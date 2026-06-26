from __future__ import annotations

from typing import Any, Dict, List, Optional

from structlog import get_logger

from app.models.request import ChatCompletionRequest
from app.models.response import ModelInfo
from app.services.providers.base import BaseProvider

logger = get_logger(__name__)


class AnthropicProvider(BaseProvider):
    name = "anthropic"
    base_url = "https://api.anthropic.com/v1"
    api_key_env = "ANTHROPIC_API_KEY"
    default_models = [
        "claude-3-opus-20240229",
        "claude-3-sonnet-20240229",
        "claude-3-haiku-20240307",
        "claude-3-5-sonnet-20240620",
    ]

    PRICING = {
        "claude-3-opus-20240229": {"input": 15.00, "output": 75.00},
        "claude-3-sonnet-20240229": {"input": 3.00, "output": 15.00},
        "claude-3-haiku-20240307": {"input": 0.25, "output": 1.25},
        "claude-3-5-sonnet-20240620": {"input": 3.00, "output": 15.00},
    }

    def _get_api_key(self) -> str:
        from app.core.config import settings
        return settings.anthropic_api_key or ""

    def _get_headers(self) -> Dict[str, str]:
        return {
            "x-api-key": self.api_key,
            "anthropic-version": "2023-06-01",
            "Content-Type": "application/json",
        }

    def _build_chat_body(self, request: ChatCompletionRequest) -> Dict[str, Any]:
        system = None
        messages = []
        for m in request.messages:
            if m.role.value == "system":
                system = m.content
            else:
                messages.append(m.model_dump(exclude_none=True))

        body = {
            "model": request.model,
            "messages": messages,
            "max_tokens": request.max_tokens or 4096,
            "temperature": request.temperature,
            "top_p": request.top_p,
        }
        if system:
            body["system"] = system
        if request.stop:
            body["stop_sequences"] = request.stop
        return body

    def _parse_chat_response(self, response: Dict[str, Any]) -> Dict[str, Any]:
        openai_style = {
            "id": response.get("id", ""),
            "object": "chat.completion",
            "created": 0,
            "model": response.get("model", ""),
            "choices": [
                {
                    "index": 0,
                    "message": {
                        "role": "assistant",
                        "content": response.get("content", [{}])[0].get("text", ""),
                    },
                    "finish_reason": response.get("stop_reason", "stop"),
                }
            ],
            "usage": {
                "prompt_tokens": response.get("usage", {}).get("input_tokens", 0),
                "completion_tokens": response.get("usage", {}).get("output_tokens", 0),
                "total_tokens": (
                    response.get("usage", {}).get("input_tokens", 0)
                    + response.get("usage", {}).get("output_tokens", 0)
                ),
            },
        }
        return openai_style

    def _parse_stream_chunk(self, chunk: bytes) -> Dict[str, Any]:
        import json
        data = json.loads(chunk.decode())
        if data.get("type") == "content_block_delta":
            return {
                "choices": [
                    {
                        "index": 0,
                        "delta": {
                            "content": data.get("delta", {}).get("text", ""),
                        },
                    }
                ]
            }
        return {"choices": [{"index": 0, "delta": {"content": ""}}]}

    def estimate_cost(
        self,
        model: str,
        prompt_tokens: int,
        completion_tokens: int,
    ) -> float:
        pricing = self.PRICING.get(model, {"input": 0, "output": 0})
        prompt_cost = (prompt_tokens / 1_000_000) * pricing["input"]
        completion_cost = (completion_tokens / 1_000_000) * pricing["output"]
        return round(prompt_cost + completion_cost, 6)

    def get_model_info(self, model_id: str) -> Optional[ModelInfo]:
        import time
        pricing = self.PRICING.get(model_id)
        return ModelInfo(
            id=model_id,
            created=int(time.time()),
            owned_by="anthropic",
            provider="anthropic",
            capabilities={
                "chat": True,
                "embeddings": False,
                "code_generation": True,
                "function_calling": False,
                "streaming": True,
                "vision": "opus" in model_id or "sonnet" in model_id,
            },
            context_length=200000,
            pricing=pricing,
        )
