"""Tests for the usage tracker service."""

from __future__ import annotations

from datetime import datetime, timezone
from unittest.mock import AsyncMock, patch

import pytest

from app.services.usage_tracker import UsageTracker


@pytest.fixture
def tracker():
    return UsageTracker()


@pytest.mark.asyncio
class TestUsageTracker:
    async def test_track_request_increments_count(self, tracker: UsageTracker):
        with patch.object(tracker, "_increment") as mock_inc:
            await tracker.track_request(
                user_id="user-1",
                provider="openai",
                model="gpt-4o",
                tokens=100,
                cost=0.002,
            )
            mock_inc.assert_called()

    async def test_track_request_with_zero_tokens(self, tracker: UsageTracker):
        with patch.object(tracker, "_increment") as mock_inc:
            await tracker.track_request(
                user_id="user-2",
                provider="anthropic",
                model="claude-3-haiku",
                tokens=0,
                cost=0.0,
            )
            mock_inc.assert_called()

    async def test_get_user_stats_returns_defaults(self, tracker: UsageTracker):
        with patch("app.services.usage_tracker.cache_manager.get", return_value=None):
            stats = await tracker.get_user_stats("nonexistent-user")
            assert stats["total_requests"] == 0
            assert stats["total_tokens"] == 0
            assert stats["total_cost"] == 0.0

    async def test_get_user_stats_with_data(self, tracker: UsageTracker):
        mock_data = {
            "total_requests": 42,
            "total_tokens": 15000,
            "total_cost": 0.85,
            "requests_by_model": {"gpt-4o": 30, "claude-3-sonnet": 12},
            "requests_by_provider": {"openai": 30, "anthropic": 12},
        }
        with patch("app.services.usage_tracker.cache_manager.get", return_value=mock_data):
            stats = await tracker.get_user_stats("active-user")
            assert stats["total_requests"] == 42
            assert stats["total_cost"] == 0.85

    async def test_get_global_stats(self, tracker: UsageTracker):
        with patch("app.services.usage_tracker.cache_manager.get", return_value={
            "total_requests": 1000,
            "total_users": 50,
            "total_cost": 25.0,
        }):
            stats = await tracker.get_global_stats()
            assert stats["total_requests"] == 1000
            assert stats["total_users"] == 50

    async def test_get_global_stats_empty(self, tracker: UsageTracker):
        with patch("app.services.usage_tracker.cache_manager.get", return_value=None):
            stats = await tracker.get_global_stats()
            assert stats["total_requests"] == 0

    async def test_daily_reset(self, tracker: UsageTracker):
        with patch.object(tracker, "_reset_daily") as mock_reset:
            await tracker.reset_daily_if_needed()
            mock_reset.assert_called_once()

    async def test_cost_limit_check_under(self, tracker: UsageTracker):
        with patch("app.services.usage_tracker.cache_manager.get", return_value=50.0):
            result = await tracker.check_cost_limit("user", limit=100.0)
            assert result["allowed"] is True
            assert result["current"] == 50.0
            assert result["remaining"] == 50.0

    async def test_cost_limit_check_over(self, tracker: UsageTracker):
        with patch("app.services.usage_tracker.cache_manager.get", return_value=120.0):
            result = await tracker.check_cost_limit("user", limit=100.0)
            assert result["allowed"] is False
            assert result["remaining"] == -20.0

    async def test_track_request_caches_user_stats(self, tracker: UsageTracker):
        with patch("app.services.usage_tracker.cache_manager.set") as mock_set:
            await tracker.track_request(
                user_id="cached-user",
                provider="openai",
                model="gpt-4o",
                tokens=50,
                cost=0.001,
            )
            mock_set.assert_called()

    async def test_disabled_when_config_off(self):
        with patch("app.services.usage_tracker.settings") as mock_settings:
            mock_settings.usage_tracking_enabled = False
            tracker = UsageTracker()
            with patch.object(tracker, "_increment") as mock_inc:
                await tracker.track_request("u", "p", "m", 0, 0.0)
                mock_inc.assert_not_called()
