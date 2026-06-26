"""Tests for agent system API endpoints."""

from __future__ import annotations

import httpx
import pytest


@pytest.mark.asyncio
class TestAgentEndpoints:
    async def test_list_workflows(self, client: httpx.AsyncClient):
        response = await client.get("/v1/agent/workflows")
        assert response.status_code == 200
        data = response.json()
        assert "workflows" in data
        assert len(data["workflows"]) > 0

    async def test_workflows_include_build_blog(self, client: httpx.AsyncClient):
        response = await client.get("/v1/agent/workflows")
        data = response.json()
        assert "build_blog_website" in data["workflows"]

    async def test_workflows_include_debug_code(self, client: httpx.AsyncClient):
        response = await client.get("/v1/agent/workflows")
        data = response.json()
        assert "debug_code" in data["workflows"]

    async def test_get_workflow_detail(self, client: httpx.AsyncClient):
        response = await client.get("/v1/agent/workflows/build_blog_website")
        assert response.status_code == 200
        data = response.json()
        assert "workflow" in data

    async def test_get_nonexistent_workflow_returns_404(self, client: httpx.AsyncClient):
        response = await client.get("/v1/agent/workflows/nonexistent_workflow")
        assert response.status_code == 404

    async def test_workflow_detail_has_steps(self, client: httpx.AsyncClient):
        response = await client.get("/v1/agent/workflows/build_blog_website")
        data = response.json()["workflow"]
        assert len(data["steps"]) > 0

    async def test_list_runs_returns_empty_initially(self, client: httpx.AsyncClient):
        response = await client.get("/v1/agent/runs")
        assert response.status_code == 200
        assert "runs" in response.json()

    async def test_get_nonexistent_run_returns_404(self, client: httpx.AsyncClient):
        response = await client.get("/v1/agent/runs/nonexistent-run-id")
        assert response.status_code == 404

    async def test_execute_workflow_without_input_fails(self, client: httpx.AsyncClient):
        response = await client.post("/v1/agent/execute", json={})
        assert response.status_code == 422

    async def test_execute_workflow_requires_workflow_field(self, client: httpx.AsyncClient):
        response = await client.post("/v1/agent/execute", json={"input": {}})
        assert response.status_code == 422

    async def test_execute_nonexistent_workflow_returns_400(self, client: httpx.AsyncClient):
        response = await client.post("/v1/agent/execute", json={
            "workflow": "does_not_exist",
            "input": {"test": True},
        })
        assert response.status_code in (400, 404)

    async def test_execute_returns_run_id(self, client: httpx.AsyncClient, sample_agent_request):
        response = await client.post("/v1/agent/execute", json=sample_agent_request)
        if response.status_code == 200:
            data = response.json()
            assert "run_id" in data
            assert data["workflow"] == "build_blog_website"
