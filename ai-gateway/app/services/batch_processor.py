from __future__ import annotations

import asyncio
import time
import uuid
from dataclasses import dataclass, field
from typing import Any, Callable, Dict, List, Optional

from structlog import get_logger

logger = get_logger(__name__)


@dataclass
class BatchItem:
    id: str
    payload: Any
    callback: Optional[Callable] = None
    created_at: float = field(default_factory=time.time)
    result: Any = None
    error: Optional[str] = None


@dataclass
class BatchResult:
    items: List[BatchItem]
    total_time_ms: float
    success_count: int
    failure_count: int
    batch_id: str


class BatchProcessor:
    def __init__(
        self,
        max_batch_size: int = 20,
        max_wait_ms: float = 50.0,
        max_concurrent_batches: int = 5,
    ):
        self.max_batch_size = max_batch_size
        self.max_wait_ms = max_wait_ms
        self.max_concurrent_batches = max_concurrent_batches
        self._current_batch: List[BatchItem] = []
        self._batch_lock = asyncio.Lock()
        self._flush_event: Optional[asyncio.Event] = None
        self._flush_task: Optional[asyncio.Task] = None
        self._running = False
        self._processor_func: Optional[Callable] = None
        self._batch_count = 0
        self._item_count = 0

    async def start(self, processor_func: Callable) -> None:
        self._processor_func = processor_func
        self._running = True
        self._flush_task = asyncio.create_task(self._flush_loop())
        logger.info("batch_processor_started")

    async def stop(self) -> None:
        self._running = False
        if self._flush_task:
            self._flush_task.cancel()
            try:
                await self._flush_task
            except asyncio.CancelledError:
                pass
        await self.flush()
        logger.info(
            "batch_processor_stopped",
            total_batches=self._batch_count,
            total_items=self._item_count,
        )

    async def add(self, payload: Any, callback: Optional[Callable] = None) -> None:
        item = BatchItem(id=str(uuid.uuid4()), payload=payload, callback=callback)
        async with self._batch_lock:
            self._current_batch.append(item)
            self._item_count += 1
            if len(self._current_batch) >= self.max_batch_size:
                asyncio.create_task(self._flush_now())

    async def flush(self) -> None:
        async with self._batch_lock:
            if self._current_batch:
                await self._process_batch(self._current_batch)
                self._current_batch = []

    async def _flush_loop(self) -> None:
        while self._running:
            await asyncio.sleep(self.max_wait_ms / 1000)
            async with self._batch_lock:
                if self._current_batch:
                    batch = self._current_batch
                    self._current_batch = []
                    asyncio.create_task(self._process_batch(batch))

    async def _flush_now(self) -> None:
        async with self._batch_lock:
            batch = self._current_batch
            self._current_batch = []
        if batch:
            await self._process_batch(batch)

    async def _process_batch(self, items: List[BatchItem]) -> None:
        if not items or not self._processor_func:
            return

        self._batch_count += 1
        batch_id = str(uuid.uuid4())[:8]
        start = time.time()

        try:
            payloads = [item.payload for item in items]
            results = await self._processor_func(payloads)

            if isinstance(results, list) and len(results) == len(items):
                success = 0
                failure = 0
                for item, result in zip(items, results):
                    if isinstance(result, Exception):
                        item.error = str(result)
                        failure += 1
                    else:
                        item.result = result
                        success += 1
                        if item.callback:
                            try:
                                if asyncio.iscoroutinefunction(item.callback):
                                    await item.callback(result)
                                else:
                                    item.callback(result)
                            except Exception as cb_err:
                                logger.warning(
                                    "batch_callback_error",
                                    item_id=item.id,
                                    error=str(cb_err),
                                )
            else:
                for item in items:
                    item.error = "Invalid batch response format"
        except Exception as e:
            logger.error(
                "batch_processing_error",
                batch_id=batch_id,
                batch_size=len(items),
                error=str(e),
            )
            for item in items:
                item.error = str(e)

        elapsed = (time.time() - start) * 1000
        success_count = sum(1 for item in items if item.error is None)
        failure_count = len(items) - success_count

        logger.info(
            "batch_completed",
            batch_id=batch_id,
            size=len(items),
            elapsed_ms=round(elapsed, 1),
            success=success_count,
            failure=failure_count,
        )

    async def process_batch(
        self,
        items: List[Any],
        processor: Callable,
        concurrency: int = 5,
    ) -> List[Any]:
        semaphore = asyncio.Semaphore(concurrency)

        async def process(item: Any) -> Any:
            async with semaphore:
                if asyncio.iscoroutinefunction(processor):
                    return await processor(item)
                return processor(item)

        tasks = [process(item) for item in items]
        return await asyncio.gather(*tasks, return_exceptions=True)

    async def process_in_chunks(
        self,
        items: List[Any],
        processor: Callable,
        chunk_size: int = 10,
    ) -> List[Any]:
        results = []
        for i in range(0, len(items), chunk_size):
            chunk = items[i : i + chunk_size]
            chunk_results = await self.process_batch(chunk, processor)
            results.extend(chunk_results)
        return results

    @property
    def pending_count(self) -> int:
        return len(self._current_batch)

    def get_stats(self) -> dict:
        return {
            "total_batches": self._batch_count,
            "total_items": self._item_count,
            "pending": self.pending_count,
            "max_batch_size": self.max_batch_size,
            "max_wait_ms": self.max_wait_ms,
        }


usage_batch_processor = BatchProcessor(max_batch_size=50, max_wait_ms=100)
embedding_batch_processor = BatchProcessor(max_batch_size=10, max_wait_ms=200)
