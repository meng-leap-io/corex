"""Tests for health and readiness endpoints."""

from __future__ import annotations

import httpx
import pytest


@pytest.mark.asyncio
class TestHealthEndpoints:
    async def test_health_returns_ok(self, client: httpx.AsyncClient):
        response = await client.get("/health")
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "healthy" or data["status"] == "ok"

    async def test_health_has_service_name(self, client: httpx.AsyncClient):
        response = await client.get("/health")
        data = response.json()
        assert data["service"] == "ai-gateway"

    async def test_health_includes_timestamp(self, client: httpx.AsyncClient):
        response = await client.get("/health")
        data = response.json()
        assert "timestamp" in data
        assert "checks" in data

    async def test_health_checks_count(self, client: httpx.AsyncClient):
        response = await client.get("/health")
        data = response.json()
        assert data["checks_count"] >= 1
        assert data["healthy_count"] >= 0

    async def test_ready_returns_ready(self, client: httpx.AsyncClient):
        response = await client.get("/ready")
        assert response.status_code == 200
        data = response.json()
        assert data["status"] == "ready"

    async def test_ready_has_uptime(self, client: httpx.AsyncClient):
        response = await client.get("/ready")
        data = response.json()
        assert data["uptime_seconds"] >= 0

    async def test_root_returns_info(self, client: httpx.AsyncClient):
        response = await client.get("/")
        assert response.status_code == 200
        data = response.json()
        assert "message" in data
        assert "version" in data

    async def test_health_is_accessible_without_auth(self, client: httpx.AsyncClient):
        response = await client.get("/health")
        assert response.status_code == 200

    async def test_health_includes_request_id_header(self, client: httpx.AsyncClient):
        response = await client.get("/health")
        assert "X-Request-ID" in response.headers
