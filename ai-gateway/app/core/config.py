from __future__ import annotations

from enum import Enum
from pathlib import Path
from typing import List, Optional

from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict


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


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
    )

    # Application
    app_name: str = "Corex AI Gateway"
    app_version: str = "1.0.0"
    environment: Environment = Environment.PRODUCTION
    debug: bool = False
    log_level: LogLevel = LogLevel.INFO
    sentry_dsn: Optional[str] = None

    # Server
    host: str = Field(default="0.0.0.0", alias="HOST")
    port: int = Field(default=8000, alias="PORT")
    workers: int = 4
    cors_origins: List[str] = [
        "https://corex.dev",
        "https://console.corex.dev",
        "http://localhost:3000",
        "http://localhost:8000",
    ]

    # Redis
    redis_host: str = "redis"
    redis_port: int = 6379
    redis_password: Optional[str] = None
    redis_db: int = 0
    redis_cache_ttl: int = 300

    # Auth / JWT
    jwt_secret: str = Field(default="", alias="JWT_SECRET")
    jwt_algorithm: str = "HS256"
    jwt_expiration: int = 3600

    # OpenAI
    openai_api_key: Optional[str] = Field(default=None, alias="OPENAI_API_KEY")
    openai_organization: Optional[str] = None
    openai_base_url: str = "https://api.openai.com/v1"

    # Anthropic
    anthropic_api_key: Optional[str] = Field(default=None, alias="ANTHROPIC_API_KEY")
    anthropic_base_url: str = "https://api.anthropic.com/v1"

    # Groq
    groq_api_key: Optional[str] = Field(default=None, alias="GROQ_API_KEY")
    groq_base_url: str = "https://api.groq.com/openai/v1"

    # DeepSeek
    deepseek_api_key: Optional[str] = Field(default=None, alias="DEEPSEEK_API_KEY")
    deepseek_base_url: str = "https://api.deepseek.com/v1"

    # Rate Limiting
    rate_limit_enabled: bool = True
    rate_limit_requests: int = 100
    rate_limit_window: int = 60
    rate_limit_burst: int = 20

    # Usage Tracking
    usage_tracking_enabled: bool = True
    usage_reset_daily: bool = True

    # Request
    max_request_size: int = 10 * 1024 * 1024
    request_timeout: int = 120
    max_retries: int = 3
    retry_delay: float = 1.0

    # Cost limits
    max_daily_cost_usd: float = 100.0
    cost_alert_threshold: float = 80.0

    @property
    def is_production(self) -> bool:
        return self.environment == Environment.PRODUCTION

    @property
    def is_development(self) -> bool:
        return self.environment == Environment.DEVELOPMENT

    @property
    def redis_url(self) -> str:
        if self.redis_password:
            return f"redis://:{self.redis_password}@{self.redis_host}:{self.redis_port}/{self.redis_db}"
        return f"redis://{self.redis_host}:{self.redis_port}/{self.redis_db}"


settings = Settings()
