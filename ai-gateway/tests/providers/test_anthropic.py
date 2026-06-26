"""Tests for Anthropic provider implementation."""

from __future__ import annotations

import pytest

from app.services.providers.anthropic import AnthropicProvider


@pytest.fixture
def provider():
    return AnthropicProvider()


@pytest.mark.asyncio
class TestAnthropicProvider:
    async def test_provider_name(self, provider: AnthropicProvider):
        assert provider.name == "anthropic"

    async def test_default_models(self, provider: AnthropicProvider):
        assert "claude-3-opus" in provider.default_models
        assert "claude-3-sonnet" in provider.default_models
        assert "claude-3-haiku" in provider.default_models
        assert "claude-3-5-sonnet" in provider.default_models

    async def test_supports_streaming(self, provider: AnthropicProvider):
        assert provider.supports_streaming is True

    async def test_api_key_env(self, provider: AnthropicProvider):
        assert provider.api_key_env == "ANTHROPIC_API_KEY"

    async def test_cost_per_token_opus(self, provider: AnthropicProvider):
        cost = provider.cost_per_token.get("claude-3-opus")
        assert cost is not None
        assert cost[0] > 0
        assert cost[1] > 0

    async def test_haiku_is_cheapest(self, provider: AnthropicProvider):
        opus = provider.cost_per_token["claude-3-opus"]
        haiku = provider.cost_per_token["claude-3-haiku"]
        assert haiku[0] < opus[0]
        assert haiku[1] < opus[1]

    async def test_estimate_cost_returns_positive(self, provider: AnthropicProvider):
        cost = provider.estimate_cost("claude-3-sonnet", 500, 200)
        assert cost > 0
