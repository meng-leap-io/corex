"""Tests for the AI router service."""

from __future__ import annotations

from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.services.ai_router import AIRouter, PROVIDER_MAP, MODEL_ALIASES


@pytest.fixture
def router():
    return AIRouter()


@pytest.mark.asyncio
class TestAIRouter:
    async def test_model_map_contains_all_models(self, router: AIRouter):
        for model in PROVIDER_MAP:
            router.model_for_model(model) is not None

    async def test_known_models_list(self, router: AIRouter):
        models = router.list_models()
        assert len(models) > 0
        assert "gpt-4o" in [m["id"] for m in models]

    async def test_provider_for_gpt4o(self, router: AIRouter):
        provider = router.get_provider_for_model("gpt-4o")
        assert provider is not None
        assert provider.name == "openai"

    async def test_provider_for_claude(self, router: AIRouter):
        provider = router.get_provider_for_model("claude-3-sonnet")
        assert provider is not None
        assert provider.name == "anthropic"

    async def test_provider_for_deepseek(self, router: AIRouter):
        provider = router.get_provider_for_model("deepseek-chat")
        assert provider is not None
        assert provider.name == "deepseek"

    async def test_unknown_model_returns_none(self, router: AIRouter):
        provider = router.get_provider_for_model("nonexistent-model")
        assert provider is None

    async def test_model_alias_resolution(self):
        assert MODEL_ALIASES["gpt-4"] == "gpt-4-turbo"

    async def test_models_have_metadata(self, router: AIRouter):
        models = router.list_models()
        for model in models:
            assert "id" in model
            assert "provider" in model
            assert "pricing" in model

    async def test_fallback_provider(self, router: AIRouter):
        with patch.object(router, "_providers", {}):
            provider = router.get_provider_for_model("gpt-4o")
            assert provider is None

    async def test_cache_key_generation(self, router: AIRouter):
        key = router._cache_key("gpt-4o", "Hello")
        assert "gpt-4o" in key
        assert isinstance(key, str)

    async def test_embeddings_model_map(self, router: AIRouter):
        provider = router.get_provider_for_model("text-embedding-3-small")
        assert provider is not None

    async def test_model_info_includes_pricing(self, router: AIRouter):
        for model in router.list_models():
            if model["id"] == "gpt-4o":
                assert model["pricing"]["input"] > 0
                assert model["pricing"]["output"] > 0
