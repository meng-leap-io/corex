from __future__ import annotations

import json
import re
import time
import uuid
from abc import ABC, abstractmethod
from dataclasses import dataclass, field
from enum import Enum
from typing import Any, Dict, List, Optional, Tuple

from pydantic import BaseModel, Field, field_validator
from structlog import get_logger

from app.core.config import settings
from app.services.ai_router import ai_router
from app.models.request import ChatCompletionRequest, Message
from app.models.response import Usage

logger = get_logger(__name__)


class AgentError(Exception):
    def __init__(self, agent: str, message: str, recoverable: bool = False):
        self.agent = agent
        self.recoverable = recoverable
        super().__init__(f"[{agent}] {message}")


class RetryExhaustedError(AgentError):
    def __init__(self, agent: str, message: str = "Max retries exhausted"):
        super().__init__(agent, message, recoverable=False)


class AgentStep(BaseModel):
    agent: str
    action: str
    input_data: Dict[str, Any] = Field(default_factory=dict)
    output_data: Optional[Dict[str, Any]] = None
    error: Optional[str] = None
    started_at: float = 0.0
    completed_at: float = 0.0
    duration_ms: float = 0.0
    tokens_used: int = 0
    retry_count: int = 0
    status: str = "pending"


class AgentConfig(BaseModel):
    max_retries: int = Field(default=3, ge=0, le=10)
    retry_delay: float = Field(default=1.0, ge=0.1, le=60.0)
    temperature: float = Field(default=0.3, ge=0.0, le=2.0)
    max_tokens: int = Field(default=4096, ge=64, le=32000)
    model: str = Field(default="gpt-4o")
    timeout: float = Field(default=60.0, ge=5.0, le=300.0)

    @field_validator("model")
    @classmethod
    def validate_model(cls, v: str) -> str:
        allowed = ("gpt-", "claude-", "llama", "mixtral", "deepseek-")
        if not any(v.startswith(p) for p in allowed):
            allowed_str = ", ".join(allowed)
            raise ValueError(f"Model must start with one of: {allowed_str}")
        return v


class AgentState(BaseModel):
    run_id: str = Field(default_factory=lambda: f"run_{uuid.uuid4().hex[:12]}")
    workflow_name: str = ""
    current_agent: str = ""
    steps: List[AgentStep] = Field(default_factory=list)
    artifacts: Dict[str, Any] = Field(default_factory=dict)
    errors: List[str] = Field(default_factory=list)
    started_at: float = 0.0
    completed_at: float = 0.0
    status: str = "pending"


class UsageTracker:
    def __init__(self):
        self.prompt_tokens: int = 0
        self.completion_tokens: int = 0
        self.total_cost: float = 0.0
        self.requests: int = 0

    def add(self, prompt: int, completion: int, cost: float) -> None:
        self.prompt_tokens += prompt
        self.completion_tokens += completion
        self.total_cost += cost
        self.requests += 1

    def to_dict(self) -> Dict[str, Any]:
        return {
            "prompt_tokens": self.prompt_tokens,
            "completion_tokens": self.completion_tokens,
            "total_tokens": self.prompt_tokens + self.completion_tokens,
            "total_cost": round(self.total_cost, 6),
            "requests": self.requests,
        }


