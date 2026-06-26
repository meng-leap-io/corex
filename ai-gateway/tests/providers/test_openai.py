"""Tests for OpenAI provider implementation."""

from __future__ import annotations

from unittest.mock import AsyncMock, patch

import httpx
import pytest

from app.services.providers.openai import OpenAIProvider


@pytest.fixture
def provider():
    return OpenAIProvider()


@pytest.mark.asyncio
class TestOpenAIProvider:
    async def test_provider_name(self, provider: OpenAIProvider):
        assert provider.name == "openai"

    async def test_default_models(self, provider: OpenAIProvider):
        assert "gpt-4o" in provider.default_models
        assert "gpt-4o-mini" in provider.default_models
        assert "gpt-4-turbo" in provider.default_models

    async def test_supports_streaming(self, provider: OpenAIProvider):
        assert provider.supports_streaming is True

    async def test_base_url_default(self, provider: OpenAIProvider):
        assert "api.openai.com" in provider.base_url

    async def test_api_key_env(self, provider: OpenAIProvider):
        assert provider.api_key_env == "OPENAI_API_KEY"

    async def test_cost_per_token_gpt4o(self, provider: OpenAIProvider):
        cost = provider.cost_per_token.get("gpt-4o")
        assert cost is not None
        input_cost, output_cost = cost
        assert input_cost > 0
        assert output_cost > 0
        assert output_cost > input_cost

    async def test_cost_per_token_gpt4o_mini(self, provider: OpenAIProvider):
        cost = provider.cost_per_token.get("gpt-4o-mini")
        assert cost is not None
        input_cost, output_cost = cost
        assert input_cost > 0
        assert input_cost < provider.cost_per_token["gpt-4o"][0]

    async def test_estimate_cost_returns_zero_for_unknown_model(self, provider: OpenAIProvider):
        cost = provider.estimate_cost("unknown-model", 100, 50)
        assert cost == 0.0

    async def test_estimate_cost_returns_positive(self, provider: OpenAIProvider):
        cost = provider.estimate_cost("gpt-4o", 1000, 500)
        assert cost > 0
        assert isinstance(cost, float)

    async def test_format_request(self, provider: OpenAIProvider):
        request = {
            "model": "gpt-4o",
            "messages": [{"role": "user", "content": "Hi"}],
            "temperature": 0.7,
        }
        formatted = provider.format_request(request)
        assert formatted["model"] == "gpt-4o"
        assert "messages" in formatted

    async def test_format_response(self, provider: OpenAIProvider):
        raw = {
            "id": "test-id",
            "choices": [
                {
                    "index": 0,
                    "message": {
                        "role": "assistant",
                        "content": "Hello!",
                    },
                    "finish_reason": "stop",
                }
            ],
            "usage": {"prompt_tokens": 10, "completion_tokens": 5, "total_tokens": 15},
        }
        formatted = provider.format_response(raw)
        assert formatted["content"] == "Hello!"
        assert formatted["model"] == "gpt-4o"
        assert formatted["usage"]["total_tokens"] == 15
