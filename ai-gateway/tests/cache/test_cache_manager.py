"""Tests for the cache manager service."""

from __future__ import annotations

from unittest.mock import AsyncMock, patch

import pytest

from app.services.cache_manager import CacheManager


@pytest.fixture
def cache():
    return CacheManager()


@pytest.mark.asyncio
class TestCacheManager:
    async def test_cache_set_and_get(self, cache: CacheManager):
        await cache.set("test_key", {"data": "value"}, ttl=60)
        result = await cache.get("test_key")
        assert result == {"data": "value"}

    async def test_cache_get_missing_key(self, cache: CacheManager):
        result = await cache.get("nonexistent_key")
        assert result is None

    async def test_cache_set_overwrites_existing(self, cache: CacheManager):
        await cache.set("key", "old_value")
        await cache.set("key", "new_value")
        result = await cache.get("key")
        assert result == "new_value"

    async def test_cache_delete_removes_key(self, cache: CacheManager):
        await cache.set("key", "value")
        await cache.delete("key")
        result = await cache.get("key")
        assert result is None

    async def test_cache_delete_missing_key(self, cache: CacheManager):
        result = await cache.delete("nonexistent")
        assert result is True

    async def test_cache_clear_all(self, cache: CacheManager):
        await cache.set("k1", "v1")
        await cache.set("k2", "v2")
        await cache.clear()
        assert await cache.get("k1") is None
        assert await cache.get("k2") is None

    async def test_cache_ttl_expiry(self, cache: CacheManager):
        await cache.set("volatile", "data", ttl=0)
        result = await cache.get("volatile")
        assert result is None

    async def test_cache_handles_complex_types(self, cache: CacheManager):
        complex_data = {
            "string": "hello",
            "number": 42,
            "list": [1, 2, 3],
            "nested": {"key": "value"},
            "boolean": True,
            "null": None,
        }
        await cache.set("complex", complex_data)
        result = await cache.get("complex")
        assert result == complex_data

    async def test_cache_disabled(self, cache: CacheManager):
        cache._enabled = False
        await cache.set("key", "value")
        result = await cache.get("key")
        assert result is None

    async def test_cache_enabled_default(self):
        cache = CacheManager()
        assert cache._enabled is True

    async def test_cache_local_fallback_on_redis_failure(self, cache: CacheManager):
        cache._redis = None
        cache._enabled = False
        cache._local_cache["local_key"] = "local_value"
        result = await cache.get("local_key")
        assert result == "local_value"
