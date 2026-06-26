from __future__ import annotations

from datetime import datetime
from typing import Any, Dict, List, Optional

from pydantic import BaseModel, ConfigDict, Field


class ErrorDetail(BaseModel):
    code: str
    message: str
    details: Optional[Dict[str, Any]] = None


class ErrorResponse(BaseModel):
    error: ErrorDetail


class Usage(BaseModel):
    prompt_tokens: int = 0
    completion_tokens: int = 0
    total_tokens: int = 0

    @classmethod
    def from_provider(cls, data: Optional[Dict[str, Any]] = None) -> Usage:
        if not data:
            return cls()
        return cls(
            prompt_tokens=data.get("prompt_tokens", 0),
            completion_tokens=data.get("completion_tokens", 0),
            total_tokens=data.get("total_tokens", 0),
        )


class ChatMessage(BaseModel):
    role: str
    content: Optional[str] = None


class Choice(BaseModel):
    index: int = 0
    message: ChatMessage
    finish_reason: Optional[str] = None
    logprobs: Optional[Dict[str, Any]] = None


class ChatCompletionResponse(BaseModel):
    id: str
    object: str = "chat.completion"
    created: int
    model: str
    choices: List[Choice]
    usage: Optional[Usage] = None
    provider: Optional[str] = None
    cached: bool = False


class StreamChunk(BaseModel):
    id: str
    object: str = "chat.completion.chunk"
    created: int
    model: str
    choices: List[Dict[str, Any]]
    usage: Optional[Usage] = None


class EmbeddingData(BaseModel):
    index: int
    object: str = "embedding"
    embedding: List[float]


class EmbeddingResponse(BaseModel):
    object: str = "list"
    data: List[EmbeddingData]
    model: str
    usage: Usage


class ModelCapabilities(BaseModel):
    chat: bool = True
    embeddings: bool = False
    code_generation: bool = True
    function_calling: bool = False
    streaming: bool = True
    vision: bool = False


class ModelInfo(BaseModel):
    id: str
    object: str = "model"
    created: int
    owned_by: str
    provider: str
    capabilities: ModelCapabilities
    context_length: int = 8192
    pricing: Optional[Dict[str, float]] = None


class ModelListResponse(BaseModel):
    object: str = "list"
    data: List[ModelInfo]


class CodeResponse(BaseModel):
    code: str
    language: Optional[str] = None
    explanation: Optional[str] = None
    model: str
    usage: Usage
    provider: str


class UsageStats(BaseModel):
    total_requests: int = 0
    total_prompt_tokens: int = 0
    total_completion_tokens: int = 0
    total_tokens: int = 0
    total_cost: float = 0.0
    by_provider: Dict[str, Dict[str, Any]] = {}
    by_model: Dict[str, Dict[str, Any]] = {}
    since: Optional[str] = None
    until: Optional[str] = None


class HealthResponse(BaseModel):
    status: str = "ok"
    service: str = "ai-gateway"
    version: str = "1.0.0"
    timestamp: str = ""
    uptime: float = 0.0
    redis: bool = False
    providers: Dict[str, bool] = {}
