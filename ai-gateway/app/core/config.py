"""Configuration for the Corex AI Gateway.

Windows-aware: uses Windows paths, registry overrides, and environment
variable fallbacks for all platform-specific settings.
"""

from __future__ import annotations

import os
import sys
from enum import Enum
from pathlib import Path
from typing import List, Optional

from pydantic import Field, field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict

from app.core.windows import (
    IS_WINDOWS,
    get_hostname,
    get_default_data_dir,
    get_default_log_dir,
    get_prometheus_multiproc_dir,
    read_registry,
    resolve_path,
)


class Environment(str, Enum):
    DEVELOPMENT = "development"
    STAGING = "staging"
    PRODUCTION = "production"


class LogLevel(str, Enum):
    DEBUG = "debug"
    INFO = "info"
    WARNING = "warning"
    ERROR = "error"
    CRITICAL = "critical"


class ProviderStrategy(str, Enum):
    """Provider selection strategy for Windows desktop mode."""

    AUTO = "auto"          # Try remote, fallback to local
    REMOTE_FIRST = "remote_first"
    LOCAL_FIRST = "local_first"
    LOCAL_ONLY = "local_only"   # Fully offline
    REMOTE_ONLY = "remote_only"


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
        extra="allow",
    )

    # ── Application ─────────────────────────────────────────────────
    app_name: str = "Corex AI Gateway"
    app_version: str = "1.0.0"
    environment: Environment = Environment.PRODUCTION
    debug: bool = False
    log_level: LogLevel = LogLevel.INFO
    sentry_dsn: Optional[str] = None

    # ── Server ──────────────────────────────────────────────────────
    host: str = Field(default="0.0.0.0" if not IS_WINDOWS else "127.0.0.1", alias="HOST")
    port: int = Field(default=8000, alias="PORT")
    workers: int = Field(default=4 if not IS_WINDOWS else 2)
    cors_origins: List[str] = [
        "https://corex.dev",
        "https://console.corex.dev",
        "http://localhost:3000",
        "http://localhost:8000",
    ]

    # ── Paths (Windows-aware via app.core.windows) ──────────────────
    data_dir: str = Field(default=str(get_default_data_dir()), alias="COREX_DATA_DIR")
    log_dir: str = Field(default=str(get_default_log_dir()), alias="COREX_LOG_DIR")
    prometheus_multiproc_dir: str = Field(
        default=str(get_prometheus_multiproc_dir()), alias="PROMETHEUS_MULTIPROC_DIR"
    )

    @field_validator("data_dir", "log_dir", "prometheus_multiproc_dir", mode="before")
    @classmethod
    def _ensure_dir_exists(cls, v: str) -> str:
        p = Path(v)
        p.mkdir(parents=True, exist_ok=True)
        return str(p.resolve())

    @property
    def data_path(self) -> Path:
        return resolve_path(self.data_dir)

    @property
    def log_path(self) -> Path:
        return resolve_path(self.log_dir)

    # ── Redis ───────────────────────────────────────────────────────
    redis_host: str = Field(
        default="redis" if not IS_WINDOWS else read_registry("redis_host", "127.0.0.1"),
        alias="REDIS_HOST",
    )
    redis_port: int = Field(default=6379, alias="REDIS_PORT")
    redis_password: Optional[str] = Field(default=None, alias="REDIS_PASSWORD")
    redis_db: int = Field(default=0, alias="REDIS_DB")
    redis_cache_ttl: int = Field(default=300, alias="REDIS_CACHE_TTL")

    # ── Provider Strategy ───────────────────────────────────────────
    provider_strategy: ProviderStrategy = Field(
        default=ProviderStrategy.AUTO, alias="PROVIDER_STRATEGY"
    )

    # ── Auth / JWT ──────────────────────────────────────────────────
    jwt_secret: str = Field(default="", alias="JWT_SECRET")
    jwt_algorithm: str = "HS256"
    jwt_expiration: int = 3600

    # ── OpenAI ──────────────────────────────────────────────────────
    openai_api_key: Optional[str] = Field(default=None, alias="OPENAI_API_KEY")
    openai_organization: Optional[str] = None
    openai_base_url: str = "https://api.openai.com/v1"

    # ── Anthropic ───────────────────────────────────────────────────
    anthropic_api_key: Optional[str] = Field(default=None, alias="ANTHROPIC_API_KEY")
    anthropic_base_url: str = "https://api.anthropic.com/v1"

    # ── Groq ────────────────────────────────────────────────────────
    groq_api_key: Optional[str] = Field(default=None, alias="GROQ_API_KEY")
    groq_base_url: str = "https://api.groq.com/openai/v1"

    # ── DeepSeek ────────────────────────────────────────────────────
    deepseek_api_key: Optional[str] = Field(default=None, alias="DEEPSEEK_API_KEY")
    deepseek_base_url: str = "https://api.deepseek.com/v1"

    # ── Ollama (local) ──────────────────────────────────────────────
    ollama_base_url: str = Field(
        default="http://127.0.0.1:11434", alias="OLLAMA_BASE_URL"
    )
    ollama_enabled: bool = Field(default=True, alias="OLLAMA_ENABLED")
    ollama_default_model: str = Field(
        default=read_registry("ollama_default_model", "llama3.2"), alias="OLLAMA_DEFAULT_MODEL"
    )

    # ── Rate Limiting ───────────────────────────────────────────────
    rate_limit_enabled: bool = True
    rate_limit_requests: int = 100
    rate_limit_window: int = 60
    rate_limit_burst: int = 20

    # ── Usage Tracking ──────────────────────────────────────────────
    usage_tracking_enabled: bool = True
    usage_reset_daily: bool = True

    # ── Request ─────────────────────────────────────────────────────
    max_request_size: int = 10 * 1024 * 1024
    request_timeout: int = 120
    max_retries: int = 3
    retry_delay: float = 1.0

    # ── Cost limits ─────────────────────────────────────────────────
    max_daily_cost_usd: float = 100.0
    cost_alert_threshold: float = 80.0

    # ── Supabase ────────────────────────────────────────────────────
    supabase_url: str = Field(
        default="https://iprhzagvffgpfihrmeqd.supabase.co",
        alias="SUPABASE_URL",
    )
    supabase_key: str = Field(
        default="sb_publishable_DBnWTqXK0l2LhAVtYMenXg_2JhBx",
        alias="SUPABASE_KEY",
    )
    supabase_service_key: Optional[str] = Field(default=None, alias="SUPABASE_SERVICE_KEY")
    supabase_jwt_secret: Optional[str] = Field(default=None, alias="SUPABASE_JWT_SECRET")

    supabase_db_host: str = Field(
        default="aws-0-us-east-1.pooler.supabase.com",
        alias="SUPABASE_DB_HOST",
    )
    supabase_db_port: int = Field(default=6543, alias="SUPABASE_DB_PORT")
    supabase_db_database: str = Field(default="postgres", alias="SUPABASE_DB_DATABASE")
    supabase_db_user: str = Field(
        default="iprhzagvffgpfihrmeqd",
        alias="SUPABASE_DB_USER",
    )
    supabase_db_password: str = Field(default="", alias="SUPABASE_DB_PASSWORD")
    supabase_db_sslmode: str = Field(default="require", alias="SUPABASE_DB_SSLMODE")

    supabase_pool_min_size: int = Field(default=2, alias="SUPABASE_POOL_MIN")
    supabase_pool_max_size: int = Field(default=10, alias="SUPABASE_POOL_MAX")
    supabase_pool_max_queries: int = Field(default=50000, alias="SUPABASE_POOL_MAX_QUERIES")
    supabase_pool_max_inactive_seconds: int = Field(
        default=300, alias="SUPABASE_POOL_MAX_INACTIVE"
    )
    supabase_command_timeout: int = Field(default=30, alias="SUPABASE_COMMAND_TIMEOUT")
    supabase_retry_attempts: int = Field(default=3, alias="SUPABASE_RETRY_ATTEMPTS")
    supabase_retry_delay: float = Field(default=0.5, alias="SUPABASE_RETRY_DELAY")
    supabase_retry_max_delay: float = Field(default=10.0, alias="SUPABASE_RETRY_MAX_DELAY")

    supabase_cache_ttl_conversations: int = Field(
        default=600, alias="SUPABASE_CACHE_TTL_CONVERSATIONS"
    )
    supabase_cache_ttl_preferences: int = Field(
        default=3600, alias="SUPABASE_CACHE_TTL_PREFERENCES"
    )
    supabase_cache_ttl_settings: int = Field(
        default=3600, alias="SUPABASE_CACHE_TTL_SETTINGS"
    )
    supabase_cache_ttl_analytics: int = Field(
        default=300, alias="SUPABASE_CACHE_TTL_ANALYTICS"
    )

    @property
    def supabase_db_dsn(self) -> str:
        dsn = (
            f"postgresql://{self.supabase_db_user}:{self.supabase_db_password}"
            f"@{self.supabase_db_host}:{self.supabase_db_port}"
            f"/{self.supabase_db_database}"
        )
        if self.supabase_db_sslmode:
            dsn += f"?sslmode={self.supabase_db_sslmode}"
        return dsn

    @property
    def supabase_rest_url(self) -> str:
        return f"{self.supabase_url}/rest/v1"

    @property
    def supabase_storage_url(self) -> str:
        return f"{self.supabase_url}/storage/v1"

    # ── Hostname (cross-platform) ───────────────────────────────────
    hostname: str = Field(default_factory=get_hostname)

    @property
    def is_production(self) -> bool:
        return self.environment == Environment.PRODUCTION

    @property
    def is_development(self) -> bool:
        return self.environment == Environment.DEVELOPMENT

    @property
    def is_windows(self) -> bool:
        return IS_WINDOWS

    @property
    def redis_url(self) -> str:
        if self.redis_password:
            return f"redis://:{self.redis_password}@{self.redis_host}:{self.redis_port}/{self.redis_db}"
        return f"redis://{self.redis_host}:{self.redis_port}/{self.redis_db}"

    @property
    def local_models_enabled(self) -> bool:
        """Whether local Ollama models should be used."""
        return self.ollama_enabled

    @property
    def use_local_first(self) -> bool:
        return self.provider_strategy in (
            ProviderStrategy.LOCAL_FIRST,
            ProviderStrategy.LOCAL_ONLY,
        )

    @property
    def use_remote_first(self) -> bool:
        return self.provider_strategy in (
            ProviderStrategy.REMOTE_FIRST,
            ProviderStrategy.REMOTE_ONLY,
        )

    def get_effective_providers(self) -> List[str]:
        """Return the list of enabled providers based on strategy."""
        all_providers = ["openai", "anthropic", "groq", "deepseek", "ollama"]
        remote_providers = ["openai", "anthropic", "groq", "deepseek"]

        if self.provider_strategy == ProviderStrategy.LOCAL_ONLY:
            return [p for p in all_providers if p == "ollama"]
        if self.provider_strategy == ProviderStrategy.REMOTE_ONLY:
            return remote_providers
        if self.provider_strategy == ProviderStrategy.LOCAL_FIRST:
            return ["ollama"] + remote_providers
        if self.provider_strategy == ProviderStrategy.REMOTE_FIRST:
            return remote_providers + ["ollama"]

        return all_providers


settings = Settings()
