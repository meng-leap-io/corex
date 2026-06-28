"""Async repository layer for Supabase database operations.

Provides CRUD with Redis caching, query optimization, pagination, and
error handling. All methods are async and use the shared connection pool.
"""

from __future__ import annotations

import json
from datetime import datetime, timedelta, timezone
from decimal import Decimal
from typing import Any, Optional

from structlog import get_logger

from app.core.supabase import (
    SupabasePool,
    SupabaseQueryError,
    pool_stats,
    supabase_pool,
)
from app.models.supabase import (
    AnalyticsEvent,
    AnalyticsEventCreate,
    AnalyticsEventType,
    Conversation,
    ConversationCreate,
    ConversationStatus,
    ConversationUpdate,
    DailyUsage,
    Message,
    MessageCreate,
    ModelSettings,
    ModelSettingsCreate,
    ModelSettingsUpdate,
    PaginatedResponse,
    Preference,
    PreferenceCategory,
    PreferenceCreate,
    PreferenceUpdate,
    UsageSummary,
)
from app.services.cache_manager import cache_manager

logger = get_logger(__name__)

CACHE_PREFIX_CONVERSATIONS = "supabase:conv:"
CACHE_PREFIX_MESSAGES = "supabase:msg:"
CACHE_PREFIX_PREFERENCES = "supabase:prefs:"
CACHE_PREFIX_SETTINGS = "supabase:settings:"
CACHE_PREFIX_ANALYTICS = "supabase:analytics:"
CACHE_PREFIX_USAGE = "supabase:usage:"


