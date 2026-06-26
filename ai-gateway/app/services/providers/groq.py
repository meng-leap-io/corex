from __future__ import annotations

from typing import Any, Dict, List, Optional

from structlog import get_logger

from app.models.request import ChatCompletionRequest
from app.models.response import ModelInfo
from app.services.providers.base import BaseProvider

logger = get_logger(__name__)


class GroqProvider(BaseProvider):
    name = "groq"
    base_url = "https://api.groq.com/openai/v1"
    api_key_env = "GROQ_API_KEY"
    default_models = [
        "llama3-70b-8192",
        "llama3-8b-8192",
        "mixtral-8x7b-32768",
        "gemma2-9b-it",
    ]

    PRICING = {
        "llama3-70b-8192": {"input": 0.59, "output": 0.79},
        "llama3-8b-8192": {"input": 0.05, "output": 0.08},
        "mixtral-8x7b-32768": {"input": 0.24, "output": 0.24},
        "gemma2-9b-it": {"input": 0.05, "output": 0.10},
    }

    def _get_api_key(self) -> str:
        from app.core.config import settings
        return settings.groq_api_key or ""

    def _get_headers(self) -> Dict[str, str]:
        return {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json",
        }

    def _build_chat_body(self, request: ChatCompletionRequest) -> Dict[str, Any]:
        body = {
            "model": request.model,
            "messages": [m.model_dump(exclude_none=True) for m in request.messages],
            "temperature": request.temperature,
            "top_p": request.top_p,
            "max_tokens": request.max_tokens,
        }
        if request.stop:
            body["stop"] = request.stop
        return body

    def _parse_chat_response(self, response: Dict[str, Any]) -> Dict[str, Any]:
        return response

    def _parse_stream_chunk(self, chunk: bytes) -> Dict[str, Any]:
        import json
        return json.loads(chunk.decode())

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
            owned_by="groq",
            provider="groq",
            capabilities={
                "chat": True,
                "embeddings": False,
                "code_generation": True,
                "function_calling": "llama" in model_id,
                "streaming": True,
                "vision": False,
            },
            context_length=int(model_id.split("-")[-1]) if model_id.split("-")[-1].isdigit() else 8192,
            pricing=pricing,
        )