class BaseAgent(ABC):
    agent_name: str = ""
    agent_description: str = ""
    system_prompt_template: str = ""

    def __init__(self, config: Optional[AgentConfig] = None):
        self.config = config or AgentConfig()
        self.usage = UsageTracker()
        self._run_id: Optional[str] = None

    def _build_system_prompt(self, context: Optional[Dict[str, Any]] = None) -> str:
        ctx = context or {}
        return self.system_prompt_template.format(**ctx)

    def _build_messages(
        self,
        user_input: str,
        context: Optional[Dict[str, Any]] = None,
        history: Optional[List[Dict[str, str]]] = None,
    ) -> List[Dict[str, str]]:
        system = self._build_system_prompt(context)
        messages = [{"role": "system", "content": system}]
        if history:
            for msg in history:
                messages.append({"role": msg.get("role", "user"), "content": msg.get("content", "")})
        messages.append({"role": "user", "content": user_input})
        return messages

    async def _call_llm(
        self,
        messages: List[Dict[str, str]],
        temperature: Optional[float] = None,
        max_tokens: Optional[int] = None,
        model: Optional[str] = None,
    ) -> Tuple[str, Usage]:
        request = ChatCompletionRequest(
            model=model or self.config.model,
            messages=[Message(**m) for m in messages],
            temperature=temperature or self.config.temperature,
            max_tokens=max_tokens or self.config.max_tokens,
        )

        response = await ai_router.chat_completion(request)

        content = ""
        usage = Usage()
        if response.choices:
            content = response.choices[0].message.content or ""
        if response.usage:
            usage = response.usage

        cost = 0.0
        if usage:
            try:
                provider, resolved = ai_router.get_provider_for_model(request.model)
                cost = provider.estimate_cost(resolved, usage.prompt_tokens, usage.completion_tokens)
            except Exception:
                pass

        self.usage.add(usage.prompt_tokens, usage.completion_tokens, cost)

        logger.info(
            "llm_call",
            agent=self.agent_name,
            model=request.model,
            prompt_tokens=usage.prompt_tokens,
            completion_tokens=usage.completion_tokens,
            cost=cost,
        )

        return content, usage

    async def _call_with_retry(
        self,
        messages: List[Dict[str, str]],
        step: AgentStep,
        temperature: Optional[float] = None,
        max_tokens: Optional[int] = None,
    ) -> str:
        last_error: Optional[str] = None

        for attempt in range(self.config.max_retries + 1):
            try:
                content, usage = await self._call_llm(messages, temperature, max_tokens)
                step.tokens_used = usage.total_tokens
                return content
            except Exception as e:
                last_error = str(e)
                step.retry_count = attempt + 1
                logger.warning(
                    "agent_retry",
                    agent=self.agent_name,
                    attempt=attempt + 1,
                    error=last_error,
                )
                if attempt < self.config.max_retries:
                    delay = self.config.retry_delay * (2 ** attempt)
                    await self._sleep(delay)
                else:
                    raise RetryExhaustedError(
                        self.agent_name,
                        f"Failed after {self.config.max_retries + 1} attempts: {last_error}",
                    ) from None

        raise RetryExhaustedError(self.agent_name, f"Failed: {last_error}")

    @abstractmethod
    async def validate_input(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        ...

    @abstractmethod
    async def parse_output(self, raw_output: str, input_data: Dict[str, Any]) -> Dict[str, Any]:
        ...

    @abstractmethod
    async def process(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        ...

    def _extract_json(self, text: str) -> Optional[Dict[str, Any]]:
        text = text.strip()
        if text.startswith("```"):
            text = re.sub(r"^```(?:json)?\s*", "", text)
            text = re.sub(r"\s*```$", "", text)
        text = text.strip()

        try:
            return json.loads(text)
        except json.JSONDecodeError:
            pass

        code_block = re.search(r"```(?:json)?\s*([\s\S]*?)```", text)
        if code_block:
            try:
                return json.loads(code_block.group(1))
            except json.JSONDecodeError:
                pass

        brace_start = text.find("{")
        brace_end = text.rfind("}")
        if brace_start != -1 and brace_end > brace_start:
            try:
                return json.loads(text[brace_start : brace_end + 1])
            except json.JSONDecodeError:
                pass

        bracket_start = text.find("[")
        bracket_end = text.rfind("]")
        if bracket_start != -1 and bracket_end > bracket_start:
            try:
                return json.loads(text[bracket_start : bracket_end + 1])
            except json.JSONDecodeError:
                pass

        return None

    def _extract_code_blocks(self, text: str) -> List[Dict[str, str]]:
        blocks = []
        pattern = re.compile(r"```(\w+)?\n([\s\S]*?)```", re.MULTILINE)
        for match in pattern.finditer(text):
            language = match.group(1) or "text"
            code = match.group(2).strip()
            blocks.append({"language": language, "code": code})
        return blocks

    def _truncate(self, text: str, max_length: int = 8000) -> str:
        if len(text) <= max_length:
            return text
        return text[:max_length] + f"\n\n... [truncated at {max_length} characters]"

    def _format_duration(self, seconds: float) -> str:
        if seconds < 1:
            return f"{round(seconds * 1000)}ms"
        if seconds < 60:
            return f"{round(seconds, 2)}s"
        return f"{int(seconds // 60)}m {int(seconds % 60)}s"

    def reset_usage(self) -> None:
        self.usage = UsageTracker()

    def get_usage_summary(self) -> Dict[str, Any]:
        return self.usage.to_dict()

    async def _sleep(self, delay: float) -> None:
        import asyncio
        await asyncio.sleep(delay)
