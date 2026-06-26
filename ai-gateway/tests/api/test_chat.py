"""Tests for chat completion and embedding endpoints."""

from __future__ import annotations

import httpx
import pytest


@pytest.mark.asyncio
class TestChatEndpoints:
    async def test_chat_completion_returns_response(self, client: httpx.AsyncClient, sample_chat_request):
        response = await client.post("/v1/chat/completions", json=sample_chat_request)
        assert response.status_code == 200
        data = response.json()
        assert "choices" in data

    async def test_chat_requires_model(self, client: httpx.AsyncClient):
        response = await client.post("/v1/chat/completions", json={"messages": []})
        assert response.status_code == 422

    async def test_chat_requires_messages(self, client: httpx.AsyncClient):
        response = await client.post("/v1/chat/completions", json={"model": "gpt-4o"})
        assert response.status_code == 422

    async def test_chat_with_invalid_model(self, client: httpx.AsyncClient):
        response = await client.post("/v1/chat/completions", json={
            "model": "nonexistent-model",
            "messages": [{"role": "user", "content": "Hi"}],
        })
        assert response.status_code in (200, 400, 422)

    async def test_chat_with_system_message(self, client: httpx.AsyncClient):
        response = await client.post("/v1/chat/completions", json={
            "model": "gpt-4o",
            "messages": [
                {"role": "system", "content": "You are helpful."},
                {"role": "user", "content": "Say hello"},
            ],
        })
        assert response.status_code == 200

    async def test_embeddings_endpoint(self, client: httpx.AsyncClient, sample_embedding_request):
        response = await client.post("/v1/embeddings", json=sample_embedding_request)
        assert response.status_code == 200
        data = response.json()
        assert "data" in data
        assert "model" in data

    async def test_embeddings_requires_model(self, client: httpx.AsyncClient):
        response = await client.post("/v1/embeddings", json={"input": "test"})
        assert response.status_code == 422

    async def test_embeddings_requires_input(self, client: httpx.AsyncClient):
        response = await client.post("/v1/embeddings", json={"model": "text-embedding-3-small"})
        assert response.status_code == 422

    async def test_chat_returns_request_id(self, client: httpx.AsyncClient, sample_chat_request):
        response = await client.post("/v1/chat/completions", json=sample_chat_request)
        assert "X-Request-ID" in response.headers
