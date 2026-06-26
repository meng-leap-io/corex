from __future__ import annotations

import time
from datetime import datetime, timezone
from typing import Any, Dict, Optional

from structlog import get_logger

from app.core.config import settings
from app.services.cache_manager import cache_manager

logger = get_logger(__name__)


class UsageTracker:
    def __init__(self):
        self._enabled = settings.usage_tracking_enabled
        self._daily_usage: Dict[str, Dict[str, Any]] = {}

    async def track_usage(
        self,
        user_id: Optional[str],
        provider: str,
        model: str,
        prompt_tokens: int,
        completion_tokens: int,
        cost: float,
        duration: float,
        endpoint: str,
        success: bool,
    ) -> None:
        if not self._enabled:
            return

        now = datetime.now(timezone.utc)
        today = now.strftime("%Y-%m-%d")

        stats = {
            "user_id": user_id or "anonymous",
            "provider": provider,
            "model": model,
            "prompt_tokens": prompt_tokens,
            "completion_tokens": completion_tokens,
            "total_tokens": prompt_tokens + completion_tokens,
            "cost": cost,
            "duration": duration,
            "endpoint": endpoint,
            "success": success,
            "timestamp": now.isoformat(),
        }

        if user_id:
            await self._track_user_usage(user_id, today, stats)

        await self._track_global_usage(today, stats)

    async def _track_user_usage(
        self,
        user_id: str,
        today: str,
        stats: Dict[str, Any],
    ) -> None:
        key = f"usage:daily:{user_id}:{today}"
        try:
            await cache_manager.increment(f"{key}:tokens", stats["total_tokens"], 86400)
            await cache_manager.increment(f"{key}:cost", int(stats["cost"] * 100000), 86400)
            await cache_manager.increment(f"{key}:requests", 1, 86400)
        except Exception as e:
            logger.warning("usage_track_error", user_id=user_id, error=str(e))

        if user_id not in self._daily_usage:
            self._daily_usage[user_id] = {"total_tokens": 0, "total_cost": 0, "total_requests": 0, "date": today}
        usage = self._daily_usage[user_id]
        usage["total_tokens"] += stats["total_tokens"]
        usage["total_cost"] += stats["cost"]
        usage["total_requests"] += 1

    async def _track_global_usage(self, today: str, stats: Dict[str, Any]) -> None:
        key = f"usage:global:{today}"
        try:
            await cache_manager.increment(f"{key}:tokens", stats["total_tokens"], 86400)
            await cache_manager.increment(f"{key}:cost", int(stats["cost"] * 100000), 86400)
            await cache_manager.increment(f"{key}:requests", 1, 86400)
        except Exception:
            pass

    async def get_user_stats(
        self,
        user_id: Optional[str],
        days: int = 30,
    ) -> Dict[str, Any]:
        if not user_id:
            return self._empty_stats()

        total_tokens = 0
        total_cost = 0
        total_requests = 0

        for day_offset in range(days):
            day = datetime.now(timezone.utc).timestamp() - day_offset * 86400
            date_str = datetime.fromtimestamp(day, tz=timezone.utc).strftime("%Y-%m-%d")
            key = f"usage:daily:{user_id}:{date_str}"
            try:
                tokens = await cache_manager.get(f"{key}:tokens") or 0
                cost_raw = await cache_manager.get(f"{key}:cost") or 0
                requests = await cache_manager.get(f"{key}:requests") or 0
                total_tokens += int(tokens)
                total_cost += int(cost_raw) / 100000
                total_requests += int(requests)
            except Exception:
                pass

        user_usage = self._daily_usage.get(user_id, {})

        return {
            "total_requests": total_requests or user_usage.get("total_requests", 0),
            "total_tokens": total_tokens or user_usage.get("total_tokens", 0),
            "total_cost": round(total_cost or user_usage.get("total_cost", 0), 4),
            "remaining_credits": max(0, settings.max_daily_cost_usd - (total_cost or user_usage.get("total_cost", 0))),
            "period_days": days,
        }

    async def get_global_stats(self, days: int = 1) -> Dict[str, Any]:
        total_requests = 0
        total_tokens = 0
        total_cost = 0

        for day_offset in range(days):
            day = datetime.now(timezone.utc).timestamp() - day_offset * 86400
            date_str = datetime.fromtimestamp(day, tz=timezone.utc).strftime("%Y-%m-%d")
            key = f"usage:global:{date_str}"
            try:
                requests = await cache_manager.get(f"{key}:requests") or 0
                tokens = await cache_manager.get(f"{key}:tokens") or 0
                cost_raw = await cache_manager.get(f"{key}:cost") or 0
                total_requests += int(requests)
                total_tokens += int(tokens)
                total_cost += int(cost_raw) / 100000
            except Exception:
                pass

        return {
            "total_requests": total_requests,
            "total_tokens": total_tokens,
            "total_cost": round(total_cost, 4),
            "period_days": days,
        }

    async def can_make_request(
        self,
        user_id: Optional[str],
        estimated_cost: float = 0,
    ) -> bool:
        if not self._enabled or not user_id:
            return True

        stats = await self.get_user_stats(user_id, days=1)
        total_spent = stats.get("total_cost", 0)
        return (total_spent + estimated_cost) < settings.max_daily_cost_usd

    def _empty_stats(self) -> Dict[str, Any]:
        return {
            "total_requests": 0,
            "total_tokens": 0,
            "total_cost": 0.0,
            "remaining_credits": settings.max_daily_cost_usd,
            "period_days": 30,
        }


usage_tracker = UsageTracker()
