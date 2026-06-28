"""AI Gateway analytics service for tracking events, metrics, and performance.

Records analytics events from AI Gateway into Supabase and provides
aggregated metrics for the dashboard. Integrates with the existing
usage_tracker and supabase_repository.
"""

from __future__ import annotations

import json
import time
from datetime import datetime, timedelta, timezone
from decimal import Decimal
from typing import Any, Optional

from structlog import get_logger

from app.core.supabase import supabase_pool
from app.models.supabase import AnalyticsEvent, AnalyticsEventType, DailyUsage

logger = get_logger(__name__)


class AnalyticsService:
    """Records and queries analytics data from the AI Gateway.

    This service extends the existing SupabaseRepository analytics methods
    with additional tracking for gateway-specific metrics (provider latency,
    token usage per model, rate limit hits, cache hit rates).
    """

    def __init__(self) -> None:
        self._pool = supabase_pool
        self._request_counts: dict[str, int] = {}
        self._last_reset: Optional[datetime] = None

    async def record_event(
        self,
        event_type: str,
        user_id: Optional[str] = None,
        metadata: Optional[dict[str, Any]] = None,
    ) -> None:
        """Record an analytics event to the analytics_events table."""
        query = """
            INSERT INTO analytics_events (id, user_id, event_type, metadata, created_at)
            VALUES (gen_random_uuid(), $1, $2, $3::jsonb, NOW())
        """
        try:
            async with self._pool.get_connection() as conn:
                await conn.execute(query, user_id, event_type, json.dumps(metadata or {}))
            logger.debug("analytics_event_recorded", event_type=event_type)
        except Exception as e:
            logger.error("analytics_event_failed", event_type=event_type, error=str(e))

    async def record_ai_request(
        self,
        user_id: str,
        provider: str,
        model: str,
        prompt_tokens: int,
        completion_tokens: int,
        duration_ms: float,
        cost: Decimal,
        success: bool = True,
        endpoint: str = "chat/completions",
        error: Optional[str] = None,
    ) -> None:
        """Record an AI provider request to both ai_usage_logs and analytics_events."""
        metadata = {
            "provider": provider,
            "model": model,
            "prompt_tokens": prompt_tokens,
            "completion_tokens": completion_tokens,
            "duration_ms": duration_ms,
            "cost": str(cost),
            "endpoint": endpoint,
            "success": success,
        }
        if error:
            metadata["error"] = error

        await self.record_event(
            event_type="ai_request",
            user_id=user_id,
            metadata=metadata,
        )

        usage_query = """
            INSERT INTO ai_usage_logs (id, user_id, provider, model, prompt_tokens,
                completion_tokens, cost, duration, endpoint, success, created_at)
            VALUES (gen_random_uuid(), $1, $2, $3, $4, $5, $6, $7, $8, $9, NOW())
        """
        try:
            async with self._pool.get_connection() as conn:
                await conn.execute(
                    usage_query,
                    user_id, provider, model,
                    prompt_tokens, completion_tokens,
                    float(cost), int(duration_ms),
                    endpoint, success,
                )
        except Exception as e:
            logger.error("ai_usage_log_failed", error=str(e))

    async def record_rate_limit_hit(
        self,
        user_id: Optional[str],
        limiter_key: str,
        limit: int,
        ttl: float,
    ) -> None:
        """Record a rate limit event."""
        await self.record_event(
            event_type="rate_limit_hit",
            user_id=user_id,
            metadata={
                "limiter_key": limiter_key,
                "limit": limit,
                "ttl_seconds": ttl,
            },
        )

    async def record_cache_miss(self, cache_key: str, duration_ms: float) -> None:
        """Record a cache miss for analytics."""
        await self.record_event(
            event_type="cache_miss",
            metadata={"cache_key": cache_key, "duration_ms": duration_ms},
        )

    async def record_provider_latency(
        self,
        provider: str,
        model: str,
        latency_ms: float,
    ) -> None:
        """Track provider-level latency metrics."""
        await self.record_event(
            event_type="provider_latency",
            metadata={
                "provider": provider,
                "model": model,
                "latency_ms": latency_ms,
            },
        )

    async def increment_counter(
        self,
        metric_key: str,
        value: float = 1.0,
        tags: Optional[dict[str, str]] = None,
    ) -> None:
        """Record a custom counter/gauge metric."""
        query = """
            INSERT INTO custom_metrics (id, metric_key, metric_type, value, tags, source, recorded_at)
            VALUES (gen_random_uuid(), $1, 'counter', $2, $3::jsonb, 'ai-gateway', NOW())
        """
        try:
            async with self._pool.get_connection() as conn:
                await conn.execute(query, metric_key, value, json.dumps(tags or {}))
        except Exception as e:
            logger.error("custom_metric_failed", metric_key=metric_key, error=str(e))

    async def get_daily_usage_summary(
        self,
        days: int = 30,
    ) -> list[dict[str, Any]]:
        """Get daily AI usage summary for the dashboard."""
        query = """
            SELECT
                created_at::date AS date,
                provider,
                model,
                COUNT(*) AS total_calls,
                SUM(prompt_tokens) AS total_prompt_tokens,
                SUM(completion_tokens) AS total_completion_tokens,
                SUM(cost) AS total_cost,
                AVG(duration) AS avg_duration_ms,
                COUNT(*) FILTER (WHERE success = false) AS error_count
            FROM ai_usage_logs
            WHERE created_at >= NOW() - ($1 || ' days')::interval
            GROUP BY created_at::date, provider, model
            ORDER BY date DESC
        """
        try:
            async with self._pool.get_connection() as conn:
                rows = await conn.fetch(query, str(days))
            result = []
            for row in rows:
                result.append({
                    "date": row["date"].isoformat() if row["date"] else None,
                    "provider": row["provider"],
                    "model": row["model"],
                    "total_calls": row["total_calls"],
                    "total_prompt_tokens": row["total_prompt_tokens"],
                    "total_completion_tokens": row["total_completion_tokens"],
                    "total_cost": float(row["total_cost"]) if row["total_cost"] else 0,
                    "avg_duration_ms": float(row["avg_duration_ms"]) if row["avg_duration_ms"] else 0,
                    "error_count": row["error_count"],
                })
            return result
        except Exception as e:
            logger.error("daily_usage_summary_failed", error=str(e))
            return []

    async def get_event_counts(
        self,
        event_type: Optional[str] = None,
        since: Optional[datetime] = None,
    ) -> list[dict[str, Any]]:
        """Get analytics event counts grouped by type and date."""
        if since is None:
            since = datetime.now(timezone.utc) - timedelta(days=7)

        query = """
            SELECT
                event_type,
                created_at::date AS date,
                COUNT(*) AS count,
                COUNT(DISTINCT user_id) AS unique_users
            FROM analytics_events
            WHERE created_at >= $1
                AND ($2::text IS NULL OR event_type = $2)
            GROUP BY event_type, created_at::date
            ORDER BY date DESC, count DESC
        """
        try:
            async with self._pool.get_connection() as conn:
                rows = await conn.fetch(query, since, event_type)
            return [
                {
                    "event_type": row["event_type"],
                    "date": row["date"].isoformat() if row["date"] else None,
                    "count": row["count"],
                    "unique_users": row["unique_users"],
                }
                for row in rows
            ]
        except Exception as e:
            logger.error("event_counts_failed", error=str(e))
            return []

    async def record_performance_metrics(self) -> dict[str, Any]:
        """Collect and record current gateway performance metrics."""
        metrics = {
            "active_connections": self._pool.stats()["active_connections"]
            if hasattr(self._pool, "stats")
            else 0,
            "queue_size": 0,
            "request_rate_per_min": self._compute_request_rate(),
            "avg_response_time_ms": 0,
        }

        query = """
            INSERT INTO performance_snapshots (
                id, active_connections, request_rate_per_min,
                extra, recorded_at
            ) VALUES (
                gen_random_uuid(), $1, $2,
                $3::jsonb, NOW()
            )
        """
        try:
            async with self._pool.get_connection() as conn:
                await conn.execute(
                    query,
                    metrics["active_connections"],
                    metrics["request_rate_per_min"],
                    json.dumps({"source": "ai-gateway"}),
                )
        except Exception as e:
            logger.error("performance_snapshot_failed", error=str(e))

        return metrics

    def _compute_request_rate(self) -> float:
        """Compute approximate request rate from internal counters."""
        now = datetime.now(timezone.utc)
        if self._last_reset is None:
            self._last_reset = now
            self._request_counts.clear()
            return 0.0

        elapsed = (now - self._last_reset).total_seconds()
        if elapsed < 1:
            return 0.0

        total = sum(self._request_counts.values())
        rate = total / (elapsed / 60) if elapsed > 0 else 0

        if elapsed > 60:
            self._last_reset = now
            self._request_counts.clear()

        return round(rate, 2)

    def increment_request_count(self, endpoint: str = "unknown") -> None:
        """Increment internal request counter (non-persistent)."""
        self._request_counts[endpoint] = self._request_counts.get(endpoint, 0) + 1


analytics_service = AnalyticsService()
