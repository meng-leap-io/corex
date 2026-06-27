from __future__ import annotations

import time
import uuid
from typing import Any, AsyncGenerator, Dict, List, Optional, Tuple

from structlog import get_logger

from app.core.config import settings
from app.core.exceptions import (
    InsufficientCreditsError,
    ModelNotFoundError,
    ProviderError,
    ProviderTimeoutError,
)
from app.models.request import ChatCompletionRequest
from app.models.response import ChatCompletionResponse, Choice, ChatMessage, Usage
from app.services.cache_manager import cache_manager
from app.services.rate_limiter import rate_limiter
from app.services.providers.base import BaseProvider
from app.services.providers.openai import OpenAIProvider
from app.services.providers.anthropic import AnthropicProvider
from app.services.providers.groq import GroqProvider
from app.services.providers.deepseek import DeepSeekProvider
from app.services.providers.ollama import OllamaProvider
from app.services.usage_tracker import usage_tracker
from app.services.token_optimizer import token_optimizer, count_tokens

logger = get_logger(__name__)

PROVIDER_MAP: Dict[str, str] = {
    "gpt-4o": "openai",
    "gpt-4o-mini": "openai",
    "gpt-4-turbo": "openai",
    "gpt-3.5-turbo": "openai",
    "claude-3-opus": "anthropic",
    "claude-3-sonnet": "anthropic",
    "claude-3-haiku": "anthropic",
    "claude-3-5-sonnet": "anthropic",
    "llama3-70b": "groq",
    "llama3-8b": "groq",
    "mixtral-8x7b": "groq",
    "gemma2-9b": "groq",
    "deepseek-chat": "deepseek",
    "deepseek-coder": "deepseek",
    # Ollama local models
    # (keys must not collide with remote models above)
    "llama3": "ollama",
    "llama3.2": "ollama",
    "llama3.1": "ollama",
    "codellama": "ollama",
    "mistral": "ollama",
    "mixtral": "ollama",
    "gemma2": "ollama",
    "phi3": "ollama",
    "neural-chat": "ollama",
    "qwen2": "ollama",
    "qwen2.5-coder": "ollama",
    "starling-lm": "ollama",
}

MODEL_ALIASES: Dict[str, str] = {
    "gpt-4o": "gpt-4o",
    "gpt-4o-mini": "gpt-4o-mini",
    "gpt-4": "gpt-4-turbo",
    "claude-3-opus": "claude-3-opus-20240229",
    "claude-3-sonnet": "claude-3-sonnet-20240229",
    "claude-3-haiku": "claude-3-haiku-20240307",
    "llama-70b": "llama3-70b-8192",
    "llama-8b": "llama3-8b-8192",
}


