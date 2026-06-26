from __future__ import annotations

import time
from typing import Dict, Optional

from structlog import get_logger

from app.core.config import settings
from app.core.exceptions import RateLimitError
from app.services.cache_manager import cache_manager

logger = get_logger(__name__)


class TokenBucket:
    def __init__(self, capacity: int, refill_rate: float):
        self.capacity = capacity
        self.refill_rate = refill_rate
        self.tokens = capacity
        self.last_refill = time.monotonic()

    def consume(self, tokens: int = 1) -> bool:
        now = time.monotonic()
        elapsed = now - self.last_refill
        self.tokens = min(self.capacity, self.tokens + elapsed * self.refill_rate)
        self.last_refill = now
        if self.tokens >= tokens:
            self.tokens -= tokens
            return True
        return False


class RateLimiter:
    def __init__(self):
        self._local_buckets: Dict[str, TokenBucket] = {}
        self._enabled = settings.rate_limit_enabled

    async def check(
        self,
        key: str,
        max_requests: Optional[int] = None,
        window: Optional[int] = None,
    ) -> None:
        if not self._enabled:
            return

        max_req = max_requests or settings.rate_limit_requests
        win = window or settings.rate_limit_window

        redis = await self._get_redis()
        if redis:
            await self._check_redis(key, max_req, win)
        else:
            self._check_local(key, max_req, win)

    async def _check_redis(self, key: str, max_requests: int, window: int) -> None:
        now = int(time.time())
        window_key = f"ratelimit:{key}:{now // window}"
        try:
            count = await cache_manager.increment(window_key, 1, window + 5)
            if count > max_requests:
                ttl = await cache_manager.get_ttl(window_key)
                logger.warning(
                    "rate_limit_exceeded",
                    key=key,
                    count=count,
                    max=max_requests,
                )
                raise RateLimitError(
                    detail=f"Rate limit exceeded. Try again in {ttl} seconds.",
                )
        except RateLimitError:
            raise
        except Exception as e:
            logger.warning("rate_limit_redis_error", key=key, error=str(e))
            self._check_local(key, max_requests, window)

    def _check_local(self, key: str, max_requests: int, window: int) -> None:
        bucket = self._local_buckets.get(key)
        if not bucket:
            refill_rate = max_requests / window
            bucket = TokenBucket(max_requests, refill_rate)
            self._local_buckets[key] = bucket

        if not bucket.consume():
            logger.warning("rate_limit_exceeded_local", key=key)
            raise RateLimitError()

    async def reset(self, key: str) -> None:
        await cache_manager.delete_pattern(f"ratelimit:{key}:*")
        self._local_buckets.pop(key, None)

    async def get_remaining(self, key: str, max_requests: int, window: int) -> int:
        now = int(time.time())
        window_key = f"ratelimit:{key}:{now // window}"
        try:
            count = await cache_manager.get(window_key) or 0
            return max(0, max_requests - int(count))
        except Exception:
            return max_requests

    async def _get_redis(self):
        try:
            from app.services.cache_manager import cache_manager
            redis = await cache_manager._get_redis()
            return redis
        except Exception:
            return None


rate_limiter = RateLimiter()
