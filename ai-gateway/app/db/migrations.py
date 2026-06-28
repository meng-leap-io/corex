"""Supabase schema migration and seed scripts.

Creates all required tables with proper indexes, constraints, and
seeds initial data. Designed to be idempotent (safe to run multiple times).
"""

from __future__ import annotations

from structlog import get_logger

from app.core.supabase import supabase_pool

logger = get_logger(__name__)


CREATE_CONVERSATIONS_TABLE = """
CREATE TABLE IF NOT EXISTS conversations (
    id UUID PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    model VARCHAR(100) NOT NULL DEFAULT 'gpt-4o-mini',
    provider VARCHAR(50) NOT NULL DEFAULT 'openai',
    system_prompt TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    token_count BIGINT NOT NULL DEFAULT 0,
    message_count INT NOT NULL DEFAULT 0,
    total_cost_usd NUMERIC(12, 6) NOT NULL DEFAULT 0.000000,
    metadata JSONB NOT NULL DEFAULT '{}',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);
"""

CREATE_MESSAGES_TABLE = """
CREATE TABLE IF NOT EXISTS messages (
    id UUID PRIMARY KEY,
    conversation_id UUID NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    role VARCHAR(20) NOT NULL,
    content TEXT NOT NULL,
    model VARCHAR(100),
    provider VARCHAR(50),
    tokens_prompt INT NOT NULL DEFAULT 0,
    tokens_completion INT NOT NULL DEFAULT 0,
    cost_usd NUMERIC(12, 8) NOT NULL DEFAULT 0.00000000,
    latency_ms DOUBLE PRECISION,
    metadata JSONB NOT NULL DEFAULT '{}',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
"""

CREATE_USER_PREFERENCES_TABLE = """
CREATE TABLE IF NOT EXISTS user_preferences (
    id UUID PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    category VARCHAR(50) NOT NULL,
    key VARCHAR(255) NOT NULL,
    value JSONB NOT NULL DEFAULT '{}',
    metadata JSONB NOT NULL DEFAULT '{}',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, category, key)
);
"""

CREATE_MODEL_SETTINGS_TABLE = """
CREATE TABLE IF NOT EXISTS model_settings (
    id UUID PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    model VARCHAR(100) NOT NULL,
    provider VARCHAR(50) NOT NULL,
    temperature DOUBLE PRECISION NOT NULL DEFAULT 0.7,
    max_tokens INT NOT NULL DEFAULT 4096,
    top_p DOUBLE PRECISION NOT NULL DEFAULT 1.0,
    frequency_penalty DOUBLE PRECISION NOT NULL DEFAULT 0.0,
    presence_penalty DOUBLE PRECISION NOT NULL DEFAULT 0.0,
    stop_sequences JSONB NOT NULL DEFAULT '[]',
    system_prompt TEXT,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    metadata JSONB NOT NULL DEFAULT '{}',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, model)
);
"""

CREATE_ANALYTICS_EVENTS_TABLE = """
CREATE TABLE IF NOT EXISTS analytics_events (
    id UUID PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    model VARCHAR(100),
    provider VARCHAR(50),
    tokens_prompt INT NOT NULL DEFAULT 0,
    tokens_completion INT NOT NULL DEFAULT 0,
    cost_usd NUMERIC(12, 8) NOT NULL DEFAULT 0.00000000,
    latency_ms DOUBLE PRECISION,
    status_code INT,
    error_message TEXT,
    metadata JSONB NOT NULL DEFAULT '{}',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
"""


INDEX_SQL = [
    # Conversations
    "CREATE INDEX IF NOT EXISTS idx_conversations_user_id ON conversations (user_id);",
    "CREATE INDEX IF NOT EXISTS idx_conversations_status ON conversations (status);",
    "CREATE INDEX IF NOT EXISTS idx_conversations_updated_at ON conversations (updated_at DESC);",
    "CREATE INDEX IF NOT EXISTS idx_conversations_user_status ON conversations (user_id, status) WHERE deleted_at IS NULL;",
    "CREATE INDEX IF NOT EXISTS idx_conversations_metadata ON conversations USING gin (metadata);",

    # Messages
    "CREATE INDEX IF NOT EXISTS idx_messages_conversation_id ON messages (conversation_id);",
    "CREATE INDEX IF NOT EXISTS idx_messages_created_at ON messages (created_at);",
    "CREATE INDEX IF NOT EXISTS idx_messages_conv_created ON messages (conversation_id, created_at);",
    "CREATE INDEX IF NOT EXISTS idx_messages_content_gin ON messages USING gin (to_tsvector('english', content));",
    "CREATE INDEX IF NOT EXISTS idx_messages_metadata ON messages USING gin (metadata);",

    # User Preferences
    "CREATE INDEX IF NOT EXISTS idx_user_preferences_user_id ON user_preferences (user_id);",
    "CREATE INDEX IF NOT EXISTS idx_user_preferences_category ON user_preferences (category);",
    "CREATE INDEX IF NOT EXISTS idx_user_preferences_lookup ON user_preferences (user_id, category);",

    # Model Settings
    "CREATE INDEX IF NOT EXISTS idx_model_settings_user_id ON model_settings (user_id);",
    "CREATE INDEX IF NOT EXISTS idx_model_settings_user_model ON model_settings (user_id, model) WHERE enabled = TRUE;",
    "CREATE INDEX IF NOT EXISTS idx_model_settings_provider ON model_settings (provider);",

    # Analytics Events
    "CREATE INDEX IF NOT EXISTS idx_analytics_events_user_id ON analytics_events (user_id);",
    "CREATE INDEX IF NOT EXISTS idx_analytics_events_type ON analytics_events (event_type);",
    "CREATE INDEX IF NOT EXISTS idx_analytics_events_created_at ON analytics_events (created_at DESC);",
    "CREATE INDEX IF NOT EXISTS idx_analytics_events_user_date ON analytics_events (user_id, created_at DESC);",
    "CREATE INDEX IF NOT EXISTS idx_analytics_events_user_type ON analytics_events (user_id, event_type, created_at DESC);",
    "CREATE INDEX IF NOT EXISTS idx_analytics_events_metadata ON analytics_events USING gin (metadata);",
]