class AIRouter:
    def __init__(self):
        self._providers: Dict[str, BaseProvider] = {}
        self._initialize_providers()

    def _initialize_providers(self) -> None:
        providers = [
            OpenAIProvider(),
            AnthropicProvider(),
            GroqProvider(),
            DeepSeekProvider(),
        ]
        for p in providers:
            if p.api_key:
                self._providers[p.name] = p
                logger.info("provider_initialized", provider=p.name)

        # Ollama is always registered (no API key needed)
        effective = settings.get_effective_providers()
        if settings.ollama_enabled and "ollama" in effective:
            self._providers["ollama"] = OllamaProvider()
            logger.info("ollama_provider_initialized", base_url=settings.ollama_base_url)

    def get_provider_for_model(self, model: str) -> Tuple[BaseProvider, str]:
        resolved = MODEL_ALIASES.get(model, model)
        provider_name = None

        for prefix, pname in PROVIDER_MAP.items():
            if resolved.startswith(prefix):
                provider_name = pname
                break

        if not provider_name:
            provider_name = self._select_provider_by_model(resolved)

        if not provider_name or provider_name not in self._providers:
            available = ", ".join(self._providers.keys())
            raise ModelNotFoundError(
                f"Model '{model}' is not available. Available providers: {available}"
            )

        return self._providers[provider_name], resolved

    def _select_provider_by_model(self, model: str) -> Optional[str]:
        for pname, provider in self._providers.items():
            if any(m in model for m in provider.default_models):
                return pname
            provider_models = self._get_provider_model_prefixes(pname)
            if any(model.startswith(prefix) for prefix in provider_models):
                return pname
        return None

    def _get_provider_model_prefixes(self, provider_name: str) -> List[str]:
        mapping = {
            "openai": ["gpt-", "o1-", "text-embedding", "tts-", "whisper"],
            "anthropic": ["claude-"],
            "groq": ["llama", "mixtral", "gemma"],
            "deepseek": ["deepseek-"],
            "ollama": ["llama3", "llama3.", "codellama", "deepseek-coder", "mistral", "mixtral", "gemma2", "phi3", "neural-chat", "qwen2", "starling-lm"],
        }
        return mapping.get(provider_name, [])

    async def chat_completion(
        self,
        request: ChatCompletionRequest,
        user_id: Optional[str] = None,
        api_key: Optional[str] = None,
    ) -> ChatCompletionResponse:
        provider, resolved_model = self.get_provider_for_model(request.model)
        request.model = resolved_model

        cache_key = self._build_cache_key(request, user_id)
        cached = await cache_manager.get(cache_key, tier="short")
        if cached:
            logger.info("cache_hit", model=request.model, key=cache_key)
            return ChatCompletionResponse(**cached)

        rate_limit_key = user_id or api_key or "anonymous"
        await rate_limiter.check(rate_limit_key)

        if settings.usage_tracking_enabled:
            can_proceed = await usage_tracker.can_make_request(user_id)
            if not can_proceed:
                stats = await usage_tracker.get_user_stats(user_id)
                raise InsufficientCreditsError(
                    credits_available=stats.get("remaining_credits", 0),
                    credits_required=0,
                )

        messages_dict = [m.model_dump(exclude_none=True) for m in request.messages]
        input_tokens = sum(count_tokens(m.get("content", "")) for m in messages_dict)

        max_output = token_optimizer.context_optimizer.get_max_output_tokens(
            request.model, request.max_tokens
        )
        if input_tokens + max_output > token_optimizer.context_optimizer.get_context_limit(request.model):
            messages_dict = token_optimizer.optimize_messages(
                messages_dict, request.model, max_output
            )
            from app.models.request import Message
            request.messages = [Message(**m) for m in messages_dict]
            logger.info("context_optimized", model=request.model, original_tokens=input_tokens)

        request.max_tokens = min(max_output, request.max_tokens or max_output)

        start_time = time.time()
        try:
            response_data = await provider.chat_completion(request)
        except (ProviderError, ProviderTimeoutError) as e:
            fallback = await self._try_fallback(request, provider.name)
            if fallback:
                return fallback
            raise

        duration = time.time() - start_time
        result = self._normalize_response(response_data, request.model, provider.name)

        if result.usage:
            await cache_manager.set(cache_key, result.model_dump(), tier="short")
            await usage_tracker.track_usage(
                user_id=user_id,
                provider=provider.name,
                model=request.model,
                prompt_tokens=result.usage.prompt_tokens,
                completion_tokens=result.usage.completion_tokens,
                cost=provider.estimate_cost(
                    request.model,
                    result.usage.prompt_tokens,
                    result.usage.completion_tokens,
                ),
                duration=duration,
                endpoint="/v1/chat/completions",
                success=True,
            )

        logger.info(
            "chat_completion_success",
            model=request.model,
            provider=provider.name,
            tokens=result.usage.total_tokens if result.usage else 0,
            duration_ms=round(duration * 1000),
        )

        return result

    async def chat_completion_stream(
        self,
        request: ChatCompletionRequest,
        user_id: Optional[str] = None,
        api_key: Optional[str] = None,
    ) -> AsyncGenerator[str, None]:
        provider, resolved_model = self.get_provider_for_model(request.model)
        request.model = resolved_model

        rate_limit_key = user_id or api_key or "anonymous"
        await rate_limiter.check(rate_limit_key)

        start_time = time.time()
        total_prompt = 0
        total_completion = 0
        full_content = ""

        try:
            async for chunk in provider.chat_completion_stream(request):
                parsed = provider._parse_stream_chunk(chunk)
                if parsed:
                    yield f"data: {chunk.decode()}\n\n"
                    delta = parsed.get("choices", [{}])[0].get("delta", {})
                    full_content += delta.get("content", "")
            yield "data: [DONE]\n\n"
        except Exception as e:
            logger.error("stream_error", provider=provider.name, error=str(e))
            error_data = {
                "error": {"code": "stream_error", "message": str(e)[:200]}
            }
            import json
            yield f"data: {json.dumps(error_data)}\n\n"
            yield "data: [DONE]\n\n"
            raise

        duration = time.time() - start_time
        await usage_tracker.track_usage(
            user_id=user_id,
            provider=provider.name,
            model=request.model,
            prompt_tokens=total_prompt,
            completion_tokens=total_completion,
            cost=provider.estimate_cost(request.model, total_prompt, total_completion),
            duration=duration,
            endpoint="/v1/chat/completions",
            success=True,
        )

    async def embeddings(
        self,
        model: str,
        input: str | List[str],
        user_id: Optional[str] = None,
    ) -> Dict[str, Any]:
        import hashlib, json

        input_hash = hashlib.md5(
            json.dumps(input, sort_keys=True).encode()
        ).hexdigest()
        cache_key = f"embed:{model}:{input_hash}"
        cached = await cache_manager.get(cache_key, tier="day")
        if cached:
            return cached

        provider, resolved_model = self.get_provider_for_model(model or "text-embedding-3-small")

        if not hasattr(provider, "embeddings"):
            provider = self._providers.get("openai")
            if not provider:
                raise ModelNotFoundError("No provider available for embeddings.")

        result = await provider.embeddings(resolved_model, input)

        await cache_manager.set(cache_key, result, tier="day")

        await usage_tracker.track_usage(
            user_id=user_id,
            provider=provider.name,
            model=resolved_model,
            prompt_tokens=result.get("usage", {}).get("total_tokens", 0),
            completion_tokens=0,
            cost=0,
            duration=0,
            endpoint="/v1/embeddings",
            success=True,
        )
        return result

    async def list_models(self) -> List[Dict[str, Any]]:
        cached = await cache_manager.get("models:all", tier="long")
        if cached:
            return cached

        models = []
        for provider in self._providers.values():
            for model_id in provider.default_models:
                info = provider.get_model_info(model_id)
                if info:
                    models.append(info.model_dump())

        await cache_manager.set("models:all", models, tier="long")
        return models

    async def warm_caches(self) -> dict:
        warmed = {}

        models = await self.list_models()
        warmed["models"] = len(models)

        for provider in self._providers.values():
            try:
                provider_models = await provider.list_models()
                warmed[provider.name] = len(provider_models)
            except Exception:
                warmed[provider.name] = 0

        logger.info("caches_warmed", **warmed)
        return warmed

    async def _try_fallback(
        self,
        request: ChatCompletionRequest,
        failed_provider: str,
    ) -> Optional[ChatCompletionResponse]:
        fallback_providers = [p for p in self._providers if p != failed_provider]
        for provider_name in fallback_providers:
            provider = self._providers[provider_name]
            fallback_model = self._get_fallback_model(request.model, provider)
            if fallback_model:
                logger.info(
                    "fallback_attempt",
                    from_provider=failed_provider,
                    to_provider=provider_name,
                    model=fallback_model,
                )
                try:
                    request.model = fallback_model
                    data = await provider.chat_completion(request)
                    return self._normalize_response(data, fallback_model, provider.name)
                except Exception:
                    continue
        return None

    def _get_fallback_model(self, original_model: str, provider: BaseProvider) -> Optional[str]:
        if provider.default_models:
            return provider.default_models[0]
        return None

    def _normalize_response(
        self,
        data: Dict[str, Any],
        model: str,
        provider_name: str,
    ) -> ChatCompletionResponse:
        usage = Usage.from_provider(data.get("usage"))
        choices = []
        for c in data.get("choices", []):
            message_data = c.get("message", c.get("delta", {}))
            choices.append(Choice(
                index=c.get("index", 0),
                message=ChatMessage(
                    role=message_data.get("role", "assistant"),
                    content=message_data.get("content"),
                ),
                finish_reason=c.get("finish_reason"),
            ))

        return ChatCompletionResponse(
            id=data.get("id", f"chatcmpl-{uuid.uuid4().hex[:12]}"),
            created=data.get("created", int(time.time())),
            model=data.get("model", model),
            choices=choices,
            usage=usage,
            provider=provider_name,
        )

    def _build_cache_key(
        self,
        request: ChatCompletionRequest,
        user_id: Optional[str] = None,
    ) -> str:
        import hashlib, json
        raw = json.dumps(request.model_dump(), sort_keys=True)
        hash_val = hashlib.md5(raw.encode()).hexdigest()
        return f"chat:{user_id or 'anon'}:{hash_val}"

    async def chat_completion_raw(self, body: Dict[str, Any]) -> Dict[str, Any]:
        """Handle a raw JSON request (without Pydantic validation).

        Used by main.py when receiving proxied requests.
        Supports automatic fallback between remote and local providers.
        """
        model = body.get("model", "")
        messages = body.get("messages", [])
        stream = body.get("stream", False)

        if stream:
            raise NotImplementedError("Streaming via raw endpoint not yet supported")

        # Build a ChatCompletionRequest from the raw body
        from app.models.request import ChatCompletionRequest as CCR
        request = CCR(
            model=model,
            messages=[{"role": m.get("role", "user"), "content": m.get("content", "")} for m in messages],
            temperature=body.get("temperature", 0.7),
            top_p=body.get("top_p", 1.0),
            max_tokens=body.get("max_tokens", 4096),
            stream=False,
        )

        try:
            result = await self.chat_completion(request)
            return result.model_dump()
        except (ProviderError, ModelNotFoundError) as e:
            logger.warning("primary_provider_failed", model=model, error=str(e))

            # Try fallback: if remote fails, try local Ollama
            if model not in PROVIDER_MAP and "ollama" in self._providers:
                fallback_model = self._get_local_fallback(model)
                if fallback_model:
                    logger.info("fallback_to_ollama", original=model, fallback=fallback_model)
                    request.model = fallback_model
                    try:
                        result = await self._providers["ollama"].chat_completion(request)
                        return self._normalize_response(result, fallback_model, "ollama").model_dump()
                    except Exception as fallback_err:
                        logger.error("fallback_failed", error=str(fallback_err))

            raise

    def _get_local_fallback(self, model: str) -> Optional[str]:
        """Find a suitable local Ollama model as fallback."""
        ollama = self._providers.get("ollama")
        if not ollama:
            return None

        # Map remote model families to local alternatives
        local_map = {
            "gpt": "llama3.2",
            "claude": "llama3.1",
            "llama": "llama3.2",
            "mixtral": "mixtral",
            "deepseek": "deepseek-coder",
            "gemma": "gemma2",
        }

        for prefix, local_model in local_map.items():
            if model.lower().startswith(prefix):
                return local_model

        return settings.ollama_default_model if settings.ollama_enabled else None

    async def close(self) -> None:
        for provider in self._providers.values():
            await provider.close()


ai_router = AIRouter()
