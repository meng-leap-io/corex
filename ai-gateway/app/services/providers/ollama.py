"""Ollama provider for local AI model inference on Windows.

Connects to a local Ollama instance (typically http://127.0.0.1:11434)
and serves models like Llama, CodeLlama, Mistral, DeepSeek Coder, etc.
This provider enables fully offline AI operations.

Ollama install: https://ollama.com/download/windows
"""

from __future__ import annotations

import json
import os
import time
from typing import Any, AsyncGenerator, Dict, List, Optional

import httpx
from structlog import get_logger

from app.core.exceptions import ProviderError, ProviderTimeoutError
from app.models.request import ChatCompletionRequest
from app.services.providers.base import BaseProvider

logger = get_logger(__name__)


class OllamaProvider(BaseProvider):
    name = "ollama"
    base_url = "http://127.0.0.1:11434"
    api_key_env = ""  # Ollama does not require an API key
    default_models = [
        "llama3.2",
        "llama3.1",
        "llama3",
        "codellama",
        "deepseek-coder",
        "mistral",
        "mixtral",
        "gemma2",
        "phi3",
        "phi3:mini",
        "neural-chat",
        "starling-lm",
        "qwen2",
        "qwen2.5-coder",
    ]
    supports_streaming = True

    PRICING = {}  # Ollama is free (local)

    def __init__(self):
        if os.environ.get("OLLAMA_BASE_URL"):
            self.base_url = os.environ["OLLAMA_BASE_URL"].rstrip("/")
        super().__init__()

    def _get_api_key(self) -> str:
        return ""

    def _get_headers(self) -> Dict[str, str]:
        return {"Content-Type": "application/json"}

    def _build_chat_body(self, request: ChatCompletionRequest) -> Dict[str, Any]:
        messages = []
        for m in request.messages:
            msg = {"role": m.role.value, "content": m.content or ""}
            messages.append(msg)

        body = {
            "model": request.model,
            "messages": messages,
            "stream": request.stream or False,
        }

        if request.temperature is not None:
            body["temperature"] = request.temperature
        if request.top_p is not None:
            body["top_p"] = request.top_p
        if request.max_tokens is not None:
            body["max_tokens"] = request.max_tokens
        if request.stop:
            body["stop"] = request.stop

        return body

    def _parse_chat_response(self, response: Dict[str, Any]) -> Dict[str, Any]:
        return {
            "id": response.get("created_at", ""),
            "object": "chat.completion",
            "created": int(time.time()),
            "model": response.get("model", "unknown"),
            "choices": [
                {
                    "index": 0,
                    "message": {
                        "role": "assistant",
                        "content": response.get("message", {}).get("content", ""),
                    },
                    "finish_reason": response.get("done_reason", "stop"),
                }
            ],
            "usage": {
                "prompt_tokens": response.get("prompt_eval_count", 0),
                "completion_tokens": response.get("eval_count", 0),
                "total_tokens": (
                    response.get("prompt_eval_count", 0)
                    + response.get("eval_count", 0)
                ),
            },
        }

    def _parse_stream_chunk(self, chunk: bytes) -> Dict[str, Any]:
        try:
            data = json.loads(chunk.decode())
            content = data.get("message", {}).get("content", "")
            done = data.get("done", False)
            return {
                "choices": [
                    {
                        "index": 0,
                        "delta": {"content": content},
                        "finish_reason": "stop" if done else None,
                    }
                ]
            }
        except json.JSONDecodeError:
            return {"choices": [{"index": 0, "delta": {"content": ""}}]}

    async def list_models(self) -> List[Dict[str, Any]]:
        """List models available in the local Ollama instance."""
        try:
            response = await self._make_request(method="GET", endpoint="/api/tags")
            models = response.get("models", [])
            return [
                {
                    "id": m["name"],
                    "name": m["name"],
                    "provider": "ollama",
                    "local": True,
                    "size": m.get("size", 0),
                    "modified_at": m.get("modified_at", ""),
                }
                for m in models
            ]
        except ProviderError:
            return self._fallback_model_list()

    def _fallback_model_list(self) -> List[Dict[str, Any]]:
        """Return default model list when Ollama is unreachable."""
        return [
            {"id": m, "name": m, "provider": "ollama", "local": True, "size": 0}
            for m in self.default_models
        ]

    async def pull_model(self, model_name: str) -> Dict[str, Any]:
        """Pull a model from Ollama's registry."""
        try:
            async with self.client.stream(
                "POST",
                f"{self.base_url}/api/pull",
                json={"name": model_name},
                timeout=httpx.Timeout(300.0),
            ) as response:
                response.raise_for_status()
                last_status = {}
                async for line in response.aiter_lines():
                    if line.strip():
                        try:
                            last_status = json.loads(line)
                        except json.JSONDecodeError:
                            pass
                return last_status
        except httpx.TimeoutException:
            raise ProviderError(
                provider=self.name,
                detail=f"Timed out pulling model '{model_name}'",
            )
        except Exception as e:
            raise ProviderError(
                provider=self.name,
                detail=f"Failed to pull model '{model_name}': {e}",
            )

    async def generate_embeddings(
        self, model: str, input_text: str
    ) -> Dict[str, Any]:
        """Generate embeddings using a local Ollama model."""
        try:
            response = await self._make_request(
                "POST",
                "/api/embeddings",
                json={"model": model, "prompt": input_text},
            )
            return {
                "object": "list",
                "data": [
                    {
                        "object": "embedding",
                        "index": 0,
                        "embedding": response.get("embedding", []),
                    }
                ],
                "model": model,
                "usage": {"prompt_tokens": 0, "total_tokens": 0},
            }
        except Exception as e:
            raise ProviderError(
                provider=self.name,
                detail=f"Embedding generation failed: {e}",
            )

    async def check_health(self) -> bool:
        """Check if Ollama is running and accessible."""
        try:
            response = await self._make_request("GET", "/api/version")
            return bool(response.get("version"))
        except (ProviderError, ProviderTimeoutError):
            return False

    async def get_running_models(self) -> List[str]:
        """Get currently loaded (running) models."""
        try:
            response = await self._make_request("GET", "/api/ps")
            return [m["name"] for m in response.get("models", [])]
        except ProviderError:
            return []

    def estimate_cost(
        self,
        model: str,
        prompt_tokens: int,
        completion_tokens: int,
    ) -> float:
        return 0.0

    def get_model_info(self, model_id: str):
        from app.models.response import ModelInfo
        return ModelInfo(
            id=model_id,
            created=0,
            owned_by="ollama",
            provider="ollama",
            capabilities={
                "chat": True,
                "embeddings": True,
                "code_generation": True,
                "function_calling": "llama" in model_id or "mistral" in model_id,
                "streaming": True,
                "vision": "llava" in model_id or "bakllava" in model_id,
            },
            context_length=8192 if "3.2" in model_id else 4096,
            pricing=None,
        )
