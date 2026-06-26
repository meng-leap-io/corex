"""Tests for the rate limiter service."""

from __future__ import annotations

import time
from unittest.mock import patch

import pytest

from app.services.rate_limiter import TokenBucket, RateLimiter
from app.core.exceptions import RateLimitError


class TestTokenBucket:
    def test_initial_tokens_at_capacity(self):
        bucket = TokenBucket(10, 1.0)
        assert bucket.tokens == 10

    def test_consume_success(self):
        bucket = TokenBucket(10, 1.0)
        assert bucket.consume(5) is True
        assert bucket.tokens == 5

    def test_consume_failure_when_empty(self):
        bucket = TokenBucket(1, 0.0)
        assert bucket.consume(1) is True
        assert bucket.consume(1) is False

    def test_refilling_over_time(self):
        bucket = TokenBucket(10, 10.0)
        bucket.consume(10)
        assert bucket.tokens == 0
        time.sleep(0.11)
        assert bucket.tokens > 0

    def test_cannot_exceed_capacity(self):
        bucket = TokenBucket(5, 100.0)
        time.sleep(0.1)
        assert bucket.tokens <= 5

    def test_consume_zero_tokens(self):
        bucket = TokenBucket(10, 1.0)
        assert bucket.consume(0) is True

    def test_consume_more_than_capacity(self):
        bucket = TokenBucket(5, 1.0)
        assert bucket.consume(10) is False


@pytest.mark.asyncio
class TestRateLimiter:
    async def test_initial_enabled(self):
        with patch("app.services.rate_limiter.settings") as mock_settings:
            mock_settings.rate_limit_enabled = True
            limiter = RateLimiter()
            assert limiter._enabled is True

    async def test_disabled_when_config_off(self):
        with patch("app.services.rate_limiter.settings") as mock_settings:
            mock_settings.rate_limit_enabled = False
            limiter = RateLimiter()
            assert limiter._enabled is False

    async def test_check_passes_within_limit(self):
        with patch("app.services.rate_limiter.settings") as mock_settings:
            mock_settings.rate_limit_enabled = True
            mock_settings.rate_limit_requests = 100
            mock_settings.rate_limit_window = 60

            limiter = RateLimiter()
            result = await limiter.check("test_key")
            assert result is None

    async def test_check_raises_when_over_limit(self):
        with patch("app.services.rate_limiter.settings") as mock_settings:
            mock_settings.rate_limit_enabled = True
            mock_settings.rate_limit_requests = 1
            mock_settings.rate_limit_window = 60

            limiter = RateLimiter()
            await limiter.check("limited_key")
            with pytest.raises(RateLimitError):
                await limiter.check("limited_key")

    async def test_check_uses_custom_limits(self):
        with patch("app.services.rate_limiter.settings") as mock_settings:
            mock_settings.rate_limit_enabled = True
            mock_settings.rate_limit_requests = 100
            mock_settings.rate_limit_window = 60

            limiter = RateLimiter()
            result = await limiter.check("custom_key", max_requests=5, window=10)
            assert result is None

    async def test_disabled_limiter_always_passes(self):
        with patch("app.services.rate_limiter.settings") as mock_settings:
            mock_settings.rate_limit_enabled = False

            limiter = RateLimiter()
            result = await limiter.check("any_key")
            assert result is None

    async def test_different_keys_have_separate_buckets(self):
        with patch("app.services.rate_limiter.settings") as mock_settings:
            mock_settings.rate_limit_enabled = True
            mock_settings.rate_limit_requests = 1
            mock_settings.rate_limit_window = 60

            limiter = RateLimiter()
            await limiter.check("user_a")
            await limiter.check("user_b")
            with pytest.raises(RateLimitError):
                await limiter.check("user_a")
