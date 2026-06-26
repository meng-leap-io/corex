from __future__ import annotations

import gzip
import json
import time
from typing import Any, Optional

from structlog import get_logger

from app.core.config import settings

logger = get_logger(__name__)

CACHE_TIERS = {
    "short": 60,
    "medium": 300,
    "long": 3600,
    "day": 86400,
    "week": 604800,
}


class CacheManager:
    def __init__(self):
        self._redis = None
        self._local_cache: dict = {}
        self._local_max_size = 500
        self._local_ttl: dict[str, float] = {}
        self._local_default_ttl = 60
        self._enabled = True
        self._hits = 0
        self._misses = 0
        self._redis_hits = 0
        self._redis_misses = 0

    async def _get_redis(self):
        if self._redis is None and self._enabled:
            try:
                import redis.asyncio as aioredis
                self._redis = aioredis.from_url(
                    settings.redis_url,
                    decode_responses=True,
                    socket_connect_timeout=2,
                    socket_timeout=2,
                    retry_on_timeout=True,
                    max_connections=20,
                )
                await self._redis.ping()
                logger.info("redis_connected")
            except Exception as e:
                logger.warning("redis_connection_failed", error=str(e))
                self._enabled = False
                self._redis = None
        return self._redis

    def _tier_ttl(self, tier: str, default: int = 300) -> int:
        return CACHE_TIERS.get(tier, default)

    def _compress(self, data: str) -> bytes:
        return gzip.compress(data.encode())

    def _decompress(self, data: bytes) -> str:
        return gzip.decompress(data).decode()

    async def get(self, key: str, tier: str = "medium") -> Optional[Any]:
        now = time.monotonic()
        local_entry = self._local_cache.get(key)
        if local_entry is not None and self._local_ttl.get(key, 0) > now:
            self._hits += 1
            return local_entry

        redis = await self._get_redis()
        if redis:
            try:
                data = await redis.get(key)
                if data:
                    self._redis_hits += 1
                    compressed = data.startswith("_gzip:")
                    raw = data[5:] if compressed else data
                    value = json.loads(raw)
                    self._set_local(key, value, tier)
                    return value
                self._redis_misses += 1
            except Exception as e:
                logger.warning("cache_get_error", key=key, error=str(e))

        self._misses += 1
        self._local_cache.pop(key, None)
        self._local_ttl.pop(key, None)
        return None

    def _set_local(self, key: str, value: Any, tier: str = "medium") -> None:
        if len(self._local_cache) >= self._local_max_size:
                oldest = min(self._local_ttl, key=lambda k: self._local_ttl[k])
                self._local_cache.pop(oldest, None)
                self._local_ttl.pop(oldest, None)

        self._local_cache[key] = value
        self._local_ttl[key] = time.monotonic() + self._tier_ttl(tier, self._local_default_ttl)

    async def set(
        self,
        key: str,
        value: Any,
        ttl: Optional[int] = None,
        tier: str = "medium",
        compress: bool = False,
    ) -> bool:
        ttl = ttl or self._tier_ttl(tier)
        self._set_local(key, value, tier)

        redis = await self._get_redis()
        if redis:
            try:
                serialized = json.dumps(value, default=str)
                if compress and len(serialized) > 2048:
                    compressed = self._compress(serialized)
                    await redis.setex(key, ttl, compressed)
                else:
                    await redis.setex(key, ttl, serialized)
                return True
            except Exception as e:
                logger.warning("cache_set_error", key=key, error=str(e))
                return False
        return True

    async def set_many(self, mapping: dict[str, Any], ttl: int = 300) -> bool:
        redis = await self._get_redis()
        if redis:
            try:
                pipe = redis.pipeline()
                for key, value in mapping.items():
                    serialized = json.dumps(value, default=str)
                    pipe.setex(key, ttl, serialized)
                await pipe.execute()
                return True
            except Exception as e:
                logger.warning("cache_set_many_error", error=str(e))
        for key, value in mapping.items():
            self._set_local(key, value)
        return True

    async def get_many(self, keys: list[str]) -> dict[str, Any]:
        result = {}
        redis_keys = []

        for key in keys:
            now = time.monotonic()
            local_val = self._local_cache.get(key)
            if local_val is not None and self._local_ttl.get(key, 0) > now:
                result[key] = local_val
            else:
                redis_keys.append(key)

        if redis_keys:
            redis = await self._get_redis()
            if redis:
                try:
                    values = await redis.mget(redis_keys)
                    for key, val in zip(redis_keys, values):
                        if val:
                            decoded = json.loads(val)
                            result[key] = decoded
                            self._set_local(key, decoded)
                            self._redis_hits += 1
                        else:
                            self._redis_misses += 1
                except Exception:
                    pass
        return result

    async def warm(self, keys: dict[str, Any], ttl: int = 300) -> int:
        warm_count = 0
        redis = await self._get_redis()
        if redis:
            try:
                pipe = redis.pipeline()
                for key, value in keys.items():
                    serialized = json.dumps(value, default=str)
                    pipe.setex(key, ttl, serialized)
                    warm_count += 1
                await pipe.execute()
                logger.info("cache_warmed", count=warm_count)
            except Exception as e:
                logger.warning("cache_warm_error", error=str(e))
        for key, value in keys.items():
            self._set_local(key, value)
            warm_count += 1
        return warm_count

    async def delete(self, key: str) -> bool:
        redis = await self._get_redis()
        if redis:
            try:
                await redis.delete(key)
            except Exception:
                pass
        self._local_cache.pop(key, None)
        self._local_ttl.pop(key, None)
        return True

    async def delete_pattern(self, pattern: str) -> int:
        redis = await self._get_redis()
        count = 0
        if redis:
            try:
                cursor = 0
                while True:
                    cursor, keys = await redis.scan(cursor=cursor, match=pattern, count=100)
                    if keys:
                        await redis.delete(*keys)
                        count += len(keys)
                    if cursor == 0:
                        break
            except Exception:
                pass
        self._local_cache = {k: v for k, v in self._local_cache.items() if pattern not in k}
        self._local_ttl = {k: v for k, v in self._local_ttl.items() if pattern not in k}
        return count

    async def exists(self, key: str) -> bool:
        now = time.monotonic()
        if key in self._local_cache and self._local_ttl.get(key, 0) > now:
            return True
        redis = await self._get_redis()
        if redis:
            try:
                return await redis.exists(key) > 0
            except Exception:
                pass
        return key in self._local_cache

    async def increment(self, key: str, amount: int = 1, ttl: int = 60) -> int:
        redis = await self._get_redis()
        if redis:
            try:
                val = await redis.incrby(key, amount)
                await redis.expire(key, ttl)
                return val
            except Exception:
                pass
        now = time.monotonic()
        val = self._local_cache.get(key, 0)
        val += amount
        self._local_cache[key] = val
        self._local_ttl[key] = now + ttl
        return val

    async def get_ttl(self, key: str) -> int:
        redis = await self._get_redis()
        if redis:
            try:
                return await redis.ttl(key)
            except Exception:
                pass
        now = time.monotonic()
        expiry = self._local_ttl.get(key)
        if expiry:
            return max(0, int(expiry - now))
        return -1

    async def clear(self) -> bool:
        redis = await self._get_redis()
        if redis:
            try:
                await redis.flushdb()
            except Exception:
                pass
        self._local_cache.clear()
        self._local_ttl.clear()
        return True

    async def health_check(self) -> bool:
        redis = await self._get_redis()
        if redis:
            try:
                await redis.ping()
                return True
            except Exception:
                return False
        return bool(self._local_cache) or False

    async def get_stats(self) -> dict:
        return {
            "hits": self._hits,
            "misses": self._misses,
            "redis_hits": self._redis_hits,
            "redis_misses": self._redis_misses,
            "hit_ratio": round(
                self._hits / (self._hits + self._misses), 4
            ) if (self._hits + self._misses) > 0 else 0,
            "local_cache_size": len(self._local_cache),
            "redis_enabled": self._redis is not None,
        }

    async def close(self) -> None:
        if self._redis:
            try:
                await self._redis.close()
            except Exception:
                pass


cache_manager = CacheManager()
