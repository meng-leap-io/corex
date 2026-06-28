"""Pydantic models for Supabase database entities.

All models use UUID primary keys, timezone-aware timestamps, and
optional jsonb metadata fields to match the Laravel backend schema.
"""

from __future__ import annotations

from datetime import datetime
from decimal import Decimal
from enum import Enum
from typing import Any, Optional
from uuid import UUID, uuid4

from pydantic import BaseModel, Field


# ── Enums ─────────────────────────────────────────────────────────────

class ConversationStatus(str, Enum):
    ACTIVE = "active"
    ARCHIVED = "archived"
    DELETED = "deleted"


class MessageRole(str, Enum):
    USER = "user"
    ASSISTANT = "assistant"
    SYSTEM = "system"
    TOOL = "tool"


class ModelProvider(str, Enum):
    OPENAI = "openai"
    ANTHROPIC = "anthropic"
    GROQ = "groq"
    DEEPSEEK = "deepseek"
    OLLAMA = "ollama"


class PreferenceCategory(str, Enum):
    UI = "ui"
    THEME = "theme"
    LANGUAGE = "language"
    NOTIFICATIONS = "notifications"
    ACCESSIBILITY = "accessibility"
    PRIVACY = "privacy"


class AnalyticsEventType(str, Enum):
    CHAT_COMPLETION = "chat_completion"
    EMBEDDING = "embedding"
    AGENT_EXECUTION = "agent_execution"
    MODEL_DOWNLOAD = "model_download"
    API_CALL = "api_call"
    ERROR = "error"
    USAGE = "usage"


# ── Conversations ─────────────────────────────────────────────────────


class ConversationCreate(BaseModel):
    user_id: str = Field(..., min_length=1, max_length=36)
    title: str = Field(default="", max_length=255)
    model: str = Field(default="gpt-4o-mini", max_length=100)
    provider: ModelProvider = Field(default=ModelProvider.OPENAI)
    system_prompt: Optional[str] = None
    metadata: dict[str, Any] = Field(default_factory=dict)


class ConversationUpdate(BaseModel):
    title: Optional[str] = None
    model: Optional[str] = None
    provider: Optional[ModelProvider] = None
    system_prompt: Optional[str] = None
    status: Optional[ConversationStatus] = None
    metadata: Optional[dict[str, Any]] = None


class Conversation(BaseModel):
    id: str = Field(default_factory=lambda: str(uuid4()))
    user_id: str
    title: str = ""
    model: str = "gpt-4o-mini"
    provider: ModelProvider = ModelProvider.OPENAI
    system_prompt: Optional[str] = None
    status: ConversationStatus = ConversationStatus.ACTIVE
    token_count: int = 0
    message_count: int = 0
    total_cost_usd: Decimal = Field(default=Decimal("0.0000"), max_digits=12, decimal_places=6)
    metadata: dict[str, Any] = Field(default_factory=dict)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)
    deleted_at: Optional[datetime] = None


class MessageCreate(BaseModel):
    conversation_id: str
    role: MessageRole
    content: str
    model: Optional[str] = None
    provider: Optional[ModelProvider] = None
    tokens_prompt: int = 0
    tokens_completion: int = 0
    cost_usd: Decimal = Field(default=Decimal("0.000000"), max_digits=12, decimal_places=8)
    latency_ms: Optional[float] = None
    metadata: dict[str, Any] = Field(default_factory=dict)


class Message(BaseModel):
    id: str = Field(default_factory=lambda: str(uuid4()))
    conversation_id: str
    role: MessageRole
    content: str
    model: Optional[str] = None
    provider: Optional[ModelProvider] = None
    tokens_prompt: int = 0
    tokens_completion: int = 0
    cost_usd: Decimal = Field(default=Decimal("0.000000"), max_digits=12, decimal_places=8)
    latency_ms: Optional[float] = None
    metadata: dict[str, Any] = Field(default_factory=dict)
    created_at: datetime = Field(default_factory=datetime.utcnow)


# ── User Preferences ──────────────────────────────────────────────────


class PreferenceCreate(BaseModel):
    user_id: str
    category: PreferenceCategory
    key: str = Field(..., max_length=255)
    value: Any
    metadata: dict[str, Any] = Field(default_factory=dict)


class PreferenceUpdate(BaseModel):
    value: Any
    metadata: Optional[dict[str, Any]] = None


class Preference(BaseModel):
    id: str = Field(default_factory=lambda: str(uuid4()))
    user_id: str
    category: PreferenceCategory
    key: str
    value: Any
    metadata: dict[str, Any] = Field(default_factory=dict)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)


