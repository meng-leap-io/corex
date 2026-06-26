from __future__ import annotations

import random
import time
from abc import ABC, abstractmethod
from typing import Any, AsyncGenerator, Dict, List, Optional, Tuple

import httpx
from structlog import get_logger

from app.core.config import settings
from app.core.exceptions import (
    ProviderError,
    ProviderRateLimitError,
    ProviderTimeoutError,
)
from app.models.request import ChatCompletionRequest
from app.models.response import ModelInfo, Usage

logger = get_logger(__name__)


class CircuitBreaker:
    def __init__(self, failure_threshold: int = 5, recovery_timeout: float = 30.0):
        self.failure_threshold = failure_threshold
        self.recovery_timeout = recovery_timeout
        self.failure_count = 0
        self.last_failure_time = 0.0
        self.state = "closed"

    def record_failure(self) -> None:
        self.failure_count += 1
        self.last_failure_time = time.monotonic()
        if self.failure_count >= self.failure_threshold:
            self.state = "open"
            logger.warning("circuit_breaker_opened", failures=self.failure_count)

    def record_success(self) -> None:
        if self.state == "half-open":
            self.state = "closed"
            logger.info("circuit_breaker_closed")
        self.failure_count = 0

    def allow_request(self) -> bool:
        if self.state == "closed":
            return True
        if self.state == "open":
            if time.monotonic() - self.last_failure_time >= self.recovery_timeout:
                self.state = "half-open"
                return True
            return False
        return True


