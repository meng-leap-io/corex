from __future__ import annotations

from datetime import datetime
from enum import Enum
from typing import Any, Dict, List, Optional

from pydantic import BaseModel, ConfigDict, Field, field_validator


class Role(str, Enum):
    SYSTEM = "system"
    USER = "user"
    ASSISTANT = "assistant"
    TOOL = "tool"
    FUNCTION = "function"


class Message(BaseModel):
    role: Role
    content: Optional[str] = None
    name: Optional[str] = None
    tool_calls: Optional[List[Dict[str, Any]]] = None
    tool_call_id: Optional[str] = None

    @field_validator("content")
    @classmethod
    def content_or_tool_calls(cls, v, info):
        role = info.data.get("role")
        tool_calls = info.data.get("tool_calls")
        if role in (Role.ASSISTANT,) and not v and not tool_calls:
            raise ValueError("Assistant messages must have content or tool_calls.")
        return v


class FunctionCall(BaseModel):
    name: str
    arguments: str


class ToolCall(BaseModel):
    id: str
    type: str = "function"
    function: FunctionCall


class ToolDefinition(BaseModel):
    type: str = "function"
    function: Dict[str, Any]


class ResponseFormat(BaseModel):
    type: str = "text"


class StreamOptions(BaseModel):
    include_usage: bool = False


class ChatCompletionRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    model: str = Field(..., description="Model ID to use")
    messages: List[Message] = Field(..., min_length=1, description="Conversation messages")
    temperature: Optional[float] = Field(default=0.7, ge=0, le=2)
    top_p: Optional[float] = Field(default=1.0, ge=0, le=1)
    n: Optional[int] = Field(default=1, ge=1, le=10)
    stream: Optional[bool] = False
    stream_options: Optional[StreamOptions] = None
    stop: Optional[List[str]] = None
    max_tokens: Optional[int] = Field(default=4096, ge=1)
    presence_penalty: Optional[float] = Field(default=0, ge=-2, le=2)
    frequency_penalty: Optional[float] = Field(default=0, ge=-2, le=2)
    logit_bias: Optional[Dict[str, float]] = None
    user: Optional[str] = None
    tools: Optional[List[ToolDefinition]] = None
    tool_choice: Optional[str] = None
    response_format: Optional[ResponseFormat] = None
    seed: Optional[int] = None

    @field_validator("model")
    @classmethod
    def validate_model(cls, v: str) -> str:
        allowed_prefixes = ("gpt-", "claude-", "gemini-", "mixtral", "llama", "deepseek", "command-")
        if not any(v.startswith(p) for p in allowed_prefixes):
            pass
        return v


class EmbeddingRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    model: str = Field(..., description="Embedding model ID")
    input: str | List[str] = Field(..., description="Text to embed")
    user: Optional[str] = None
    encoding_format: Optional[str] = "float"


class CodeRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    model: Optional[str] = None
    prompt: str = Field(..., description="Code prompt")
    language: Optional[str] = None
    context: Optional[str] = None
    temperature: Optional[float] = 0.2
    max_tokens: Optional[int] = 4096
    stream: Optional[bool] = False


class DebugRequest(CodeRequest):
    code: str = Field(..., description="Code to debug")
    error_message: Optional[str] = None


class RefactorRequest(CodeRequest):
    code: str = Field(..., description="Code to refactor")
    instructions: Optional[str] = None


class ExplainRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")

    code: str = Field(..., description="Code to explain")
    language: Optional[str] = None
    detail_level: Optional[str] = "detailed"