class SupabaseRepository:
    """High-level async repository for Supabase entities.

    Wraps SupabasePool with caching, batching, and query optimization.
    """

    def __init__(self, pool: SupabasePool):
        self.pool = pool

    # ── Conversations ─────────────────────────────────────────────────

    async def create_conversation(self, data: ConversationCreate) -> Conversation:
        conv = Conversation(user_id=data.user_id, title=data.title, model=data.model)
        row = await self.pool.fetchrow(
            """INSERT INTO conversations (id, user_id, title, model, provider, system_prompt, metadata)
               VALUES ($1, $2, $3, $4, $5, $6, $7)
               RETURNING *""",
            conv.id, conv.user_id, conv.title, conv.model,
            conv.provider.value, data.system_prompt,
            json.dumps(data.metadata),
        )
        await self._invalidate_conversation_list(conv.user_id)
        return self._row_to_conversation(row)

    async def get_conversation(self, conversation_id: str) -> Optional[Conversation]:
        cache_key = f"{CACHE_PREFIX_CONVERSATIONS}{conversation_id}"
        cached = await cache_manager.get(cache_key, tier="short")
        if cached:
            pool_stats.cache_hits += 1
            return Conversation(**cached)

        pool_stats.cache_misses += 1
        row = await self.pool.fetchrow(
            "SELECT * FROM conversations WHERE id = $1 AND deleted_at IS NULL",
            conversation_id,
        )
        if not row:
            return None
        conv = self._row_to_conversation(row)
        await cache_manager.set(cache_key, conv.model_dump(), tier="short")
        return conv

    async def list_conversations(
        self,
        user_id: str,
        page: int = 1,
        per_page: int = 20,
        status: Optional[ConversationStatus] = None,
    ) -> PaginatedResponse:
        where = "user_id = $1 AND deleted_at IS NULL"
        params: list = [user_id]
        idx = 2

        if status:
            where += f" AND status = ${idx}"
            params.append(status.value)
            idx += 1

        count = await self.pool.fetchval(
            f"SELECT COUNT(*) FROM conversations WHERE {where}", *params,
        )

        offset = (page - 1) * per_page
        rows = await self.pool.fetch(
            f"""SELECT * FROM conversations WHERE {where}
                ORDER BY updated_at DESC LIMIT ${idx} OFFSET ${idx + 1}""",
            *params, per_page, offset,
        )

        items = [self._row_to_conversation(r) for r in rows]
        return PaginatedResponse.from_list(items, count, page, per_page)

    async def update_conversation(
        self, conversation_id: str, data: ConversationUpdate,
    ) -> Optional[Conversation]:
        sets = []
        params: list = []
        idx = 1

        for field, value in data.model_dump(exclude_none=True).items():
            if field == "metadata" and value:
                value = json.dumps(value)
            sets.append(f"{field} = ${idx}")
            params.append(value)
            idx += 1

        if not sets:
            return await self.get_conversation(conversation_id)

        sets.append(f"updated_at = NOW()")
        params.append(conversation_id)

        row = await self.pool.fetchrow(
            f"""UPDATE conversations SET {', '.join(sets)}
                WHERE id = ${idx} AND deleted_at IS NULL
                RETURNING *""",
            *params,
        )
        if row:
            await cache_manager.delete(f"{CACHE_PREFIX_CONVERSATIONS}{conversation_id}")
            await self._invalidate_conversation_list(row["user_id"])
        return self._row_to_conversation(row) if row else None

    async def delete_conversation(self, conversation_id: str) -> bool:
        row = await self.pool.fetchrow(
            """UPDATE conversations SET deleted_at = NOW(), status = 'deleted', updated_at = NOW()
               WHERE id = $1 AND deleted_at IS NULL
               RETURNING user_id""",
            conversation_id,
        )
        if row:
            await cache_manager.delete(f"{CACHE_PREFIX_CONVERSATIONS}{conversation_id}")
            await cache_manager.delete_pattern(f"{CACHE_PREFIX_MESSAGES}{conversation_id}:*")
            await self._invalidate_conversation_list(row["user_id"])
            return True
        return False

    async def get_or_create_conversation(
        self, user_id: str, conversation_id: Optional[str] = None,
    ) -> Conversation:
        if conversation_id:
            conv = await self.get_conversation(conversation_id)
            if conv and conv.user_id == user_id:
                return conv
        return await self.create_conversation(
            ConversationCreate(user_id=user_id, title="New Chat"),
        )

    async def add_message(self, data: MessageCreate) -> Message:
        msg = Message(
            conversation_id=data.conversation_id,
            role=data.role,
            content=data.content,
            model=data.model,
            provider=data.provider,
            tokens_prompt=data.tokens_prompt,
            tokens_completion=data.tokens_completion,
            cost_usd=data.cost_usd,
            latency_ms=data.latency_ms,
            metadata=data.metadata,
        )
        await self.pool.execute(
            """INSERT INTO messages (id, conversation_id, role, content, model, provider,
               tokens_prompt, tokens_completion, cost_usd, latency_ms, metadata)
               VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)""",
            msg.id, msg.conversation_id, msg.role.value, msg.content,
            msg.model, msg.provider.value if msg.provider else None,
            msg.tokens_prompt, msg.tokens_completion,
            msg.cost_usd, msg.latency_ms,
            json.dumps(msg.metadata),
        )

        await self.pool.execute(
            """UPDATE conversations SET
               message_count = message_count + 1,
               token_count = token_count + $1,
               total_cost_usd = total_cost_usd + $2,
               updated_at = NOW()
               WHERE id = $3""",
            msg.tokens_prompt + msg.tokens_completion,
            msg.cost_usd,
            msg.conversation_id,
        )

        await cache_manager.delete(f"{CACHE_PREFIX_CONVERSATIONS}{msg.conversation_id}")
        return msg

    async def get_messages(
        self, conversation_id: str, limit: int = 100, before_id: Optional[str] = None,
    ) -> list[Message]:
        if before_id:
            rows = await self.pool.fetch(
                """SELECT * FROM messages
                   WHERE conversation_id = $1 AND id < $2
                   ORDER BY created_at DESC LIMIT $3""",
                conversation_id, before_id, limit,
            )
        else:
            rows = await self.pool.fetch(
                """SELECT * FROM messages
                   WHERE conversation_id = $1
                   ORDER BY created_at ASC LIMIT $2""",
                conversation_id, limit,
            )
        return [self._row_to_message(r) for r in rows]

    async def search_messages(
        self, user_id: str, query: str, limit: int = 20,
    ) -> list[Message]:
        rows = await self.pool.fetch(
            """SELECT m.* FROM messages m
               JOIN conversations c ON c.id = m.conversation_id
               WHERE c.user_id = $1 AND c.deleted_at IS NULL
               AND m.content ILIKE $2
               ORDER BY m.created_at DESC LIMIT $3""",
            user_id, f"%{query}%", limit,
        )
        return [self._row_to_message(r) for r in rows]

    # ── User Preferences ──────────────────────────────────────────────

    async def set_preference(self, data: PreferenceCreate) -> Preference:
        pref = Preference(
            user_id=data.user_id,
            category=data.category,
            key=data.key,
            value=data.value,
            metadata=data.metadata,
        )
        row = await self.pool.fetchrow(
            """INSERT INTO user_preferences (id, user_id, category, key, value, metadata)
               VALUES ($1, $2, $3, $4, $5, $6)
               ON CONFLICT (user_id, category, key)
               DO UPDATE SET value = $5, metadata = $6, updated_at = NOW()
               RETURNING *""",
            pref.id, pref.user_id, pref.category.value, pref.key,
            json.dumps(pref.value), json.dumps(pref.metadata),
        )
        await cache_manager.delete(f"{CACHE_PREFIX_PREFERENCES}{pref.user_id}:{pref.category.value}:{pref.key}")
        return self._row_to_preference(row)

    async def get_preference(
        self, user_id: str, category: PreferenceCategory, key: str,
    ) -> Optional[Preference]:
        cache_key = f"{CACHE_PREFIX_PREFERENCES}{user_id}:{category.value}:{key}"
        cached = await cache_manager.get(cache_key, tier="long")
        if cached:
            pool_stats.cache_hits += 1
            return Preference(**cached)

        pool_stats.cache_misses += 1
        row = await self.pool.fetchrow(
            "SELECT * FROM user_preferences WHERE user_id = $1 AND category = $2 AND key = $3",
            user_id, category.value, key,
        )
        if not row:
            return None
        pref = self._row_to_preference(row)
        await cache_manager.set(cache_key, pref.model_dump(), tier="long")
        return pref

    async def list_preferences(
        self, user_id: str, category: Optional[PreferenceCategory] = None,
    ) -> list[Preference]:
        if category:
            rows = await self.pool.fetch(
                "SELECT * FROM user_preferences WHERE user_id = $1 AND category = $2 ORDER BY key",
                user_id, category.value,
            )
        else:
            rows = await self.pool.fetch(
                "SELECT * FROM user_preferences WHERE user_id = $1 ORDER BY category, key",
                user_id,
            )
        return [self._row_to_preference(r) for r in rows]

    async def delete_preference(self, user_id: str, category: PreferenceCategory, key: str) -> bool:
        result = await self.pool.execute(
            "DELETE FROM user_preferences WHERE user_id = $1 AND category = $2 AND key = $3",
            user_id, category.value, key,
        )
        await cache_manager.delete(f"{CACHE_PREFIX_PREFERENCES}{user_id}:{category.value}:{key}")
        return result != "DELETE 0"

    async def get_all_preferences_dict(self, user_id: str) -> dict[str, Any]:
        prefs = await self.list_preferences(user_id)
        result: dict[str, Any] = {}
        for p in prefs:
            cat = p.category.value
            if cat not in result:
                result[cat] = {}
            result[cat][p.key] = p.value
        return result

    # ── AI Model Settings ─────────────────────────────────────────────

    async def create_model_settings(self, data: ModelSettingsCreate) -> ModelSettings:
        ms = ModelSettings(
            user_id=data.user_id,
            model=data.model,
            provider=data.provider,
            temperature=data.temperature,
            max_tokens=data.max_tokens,
            top_p=data.top_p,
            frequency_penalty=data.frequency_penalty,
            presence_penalty=data.presence_penalty,
            stop_sequences=data.stop_sequences,
            system_prompt=data.system_prompt,
            enabled=data.enabled,
            metadata=data.metadata,
        )
        row = await self.pool.fetchrow(
            """INSERT INTO model_settings (id, user_id, model, provider, temperature,
               max_tokens, top_p, frequency_penalty, presence_penalty,
               stop_sequences, system_prompt, enabled, metadata)
               VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13)
               RETURNING *""",
            ms.id, ms.user_id, ms.model, ms.provider.value,
            ms.temperature, ms.max_tokens, ms.top_p,
            ms.frequency_penalty, ms.presence_penalty,
            ms.stop_sequences, ms.system_prompt, ms.enabled,
            json.dumps(ms.metadata),
        )
        await self._invalidate_settings_list(ms.user_id)
        return self._row_to_model_settings(row)

    async def get_model_settings(self, settings_id: str) -> Optional[ModelSettings]:
        cache_key = f"{CACHE_PREFIX_SETTINGS}{settings_id}"
        cached = await cache_manager.get(cache_key, tier="long")
        if cached:
            pool_stats.cache_hits += 1
            return ModelSettings(**cached)

        pool_stats.cache_misses += 1
        row = await self.pool.fetchrow(
            "SELECT * FROM model_settings WHERE id = $1", settings_id,
        )
        if not row:
            return None
        ms = self._row_to_model_settings(row)
        await cache_manager.set(cache_key, ms.model_dump(), tier="long")
        return ms

    async def list_model_settings(
        self, user_id: str, enabled_only: bool = True,
    ) -> list[ModelSettings]:
        if enabled_only:
            rows = await self.pool.fetch(
                "SELECT * FROM model_settings WHERE user_id = $1 AND enabled = TRUE ORDER BY model",
                user_id,
            )
        else:
            rows = await self.pool.fetch(
                "SELECT * FROM model_settings WHERE user_id = $1 ORDER BY model",
                user_id,
            )
        return [self._row_to_model_settings(r) for r in rows]

    async def update_model_settings(
        self, settings_id: str, data: ModelSettingsUpdate,
    ) -> Optional[ModelSettings]:
        sets = []
        params: list = []
        idx = 1

        for field, value in data.model_dump(exclude_none=True).items():
            if field == "stop_sequences" and value is not None:
                value = json.dumps(value)
            elif field == "metadata" and value:
                value = json.dumps(value)
            sets.append(f"{field} = ${idx}")
            params.append(value)
            idx += 1

        if not sets:
            return await self.get_model_settings(settings_id)

        sets.append("updated_at = NOW()")
        params.append(settings_id)

        row = await self.pool.fetchrow(
            f"UPDATE model_settings SET {', '.join(sets)} WHERE id = ${idx} RETURNING *",
            *params,
        )
        if row:
            await cache_manager.delete(f"{CACHE_PREFIX_SETTINGS}{settings_id}")
            await self._invalidate_settings_list(row["user_id"])
        return self._row_to_model_settings(row) if row else None

    async def get_user_model_settings(
        self, user_id: str, model: str,
    ) -> Optional[ModelSettings]:
        row = await self.pool.fetchrow(
            "SELECT * FROM model_settings WHERE user_id = $1 AND model = $2 AND enabled = TRUE",
            user_id, model,
        )
        return self._row_to_model_settings(row) if row else None

    # ── Analytics ─────────────────────────────────────────────────────

    async def record_event(self, data: AnalyticsEventCreate) -> AnalyticsEvent:
        event = AnalyticsEvent(
            user_id=data.user_id,
            event_type=data.event_type,
            model=data.model,
            provider=data.provider,
            tokens_prompt=data.tokens_prompt,
            tokens_completion=data.tokens_completion,
            cost_usd=data.cost_usd,
            latency_ms=data.latency_ms,
            status_code=data.status_code,
            error_message=data.error_message,
            metadata=data.metadata,
        )
        await self.pool.execute(
            """INSERT INTO analytics_events (id, user_id, event_type, model, provider,
               tokens_prompt, tokens_completion, cost_usd, latency_ms, status_code,
               error_message, metadata)
               VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12)""",
            event.id, event.user_id, event.event_type.value,
            event.model, event.provider.value if event.provider else None,
            event.tokens_prompt, event.tokens_completion,
            event.cost_usd, event.latency_ms, event.status_code,
            event.error_message, json.dumps(event.metadata),
        )
        await cache_manager.delete(f"{CACHE_PREFIX_ANALYTICS}{event.user_id}:*")
        return event

    async def get_usage_summary(self, user_id: str) -> UsageSummary:
        cache_key = f"{CACHE_PREFIX_USAGE}summary:{user_id}"
        cached = await cache_manager.get(cache_key, tier="medium")
        if cached:
            pool_stats.cache_hits += 1
            return UsageSummary(**cached)

        pool_stats.cache_misses += 1
        today_start = datetime.now(timezone.utc).replace(
            hour=0, minute=0, second=0, microsecond=0,
        )

        row = await self.pool.fetchrow(
            """SELECT
               COUNT(*)::int AS total_requests,
               COALESCE(SUM(tokens_prompt + tokens_completion), 0)::bigint AS total_tokens,
               COALESCE(SUM(cost_usd), 0) AS total_cost_usd,
               COALESCE(AVG(latency_ms), 0) AS avg_latency_ms
               FROM analytics_events
               WHERE user_id = $1""",
            user_id,
        )

        today = await self.pool.fetchrow(
            """SELECT
               COUNT(*)::int AS requests_today,
               COALESCE(SUM(tokens_prompt + tokens_completion), 0)::bigint AS tokens_today,
               COALESCE(SUM(cost_usd), 0) AS cost_today_usd,
               COUNT(*) FILTER (WHERE status_code >= 400)::int AS error_count
               FROM analytics_events
               WHERE user_id = $1 AND created_at >= $2""",
            user_id, today_start,
        )

        top = await self.pool.fetchrow(
            """SELECT model, COUNT(*)::int AS cnt
               FROM analytics_events
               WHERE user_id = $1 AND model IS NOT NULL
               GROUP BY model ORDER BY cnt DESC LIMIT 1""",
            user_id,
        )

        total_requests = row["total_requests"] or 0
        summary = UsageSummary(
            user_id=user_id,
            total_requests=total_requests,
            total_tokens=row["total_tokens"] or 0,
            total_cost_usd=Decimal(str(row["total_cost_usd"] or 0)),
            requests_today=today["requests_today"] or 0,
            tokens_today=today["tokens_today"] or 0,
            cost_today_usd=Decimal(str(today["cost_today_usd"] or 0)),
            top_model=top["model"] if top else None,
            avg_latency_ms=float(row["avg_latency_ms"] or 0),
            error_rate=round(
                (today["error_count"] or 0) / max(today["requests_today"] or 1, 1) * 100, 2
            ),
        )

        await cache_manager.set(cache_key, summary.model_dump(), tier="medium")
        return summary

    async def get_daily_usage(
        self, user_id: str, days: int = 30,
    ) -> list[DailyUsage]:
        cache_key = f"{CACHE_PREFIX_USAGE}daily:{user_id}:{days}"
        cached = await cache_manager.get(cache_key, tier="medium")
        if cached:
            pool_stats.cache_hits += 1
            return [DailyUsage(**d) for d in cached]

        pool_stats.cache_misses += 1
        since = datetime.now(timezone.utc) - timedelta(days=days)

        rows = await self.pool.fetch(
            """SELECT
               created_at::date AS date,
               COUNT(*)::int AS total_requests,
               COALESCE(SUM(tokens_prompt), 0)::bigint AS total_tokens_prompt,
               COALESCE(SUM(tokens_completion), 0)::bigint AS total_tokens_completion,
               COALESCE(SUM(cost_usd), 0) AS total_cost_usd,
               COALESCE(AVG(latency_ms), 0) AS avg_latency_ms,
               COUNT(*) FILTER (WHERE status_code >= 400)::int AS error_count
               FROM analytics_events
               WHERE user_id = $1 AND created_at >= $2
               GROUP BY created_at::date
               ORDER BY date DESC""",
            user_id, since,
        )

        daily = []
        for r in rows:
            daily.append(DailyUsage(
                date=str(r["date"]),
                user_id=user_id,
                total_requests=r["total_requests"] or 0,
                total_tokens_prompt=r["total_tokens_prompt"] or 0,
                total_tokens_completion=r["total_tokens_completion"] or 0,
                total_cost_usd=Decimal(str(r["total_cost_usd"] or 0)),
                avg_latency_ms=float(r["avg_latency_ms"] or 0),
                error_count=r["error_count"] or 0,
            ))

        await cache_manager.set(
            cache_key, [d.model_dump() for d in daily], tier="medium",
        )
        return daily

    async def get_events(
        self,
        user_id: str,
        event_type: Optional[AnalyticsEventType] = None,
        limit: int = 50,
        offset: int = 0,
    ) -> list[AnalyticsEvent]:
        if event_type:
            rows = await self.pool.fetch(
                """SELECT * FROM analytics_events
                   WHERE user_id = $1 AND event_type = $2
                   ORDER BY created_at DESC LIMIT $3 OFFSET $4""",
                user_id, event_type.value, limit, offset,
            )
        else:
            rows = await self.pool.fetch(
                """SELECT * FROM analytics_events
                   WHERE user_id = $1
                   ORDER BY created_at DESC LIMIT $2 OFFSET $3""",
                user_id, limit, offset,
            )
        return [self._row_to_analytics_event(r) for r in rows]

    async def record_chat_usage(
        self,
        user_id: str,
        model: str,
        provider: str,
        tokens_prompt: int,
        tokens_completion: int,
        cost_usd: Decimal,
        latency_ms: float,
        status_code: int = 200,
        error_message: Optional[str] = None,
    ) -> AnalyticsEvent:
        return await self.record_event(AnalyticsEventCreate(
            user_id=user_id,
            event_type=AnalyticsEventType.CHAT_COMPLETION,
            model=model,
            provider=provider,
            tokens_prompt=tokens_prompt,
            tokens_completion=tokens_completion,
            cost_usd=cost_usd,
            latency_ms=latency_ms,
            status_code=status_code,
            error_message=error_message,
        ))

    # ── Health ────────────────────────────────────────────────────────

    async def check_tables_exist(self) -> dict[str, bool]:
        tables = ["conversations", "messages", "user_preferences", "model_settings", "analytics_events"]
        result: dict[str, bool] = {}
        for table in tables:
            try:
                row = await self.pool.fetchval(
                    "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = $1)",
                    table,
                )
                result[table] = row
            except Exception:
                result[table] = False
        return result

    async def get_table_row_counts(self) -> dict[str, int]:
        tables = ["conversations", "messages", "user_preferences", "model_settings", "analytics_events"]
        result: dict[str, int] = {}
        for table in tables:
            try:
                count = await self.pool.fetchval(f"SELECT COUNT(*) FROM {table}")
                result[table] = count
            except Exception:
                result[table] = -1
        return result

    # ── Internal helpers ──────────────────────────────────────────────

    async def _invalidate_conversation_list(self, user_id: str) -> None:
        await cache_manager.delete_pattern(f"{CACHE_PREFIX_CONVERSATIONS}list:{user_id}:*")

    async def _invalidate_settings_list(self, user_id: str) -> None:
        await cache_manager.delete_pattern(f"{CACHE_PREFIX_SETTINGS}list:{user_id}:*")

    @staticmethod
    def _row_to_conversation(row) -> Conversation:
        return Conversation(
            id=row["id"],
            user_id=row["user_id"],
            title=row.get("title", ""),
            model=row.get("model", "gpt-4o-mini"),
            provider=row.get("provider", "openai"),
            system_prompt=row.get("system_prompt"),
            status=row.get("status", "active"),
            token_count=row.get("token_count", 0),
            message_count=row.get("message_count", 0),
            total_cost_usd=Decimal(str(row.get("total_cost_usd", 0))),
            metadata=row.get("metadata") or {},
            created_at=row.get("created_at"),
            updated_at=row.get("updated_at"),
            deleted_at=row.get("deleted_at"),
        )

    @staticmethod
    def _row_to_message(row) -> Message:
        return Message(
            id=row["id"],
            conversation_id=row["conversation_id"],
            role=row["role"],
            content=row["content"],
            model=row.get("model"),
            provider=row.get("provider"),
            tokens_prompt=row.get("tokens_prompt", 0),
            tokens_completion=row.get("tokens_completion", 0),
            cost_usd=Decimal(str(row.get("cost_usd", 0))),
            latency_ms=row.get("latency_ms"),
            metadata=row.get("metadata") or {},
            created_at=row.get("created_at"),
        )

    @staticmethod
    def _row_to_preference(row) -> Preference:
        value = row["value"]
        if isinstance(value, str):
            try:
                value = json.loads(value)
            except (json.JSONDecodeError, TypeError):
                pass
        return Preference(
            id=row["id"],
            user_id=row["user_id"],
            category=row["category"],
            key=row["key"],
            value=value,
            metadata=row.get("metadata") or {},
            created_at=row.get("created_at"),
            updated_at=row.get("updated_at"),
        )

    @staticmethod
    def _row_to_model_settings(row) -> ModelSettings:
        stop = row.get("stop_sequences")
        if isinstance(stop, str):
            try:
                stop = json.loads(stop)
            except (json.JSONDecodeError, TypeError):
                stop = []
        return ModelSettings(
            id=row["id"],
            user_id=row["user_id"],
            model=row["model"],
            provider=row["provider"],
            temperature=row.get("temperature", 0.7),
            max_tokens=row.get("max_tokens", 4096),
            top_p=row.get("top_p", 1.0),
            frequency_penalty=row.get("frequency_penalty", 0.0),
            presence_penalty=row.get("presence_penalty", 0.0),
            stop_sequences=stop or [],
            system_prompt=row.get("system_prompt"),
            enabled=row.get("enabled", True),
            metadata=row.get("metadata") or {},
            created_at=row.get("created_at"),
            updated_at=row.get("updated_at"),
        )

    @staticmethod
    def _row_to_analytics_event(row) -> AnalyticsEvent:
        return AnalyticsEvent(
            id=row["id"],
            user_id=row["user_id"],
            event_type=row["event_type"],
            model=row.get("model"),
            provider=row.get("provider"),
            tokens_prompt=row.get("tokens_prompt", 0),
            tokens_completion=row.get("tokens_completion", 0),
            cost_usd=Decimal(str(row.get("cost_usd", 0))),
            latency_ms=row.get("latency_ms"),
            status_code=row.get("status_code"),
            error_message=row.get("error_message"),
            metadata=row.get("metadata") or {},
            created_at=row.get("created_at"),
        )


supabase_repository = SupabaseRepository(supabase_pool)