class BaseProvider(ABC):
    name: str = ""
    base_url: str = ""
    api_key_env: str = ""
    default_models: List[str] = []
    supports_streaming: bool = True

    CONNECTION_LIMITS = httpx.Limits(
        max_keepalive_connections=30,
        max_connections=150,
        keepalive_expiry=60.0,
    )

    TIMEOUT_CONFIG = httpx.Timeout(
        connect=10.0,
        read=settings.request_timeout,
        write=10.0,
        pool=5.0,
    )

    def __init__(self):
        self.api_key = self._get_api_key()
        self.client = httpx.AsyncClient(
            base_url=self.base_url,
            timeout=self.TIMEOUT_CONFIG,
            limits=self.CONNECTION_LIMITS,
            http2=True,
        )
        self.circuit_breaker = CircuitBreaker()

    @abstractmethod
    def _get_api_key(self) -> str:
        ...

    @abstractmethod
    def _get_headers(self) -> Dict[str, str]:
        ...

    async def chat_completion(
        self,
        request: ChatCompletionRequest,
    ) -> Dict[str, Any]:
        if not self.circuit_breaker.allow_request():
            raise ProviderError(
                provider=self.name,
                detail="Circuit breaker is open. Provider temporarily unavailable.",
            )

        body = self._build_chat_body(request)
        response = await self._make_request(
            method="POST",
            endpoint="/chat/completions",
            json=body,
        )
        self.circuit_breaker.record_success()
        return self._parse_chat_response(response)

    async def chat_completion_stream(
        self,
        request: ChatCompletionRequest,
    ) -> AsyncGenerator[Dict[str, Any], None]:
        body = self._build_chat_body(request)
        body["stream"] = True
        async for chunk in self._stream_request(
            method="POST",
            endpoint="/chat/completions",
            json=body,
        ):
            yield self._parse_stream_chunk(chunk)

    async def embeddings(
        self,
        model: str,
        input: str | List[str],
    ) -> Dict[str, Any]:
        body = {"model": model, "input": input}
        response = await self._make_request(
            method="POST",
            endpoint="/embeddings",
            json=body,
        )
        return response

    async def list_models(self) -> List[Dict[str, Any]]:
        response = await self._make_request(
            method="GET",
            endpoint="/models",
        )
        return response.get("data", [])

    @abstractmethod
    def _build_chat_body(self, request: ChatCompletionRequest) -> Dict[str, Any]:
        ...

    @abstractmethod
    def _parse_chat_response(self, response: Dict[str, Any]) -> Dict[str, Any]:
        ...

    def _parse_stream_chunk(self, chunk: bytes) -> Dict[str, Any]:
        return {"error": "Streaming not implemented for this provider."}

    async def _make_request(
        self,
        method: str,
        endpoint: str,
        json: Optional[Dict[str, Any]] = None,
    ) -> Dict[str, Any]:
        url = f"{self.base_url.rstrip('/')}{endpoint}"
        headers = self._get_headers()

        for attempt in range(settings.max_retries):
            try:
                response = await self.client.request(
                    method=method,
                    url=url,
                    headers=headers,
                    json=json,
                )

                if response.status_code == 429:
                    retry_after = int(response.headers.get("Retry-After", 5))
                    self.circuit_breaker.record_failure()
                    raise ProviderRateLimitError(
                        provider=self.name,
                        retry_after=retry_after,
                    )

                if response.status_code == 401:
                    raise ProviderError(
                        provider=self.name,
                        detail="Invalid API key.",
                    )

                response.raise_for_status()
                return response.json()

            except httpx.TimeoutException:
                logger.warning(
                    "provider_timeout",
                    provider=self.name,
                    attempt=attempt + 1,
                    endpoint=endpoint,
                )
                if attempt == settings.max_retries - 1:
                    self.circuit_breaker.record_failure()
                    raise ProviderTimeoutError(
                        provider=self.name,
                        timeout=settings.request_timeout,
                    )
                await self._backoff(attempt)

            except ProviderRateLimitError:
                raise

            except httpx.HTTPStatusError as e:
                logger.error(
                    "provider_http_error",
                    provider=self.name,
                    status_code=e.response.status_code,
                    body=e.response.text[:500],
                )
                if attempt == settings.max_retries - 1:
                    self.circuit_breaker.record_failure()
                    raise ProviderError(
                        provider=self.name,
                        detail=f"HTTP {e.response.status_code}: {e.response.text[:200]}",
                    )
                await self._backoff(attempt)

            except httpx.RequestError as e:
                logger.error(
                    "provider_request_error",
                    provider=self.name,
                    error=str(e),
                )
                if attempt == settings.max_retries - 1:
                    self.circuit_breaker.record_failure()
                    raise ProviderError(
                        provider=self.name,
                        detail=f"Connection error: {str(e)[:200]}",
                    )
                await self._backoff(attempt)

        raise ProviderError(provider=self.name, detail="Max retries exceeded.")

    async def _stream_request(
        self,
        method: str,
        endpoint: str,
        json: Optional[Dict[str, Any]] = None,
    ) -> AsyncGenerator[bytes, None]:
        url = f"{self.base_url.rstrip('/')}{endpoint}"
        headers = self._get_headers()

        try:
            async with self.client.stream(
                method=method,
                url=url,
                headers=headers,
                json=json,
            ) as response:
                if response.status_code == 429:
                    raise ProviderRateLimitError(provider=self.name)

                response.raise_for_status()

                async for line in response.aiter_lines():
                    if line.startswith("data: "):
                        data = line[6:]
                        if data.strip() == "[DONE]":
                            break
                        yield data.encode()

        except httpx.TimeoutException:
            raise ProviderTimeoutError(
                provider=self.name,
                timeout=settings.request_timeout,
            )
        except Exception as e:
            raise ProviderError(
                provider=self.name,
                detail=f"Stream error: {str(e)[:200]}",
            )

    async def _backoff(self, attempt: int) -> None:
        base_delay = settings.retry_delay * (2**attempt)
        jitter = random.uniform(0, 0.5 * base_delay)
        delay = base_delay + jitter
        await self._sleep(delay)

    async def _sleep(self, delay: float) -> None:
        import asyncio
        await asyncio.sleep(delay)

    async def close(self) -> None:
        await self.client.aclose()

    def estimate_cost(
        self,
        model: str,
        prompt_tokens: int,
        completion_tokens: int,
    ) -> float:
        return 0.0

    def get_model_info(self, model_id: str) -> Optional[ModelInfo]:
        return None
