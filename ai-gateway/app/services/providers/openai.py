from __future__ import annotations

from typing import Any, Dict, List, Optional

from structlog import get_logger

from app.models.request import ChatCompletionRequest
from app.models.response import ModelInfo
from app.services.providers.base import BaseProvider

logger = get_logger(__name__)


class OpenAIProvider(BaseProvider):
    name = "openai"
    base_url = "https://api.openai.com/v1"
    api_key_env = "OPENAI_API_KEY"
    default_models = [
        "gpt-4o",
        "gpt-4o-mini",
        "gpt-4-turbo",
        "gpt-4",
        "gpt-3.5-turbo",
        "o1-mini",
        "o1-preview",
    ]

    PRICING = {
        "gpt-4o": {"input": 2.50, "output": 10.00},
        "gpt-4o-mini": {"input": 0.15, "output": 0.60},
        "gpt-4-turbo": {"input": 10.00, "output": 30.00},
        "gpt-4": {"input": 30.00, "output": 60.00},
        "gpt-3.5-turbo": {"input": 0.50, "output": 1.50},
    }

    def _get_api_key(self) -> str:
        from app.core.config import settings
        return settings.openai_api_key or ""

    def _get_headers(self) -> Dict[str, str]:
        headers = {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json",
        }
        from app.core.config import settings
        if settings.openai_organization:
            headers["OpenAI-Organization"] = settings.openai_organization
        return headers

    def _build_chat_body(self, request: ChatCompletionRequest) -> Dict[str, Any]:
        body = {
            "model": request.model,
            "messages": [m.model_dump(exclude_none=True) for m in request.messages],
            "temperature": request.temperature,
            "top_p": request.top_p,
            "n": request.n,
            "max_tokens": request.max_tokens,
            "presence_penalty": request.presence_penalty,
            "frequency_penalty": request.frequency_penalty,
        }
        if request.stop:
            body["stop"] = request.stop
        if request.user:
            body["user"] = request.user
        if request.tools:
            body["tools"] = [t.model_dump(exclude_none=True) for t in request.tools]
        if request.tool_choice:
            body["tool_choice"] = request.tool_choice
        if request.response_format and request.response_format.type != "text":
            body["response_format"] = request.response_format.model_dump()
        if request.seed is not None:
            body["seed"] = request.seed
        return body

    def _parse_chat_response(self, response: Dict[str, Any]) -> Dict[str, Any]:
        return response

    def _parse_stream_chunk(self, chunk: bytes) -> Dict[str, Any]:
        import json
        return json.loads(chunk.decode())

    async def embeddings(
        self,
        model: str,
        input: str | List[str],
    ) -> Dict[str, Any]:
        body = {
            "model": model or "text-embedding-3-small",
            "input": input,
        }
        return await self._make_request(
            method="POST",
            endpoint="/embeddings",
            json=body,
        )

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
            owned_by="openai",
            provider="openai",
            capabilities={
                "chat": True,
                "embeddings": model_id.startswith("text-embedding"),
                "code_generation": True,
                "function_calling": "gpt-4" in model_id or "gpt-3.5" in model_id,
                "streaming": True,
                "vision": "gpt-4" in model_id and "mini" not in model_id,
            },
            context_length=128000 if "gpt-4" in model_id else 16385,
            pricing=pricing,
        )
