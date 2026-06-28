"""Tests for the AI Gateway AnalyticsService."""

from __future__ import annotations

from datetime import datetime, timedelta, timezone
from decimal import Decimal
from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.services.analytics_service import AnalyticsService


class MockRecord(dict):
    """A dict subclass that also supports attribute access like asyncpg records."""
    def __getattr__(self, name):
        try:
            return self[name]
        except KeyError:
            raise AttributeError(name)


@pytest.fixture
def analytics_service() -> AnalyticsService:
    service = AnalyticsService()
    service._pool = MagicMock()
    return service


@pytest.mark.asyncio
async def test_record_event(analytics_service: AnalyticsService):
    mock_conn = AsyncMock()
    analytics_service._pool.get_connection.return_value.__aenter__.return_value = mock_conn

    await analytics_service.record_event(
        event_type="test_event",
        user_id="user-123",
        metadata={"key": "value"},
    )

    assert mock_conn.execute.called
    call_args = mock_conn.execute.call_args
    assert call_args[0][1] == "user-123"
    assert call_args[0][2] == "test_event"


@pytest.mark.asyncio
async def test_record_ai_request(analytics_service: AnalyticsService):
    mock_conn = AsyncMock()
    analytics_service._pool.get_connection.return_value.__aenter__.return_value = mock_conn

    await analytics_service.record_ai_request(
        user_id="user-123",
        provider="openai",
        model="gpt-4o",
        prompt_tokens=100,
        completion_tokens=50,
        duration_ms=1500.0,
        cost=Decimal("0.005"),
        success=True,
    )

    assert mock_conn.execute.call_count == 2


@pytest.mark.asyncio
async def test_record_rate_limit_hit(analytics_service: AnalyticsService):
    mock_conn = AsyncMock()
    analytics_service._pool.get_connection.return_value.__aenter__.return_value = mock_conn

    await analytics_service.record_rate_limit_hit(
        user_id="user-123",
        limiter_key="api:user-123",
        limit=100,
        ttl=60.0,
    )

    assert mock_conn.execute.called


@pytest.mark.asyncio
async def test_increment_counter(analytics_service: AnalyticsService):
    mock_conn = AsyncMock()
    analytics_service._pool.get_connection.return_value.__aenter__.return_value = mock_conn

    await analytics_service.increment_counter(
        metric_key="test_counter",
        value=1.0,
        tags={"env": "test"},
    )

    assert mock_conn.execute.called
    call_args = mock_conn.execute.call_args
    assert "custom_metrics" in call_args[0][0]


@pytest.mark.asyncio
async def test_get_daily_usage_summary(analytics_service: AnalyticsService):
    now = datetime.now(timezone.utc)
    mock_conn = AsyncMock()
    mock_conn.fetch = AsyncMock(return_value=[
        MockRecord({
            "date": now.date(),
            "provider": "openai",
            "model": "gpt-4o",
            "total_calls": 100,
            "total_prompt_tokens": 10000,
            "total_completion_tokens": 5000,
            "total_cost": 0.50,
            "avg_duration_ms": 1200.0,
            "error_count": 2,
        }),
    ])
    analytics_service._pool.get_connection.return_value.__aenter__.return_value = mock_conn

    result = await analytics_service.get_daily_usage_summary(days=7)

    assert len(result) == 1
    assert result[0]["provider"] == "openai"
    assert result[0]["total_calls"] == 100
    assert result[0]["total_cost"] == 0.50


@pytest.mark.asyncio
async def test_get_event_counts(analytics_service: AnalyticsService):
    now = datetime.now(timezone.utc)
    mock_conn = AsyncMock()
    mock_conn.fetch = AsyncMock(return_value=[
        MockRecord({
            "event_type": "page_view",
            "date": now.date(),
            "count": 50,
            "unique_users": 10,
        }),
    ])
    analytics_service._pool.get_connection.return_value.__aenter__.return_value = mock_conn

    result = await analytics_service.get_event_counts(event_type="page_view")

    assert len(result) == 1
    assert result[0]["event_type"] == "page_view"
    assert result[0]["count"] == 50


@pytest.mark.asyncio
async def test_increment_request_count(analytics_service: AnalyticsService):
    assert analytics_service._request_counts.get("test_endpoint") is None
    analytics_service.increment_request_count("test_endpoint")
    assert analytics_service._request_counts["test_endpoint"] == 1
    analytics_service.increment_request_count("test_endpoint")
    assert analytics_service._request_counts["test_endpoint"] == 2


def test_compute_request_rate(analytics_service: AnalyticsService):
    analytics_service._last_reset = datetime.now(timezone.utc) - timedelta(minutes=2)
    analytics_service._request_counts = {"chat": 60}

    rate = analytics_service._compute_request_rate()
    assert rate > 0


@pytest.mark.asyncio
async def test_record_performance_metrics(analytics_service: AnalyticsService):
    mock_conn = AsyncMock()
    analytics_service._pool.get_connection.return_value.__aenter__.return_value = mock_conn

    result = await analytics_service.record_performance_metrics()

    assert "active_connections" in result
    assert "request_rate_per_min" in result
    assert mock_conn.execute.called
