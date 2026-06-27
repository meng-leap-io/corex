"""Shared fixtures and configuration for all tests."""

from __future__ import annotations

import os
from typing import Any, AsyncGenerator, Dict
from unittest.mock import AsyncMock, MagicMock, patch

import httpx
import pytest
import pytest_asyncio
from fastapi import FastAPI
from httpx import ASGITransport
from asgi_lifespan import LifespanManager

from app.core.config import Settings, Environment


# ── Test Settings ──────────────────────────────────────────────────────────

@pytest.fixture(autouse=True)
def test_settings():
    """Override settings for testing."""
    with patch("app.core.config.settings") as mock:
        mock.environment = Environment.DEVELOPMENT
        mock.debug = True
        mock.log_level = "warning"
        mock.rate_limit_enabled = False
        mock.usage_tracking_enabled = False
        mock.sentry_dsn = None
        mock.jwt_secret = "test-secret-key"
        mock.redis_host = "localhost"
        mock.redis_port = 6379
        mock.redis_password = None
        mock.redis_db = 1
        mock.redis_url = "redis://localhost:6379/1"
        mock.is_production = False
        mock.is_development = True
        mock.app_version = "1.0.0-test"
        yield mock


# ── Test Client ────────────────────────────────────────────────────────────

@pytest_asyncio.fixture
async def app() -> FastAPI:
    """Create a fresh FastAPI app for testing."""
    from main import app as fastapi_app
    async with LifespanManager(fastapi_app) as manager:
        yield manager.app


@pytest_asyncio.fixture
async def client(app: FastAPI) -> AsyncGenerator[httpx.AsyncClient, None]:
    """Create an async test client."""
    transport = ASGITransport(app=app)
    async with httpx.AsyncClient(transport=transport, base_url="http://test") as ac:
        yield ac


# ── Mock Providers ─────────────────────────────────────────────────────────

@pytest.fixture
def mock_openai_response() -> Dict[str, Any]:
    return {
        "id": "chatcmpl-123",
        "object": "chat.completion",
        "created": 1677652288,
        "model": "gpt-4o",
        "choices": [
            {
                "index": 0,
                "message": {
                    "role": "assistant",
                    "content": "Hello! I'm an AI assistant.",
                },
                "finish_reason": "stop",
            }
        ],
        "usage": {
            "prompt_tokens": 25,
            "completion_tokens": 12,
            "total_tokens": 37,
        },
    }


@pytest.fixture
def mock_anthropic_response() -> Dict[str, Any]:
    return {
        "id": "msg_123",
        "type": "message",
        "role": "assistant",
        "content": [{"type": "text", "text": "Hello from Claude!"}],
        "model": "claude-3-sonnet-20240229",
        "usage": {
            "input_tokens": 20,
            "output_tokens": 10,
        },
    }


@pytest.fixture
def mock_stream_chunks() -> list[Dict[str, Any]]:
    return [
        {"choices": [{"delta": {"role": "assistant"}, "index": 0}]},
        {"choices": [{"delta": {"content": "Hello"}, "index": 0}]},
        {"choices": [{"delta": {"content": " world"}, "index": 0}]},
        {"choices": [{"delta": {"content": "!"}, "index": 0}]},
        {"choices": [{"delta": {}, "finish_reason": "stop", "index": 0}]},
    ]


# ── Mock HTTP Client ───────────────────────────────────────────────────────

@pytest.fixture
def mock_httpx_client():
    """Mock httpx AsyncClient for provider testing."""
    with patch("httpx.AsyncClient") as mock:
        client = AsyncMock()
        mock.return_value = client
        yield client


@pytest.fixture
def mock_redis():
    """Mock Redis connection."""
    with patch("redis.asyncio.from_url") as mock:
        redis = AsyncMock()
        redis.ping.return_value = True
        redis.get.return_value = None
        redis.set.return_value = True
        redis.delete.return_value = True
        redis.exists.return_value = False
        redis.expire.return_value = True
        mock.return_value = redis
        yield redis


# ── Sample Data ────────────────────────────────────────────────────────────

@pytest.fixture
def sample_chat_request() -> Dict[str, Any]:
    return {
        "model": "gpt-4o",
        "messages": [
            {"role": "system", "content": "You are a helpful assistant."},
            {"role": "user", "content": "Hello!"},
        ],
        "temperature": 0.7,
        "max_tokens": 100,
    }


@pytest.fixture
def sample_code_request() -> Dict[str, Any]:
    return {
        "prompt": "Create a REST API endpoint in Laravel",
        "language": "php",
        "framework": "laravel",
        "max_tokens": 500,
    }


@pytest.fixture
def sample_embedding_request() -> Dict[str, Any]:
    return {
        "model": "text-embedding-3-small",
        "input": "The quick brown fox jumps over the lazy dog",
    }


@pytest.fixture
def sample_agent_request() -> Dict[str, Any]:
    return {
        "workflow": "build_blog_website",
        "input": {
            "project_name": "My Blog",
            "description": "A personal blog with posts and comments",
        },
    }


# ── Auth Headers ───────────────────────────────────────────────────────────

@pytest.fixture
def auth_headers() -> Dict[str, str]:
    token = "test-jwt-token-for-testing-purposes"
    return {"Authorization": f"Bearer {token}"}
