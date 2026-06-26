"""Tests for the base agent system."""

from __future__ import annotations

import json
from unittest.mock import AsyncMock, patch

import pytest

from app.services.agents.base_agent import (
    AgentConfig,
    AgentState,
    BaseAgent,
    AgentError,
)


class ConcreteAgent(BaseAgent):
    """Concrete implementation for testing."""

    name: str = "test-agent"
    system_prompt: str = "You are a test agent."

    async def validate_input(self, data: dict) -> dict:
        if "prompt" not in data:
            raise ValueError("Missing 'prompt' in input")
        return data

    async def process(self, data: dict) -> dict:
        return {"result": "processed", "input": data}

    async def parse_output(self, response: str) -> dict:
        try:
            return json.loads(response)
        except json.JSONDecodeError:
            return {"raw": response}


@pytest.fixture
def agent():
    return ConcreteAgent()


@pytest.mark.asyncio
class TestBaseAgent:
    async def test_agent_has_name(self, agent: ConcreteAgent):
        assert agent.name == "test-agent"

    async def test_agent_has_system_prompt(self, agent: ConcreteAgent):
        assert "test agent" in agent.system_prompt.lower()

    async def test_validate_input_passes_with_prompt(self, agent: ConcreteAgent):
        result = await agent.validate_input({"prompt": "Hello"})
        assert result["prompt"] == "Hello"

    async def test_validate_input_fails_without_prompt(self, agent: ConcreteAgent):
        with pytest.raises(ValueError, match="Missing 'prompt'"):
            await agent.validate_input({})

    async def test_process_returns_result(self, agent: ConcreteAgent):
        result = await agent.process({"prompt": "test"})
        assert result["result"] == "processed"

    async def test_parse_output_json(self, agent: ConcreteAgent):
        result = await agent.parse_output('{"key": "value"}')
        assert result == {"key": "value"}

    async def test_parse_output_fallback(self, agent: ConcreteAgent):
        result = await agent.parse_output("plain text response")
        assert result == {"raw": "plain text response"}

    async def test_execute_returns_state(self, agent: ConcreteAgent):
        state = await agent.execute({"prompt": "hello"})
        assert isinstance(state, AgentState)
        assert state.agent == "test-agent"
        assert state.status == "completed"

    async def test_execute_fails_on_invalid_input(self, agent: ConcreteAgent):
        state = await agent.execute({})
        assert state.status == "failed"
        assert state.error is not None

    async def test_agent_config_defaults(self):
        config = AgentConfig()
        assert config.max_retries == 3
        assert config.temperature == 0.7
        assert config.max_tokens == 2048

    async def test_agent_state_has_run_id(self, agent: ConcreteAgent):
        state = await agent.execute({"prompt": "test"})
        assert len(state.run_id) > 0
        assert state.run_id.count("-") == 4

    async def test_agent_token_tracking(self, agent: ConcreteAgent):
        state = await agent.execute({"prompt": "track tokens"})
        assert isinstance(state.prompt_tokens, int)
        assert isinstance(state.completion_tokens, int)
        assert state.total_tokens >= 0

    async def test_agent_error_creation(self):
        error = AgentError("test-agent", "Something went wrong", recoverable=True)
        assert error.agent == "test-agent"
        assert str(error) == "Something went wrong"
        assert error.recoverable is True

    async def test_agent_error_default_recoverable(self):
        error = AgentError("test-agent", "Fatal error")
        assert error.recoverable is False