# ── AI Model Settings ─────────────────────────────────────────────────


class ModelSettingsCreate(BaseModel):
    user_id: str
    model: str = Field(..., max_length=100)
    provider: ModelProvider
    temperature: float = Field(default=0.7, ge=0.0, le=2.0)
    max_tokens: int = Field(default=4096, ge=1, le=128000)
    top_p: float = Field(default=1.0, ge=0.0, le=1.0)
    frequency_penalty: float = Field(default=0.0, ge=-2.0, le=2.0)
    presence_penalty: float = Field(default=0.0, ge=-2.0, le=2.0)
    stop_sequences: list[str] = Field(default_factory=list)
    system_prompt: Optional[str] = None
    enabled: bool = True
    metadata: dict[str, Any] = Field(default_factory=dict)


class ModelSettingsUpdate(BaseModel):
    temperature: Optional[float] = None
    max_tokens: Optional[int] = None
    top_p: Optional[float] = None
    frequency_penalty: Optional[float] = None
    presence_penalty: Optional[float] = None
    stop_sequences: Optional[list[str]] = None
    system_prompt: Optional[str] = None
    enabled: Optional[bool] = None
    metadata: Optional[dict[str, Any]] = None


class ModelSettings(BaseModel):
    id: str = Field(default_factory=lambda: str(uuid4()))
    user_id: str
    model: str
    provider: ModelProvider
    temperature: float = 0.7
    max_tokens: int = 4096
    top_p: float = 1.0
    frequency_penalty: float = 0.0
    presence_penalty: float = 0.0
    stop_sequences: list[str] = Field(default_factory=list)
    system_prompt: Optional[str] = None
    enabled: bool = True
    metadata: dict[str, Any] = Field(default_factory=dict)
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)


# ── Analytics ─────────────────────────────────────────────────────────


class AnalyticsEventCreate(BaseModel):
    user_id: str
    event_type: AnalyticsEventType
    model: Optional[str] = None
    provider: Optional[ModelProvider] = None
    tokens_prompt: int = 0
    tokens_completion: int = 0
    cost_usd: Decimal = Field(default=Decimal("0.000000"), max_digits=12, decimal_places=8)
    latency_ms: Optional[float] = None
    status_code: Optional[int] = None
    error_message: Optional[str] = None
    metadata: dict[str, Any] = Field(default_factory=dict)


class AnalyticsEvent(BaseModel):
    id: str = Field(default_factory=lambda: str(uuid4()))
    user_id: str
    event_type: AnalyticsEventType
    model: Optional[str] = None
    provider: Optional[ModelProvider] = None
    tokens_prompt: int = 0
    tokens_completion: int = 0
    cost_usd: Decimal = Field(default=Decimal("0.000000"), max_digits=12, decimal_places=8)
    latency_ms: Optional[float] = None
    status_code: Optional[int] = None
    error_message: Optional[str] = None
    metadata: dict[str, Any] = Field(default_factory=dict)
    created_at: datetime = Field(default_factory=datetime.utcnow)


# ── Aggregated Analytics ──────────────────────────────────────────────


class DailyUsage(BaseModel):
    date: str
    user_id: str
    total_requests: int = 0
    total_tokens_prompt: int = 0
    total_tokens_completion: int = 0
    total_cost_usd: Decimal = Field(default=Decimal("0.0000"))
    request_count_by_model: dict[str, int] = Field(default_factory=dict)
    request_count_by_provider: dict[str, int] = Field(default_factory=dict)
    error_count: int = 0
    avg_latency_ms: float = 0.0


class UsageSummary(BaseModel):
    user_id: str
    total_requests: int = 0
    total_tokens: int = 0
    total_cost_usd: Decimal = Field(default=Decimal("0.0000"))
    requests_today: int = 0
    tokens_today: int = 0
    cost_today_usd: Decimal = Field(default=Decimal("0.0000"))
    top_model: Optional[str] = None
    top_provider: Optional[ModelProvider] = None
    avg_latency_ms: float = 0.0
    error_rate: float = 0.0


# ── Paginated Response ────────────────────────────────────────────────


class PaginatedResponse(BaseModel):
    data: list[Any]
    total: int
    page: int
    per_page: int
    pages: int

    @classmethod
    def from_list(
        cls, items: list, total: int, page: int, per_page: int,
    ) -> PaginatedResponse:
        return cls(
            data=items,
            total=total,
            page=page,
            per_page=per_page,
            pages=max(1, (total + per_page - 1) // per_page),
        )
