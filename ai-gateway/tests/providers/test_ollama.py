"""Tests for Ollama local provider implementation."""

from __future__ import annotations

from unittest.mock import AsyncMock, patch

import pytest

from app.services.providers.ollama import OllamaProvider


@pytest.fixture
def provider():
    return OllamaProvider()


@pytest.mark.asyncio
class TestOllamaProvider:
    async def test_provider_name(self, provider: OllamaProvider):
        assert provider.name == "ollama"

    async def test_base_url_default(self, provider: OllamaProvider):
        assert provider.base_url == "http://127.0.0.1:11434"

    async def test_base_url_from_env(self):
        with patch.dict("os.environ", {"OLLAMA_BASE_URL": "http://localhost:11434"}):
            p = OllamaProvider()
            assert p.base_url == "http://localhost:11434"

    async def test_default_models(self, provider: OllamaProvider):
        assert "llama3.2" in provider.default_models
        assert "codellama" in provider.default_models
        assert len(provider.default_models) > 5

    async def test_supports_streaming(self, provider: OllamaProvider):
        assert provider.supports_streaming is True

    async def test_no_api_key(self, provider: OllamaProvider):
        assert provider.api_key_env == ""
        assert provider._get_api_key() == ""

    async def test_estimate_cost_always_zero(self, provider: OllamaProvider):
        assert provider.estimate_cost("llama3.2", 1000, 500) == 0.0
        assert provider.estimate_cost("unknown", 999, 999) == 0.0

    async def test_check_health_returns_false_when_offline(self, provider: OllamaProvider):
        with patch.object(provider, "_make_request") as mock:
            from app.core.exceptions import ProviderError
            mock.side_effect = ProviderError(provider="ollama", detail="connection refused")
            result = await provider.check_health()
            assert result is False

    async def test_list_models_fallback_when_offline(self, provider: OllamaProvider):
        with patch.object(provider, "_make_request") as mock:
            from app.core.exceptions import ProviderError
            mock.side_effect = ProviderError(provider="ollama", detail="offline")
            models = await provider.list_models()
            assert len(models) == len(provider.default_models)
            for m in models:
                assert m["provider"] == "ollama"
                assert m["local"] is True

    async def test_pull_model_timeout(self, provider: OllamaProvider):
        with patch.object(provider, "client") as mock_client:
            from httpx import TimeoutException
            mock_context = AsyncMock()
            mock_context.__aenter__.side_effect = TimeoutException("timed out")
            mock_client.stream.return_value = mock_context

            from app.core.exceptions import ProviderError
            with pytest.raises(ProviderError, match="Timed out pulling"):
                await provider.pull_model("test-model")

    async def test_get_running_models_empty_when_offline(self, provider: OllamaProvider):
        with patch.object(provider, "_make_request") as mock:
            from app.core.exceptions import ProviderError
            mock.side_effect = ProviderError(provider="ollama", detail="offline")
            result = await provider.get_running_models()
            assert result == []

    async def test_get_model_info(self, provider: OllamaProvider):
        info = provider.get_model_info("llama3.2")
        assert info.id == "llama3.2"
        assert info.provider == "ollama"
        assert info.pricing is None
        assert info.capabilities.chat is True
        assert info.capabilities.streaming is True
        assert info.capabilities.vision is False

    async def test_get_model_info_coder(self, provider: OllamaProvider):
        info = provider.get_model_info("deepseek-coder")
        assert info.capabilities.code_generation is True
        # function_calling is only enabled for llama/mistral models
        assert info.capabilities.function_calling is False
        assert info.capabilities.embeddings is True

    async def test_parse_chat_response(self, provider: OllamaProvider):
        raw = {
            "model": "llama3.2",
            "created_at": "2024-01-01T00:00:00Z",
            "message": {"role": "assistant", "content": "Hello!"},
            "done_reason": "stop",
            "prompt_eval_count": 10,
            "eval_count": 5,
        }
        parsed = provider._parse_chat_response(raw)
        assert parsed["choices"][0]["message"]["content"] == "Hello!"
        assert parsed["usage"]["prompt_tokens"] == 10
        assert parsed["usage"]["completion_tokens"] == 5

    async def test_parse_stream_chunk(self, provider: OllamaProvider):
        import json
        chunk = json.dumps({
            "model": "llama3.2",
            "message": {"role": "assistant", "content": "Hello"},
            "done": False,
        }).encode()
        parsed = provider._parse_stream_chunk(chunk)
        assert parsed["choices"][0]["delta"]["content"] == "Hello"

    async def test_parse_stream_chunk_done(self, provider: OllamaProvider):
        import json
        chunk = json.dumps({
            "model": "llama3.2",
            "message": {"role": "assistant", "content": "Done"},
            "done": True,
        }).encode()
        parsed = provider._parse_stream_chunk(chunk)
        assert parsed["choices"][0]["finish_reason"] == "stop"

    async def test_parse_stream_chunk_invalid(self, provider: OllamaProvider):
        chunk = b"not valid json"
        parsed = provider._parse_stream_chunk(chunk)
        assert parsed["choices"][0]["delta"]["content"] == ""

    async def test_headers_no_auth(self, provider: OllamaProvider):
        headers = provider._get_headers()
        assert headers == {"Content-Type": "application/json"}

    async def test_build_chat_body(self, provider: OllamaProvider):
        from app.models.request import ChatCompletionRequest
        req = ChatCompletionRequest(
            model="llama3.2",
            messages=[{"role": "user", "content": "Hi"}],
            temperature=0.5,
            max_tokens=100,
        )
        body = provider._build_chat_body(req)
        assert body["model"] == "llama3.2"
        assert body["messages"][0]["content"] == "Hi"
        assert body["temperature"] == 0.5
        assert body["max_tokens"] == 100
        assert body["stream"] is False