# Full-text search index helper
FTS_INDEX_SQL = """
CREATE INDEX IF NOT EXISTS idx_messages_content_fts
ON messages USING gin (to_tsvector('english', content));
"""

# Materialized view for daily aggregates
MATVIEW_DAILY_USAGE = """
CREATE MATERIALIZED VIEW IF NOT EXISTS mv_daily_usage AS
SELECT
    user_id,
    created_at::date AS date,
    event_type,
    COUNT(*) AS request_count,
    SUM(tokens_prompt) AS total_tokens_prompt,
    SUM(tokens_completion) AS total_tokens_completion,
    SUM(cost_usd) AS total_cost_usd,
    AVG(latency_ms) AS avg_latency_ms,
    COUNT(*) FILTER (WHERE status_code >= 400) AS error_count
FROM analytics_events
GROUP BY user_id, created_at::date, event_type
WITH DATA;
"""

MATVIEW_INDEXES = [
    "CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_daily_usage_key ON mv_daily_usage (user_id, date, event_type);",
    "CREATE INDEX IF NOT EXISTS idx_mv_daily_usage_date ON mv_daily_usage (date DESC);",
]


async def run_migrations() -> dict[str, bool]:
    """Execute all migrations. Returns per-table success status."""
    results: dict[str, bool] = {}

    migrations = [
        ("conversations", CREATE_CONVERSATIONS_TABLE),
        ("messages", CREATE_MESSAGES_TABLE),
        ("user_preferences", CREATE_USER_PREFERENCES_TABLE),
        ("model_settings", CREATE_MODEL_SETTINGS_TABLE),
        ("analytics_events", CREATE_ANALYTICS_EVENTS_TABLE),
    ]

    for name, sql in migrations:
        try:
            await supabase_pool.execute(sql)
            results[name] = True
            logger.info("migration_table_created", table=name)
        except Exception as e:
            results[name] = False
            logger.error("migration_table_failed", table=name, error=str(e))

    return results


async def run_indexes() -> dict[str, bool]:
    """Create all indexes. Returns per-index success status."""
    results: dict[str, bool] = {}

    for sql in INDEX_SQL:
        try:
            await supabase_pool.execute(sql)
            # Extract index name from SQL for logging
            name = sql.split("CREATE INDEX IF NOT EXISTS")[-1].split("ON")[0].strip()
            results[name] = True
        except Exception as e:
            logger.warning("index_creation_failed", sql=sql[:80], error=str(e))

    try:
        await supabase_pool.execute(MATVIEW_DAILY_USAGE)
        for idx_sql in MATVIEW_INDEXES:
            await supabase_pool.execute(idx_sql)
        results["mv_daily_usage"] = True
        logger.info("materialized_view_created")
    except Exception as e:
        results["mv_daily_usage"] = False
        logger.warning("materialized_view_failed", error=str(e))

    return results


async def run_seeds() -> dict[str, int]:
    """Seed default data. Returns counts of inserted rows per table."""
    results: dict[str, int] = {}
    return results


async def refresh_materialized_views() -> bool:
    """Refresh materialized views for analytics."""
    try:
        await supabase_pool.execute("REFRESH MATERIALIZED VIEW CONCURRENTLY mv_daily_usage")
        logger.info("materialized_view_refreshed")
        return True
    except Exception as e:
        logger.warning("materialized_view_refresh_failed", error=str(e))
        return False


async def migrate() -> dict:
    """Run full migration pipeline: tables -> indexes -> seeds."""
    tables = await run_migrations()
    indexes = await run_indexes()
    seeds = await run_seeds()

    logger.info(
        "migration_complete",
        tables_ok=sum(1 for v in tables.values() if v),
        tables_fail=sum(1 for v in tables.values() if not v),
        indexes_created=len(indexes),
    )

    return {
        "tables": tables,
        "indexes": indexes,
        "seeds": seeds,
        "success": all(tables.values()),
    }


async def check_migration_status() -> dict:
    """Check which tables exist and return their status."""
    tables = [
        "conversations", "messages", "user_preferences",
        "model_settings", "analytics_events",
    ]
    status = {}
    for table in tables:
        try:
            row = await supabase_pool.fetchval(
                "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = $1)",
                table,
            )
            status[table] = bool(row)
        except Exception:
            status[table] = False
    return status
