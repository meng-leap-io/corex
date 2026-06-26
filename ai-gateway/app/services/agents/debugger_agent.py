from __future__ import annotations

from typing import Any, Dict, List, Optional

from structlog import get_logger

from app.services.agents.base_agent import (
    AgentConfig,
    AgentError,
    BaseAgent,
)

logger = get_logger(__name__)

DEBUGGER_SYSTEM_PROMPT = """You are an expert debugging engineer. Your role is to analyze buggy code, identify root causes, and provide fixes.

Approach:
1. **Understand the code** — what is it supposed to do?
2. **Analyze the error** — what exactly goes wrong?
3. **Identify root cause** — find the underlying issue
4. **Fix** — provide corrected code
5. **Prevent recurrence** — suggest tests or guardrails

For each bug, provide:
- Root cause analysis
- The specific fix
- Why the fix works
- How to prevent similar bugs

Output your debugging analysis as a JSON object:
{{
  "diagnosis": {{
    "summary": "what the bug is",
    "root_cause": "underlying cause",
    "severity": "critical|major|minor",
    "affected_component": "which part of the system"
  }},
  "fix": {{
    "code": "fixed code",
    "language": "language",
    "explanation": "why this fix works",
    "changes": ["list of specific changes made"]
  }},
  "prevention": [
    "actionable steps to prevent similar bugs"
  ],
  "test_suggestion": "how to test this fix"
}}

If multiple bugs exist, address them all."""


class DebuggerAgent(BaseAgent):
    agent_name: str = "debugger"
    agent_description: str = "Bug fixing and debugging"
    system_prompt_template: str = DEBUGGER_SYSTEM_PROMPT

    def __init__(self, config: Optional[AgentConfig] = None):
        super().__init__(config or AgentConfig(temperature=0.2, max_tokens=6144, model="gpt-4o"))

    async def validate_input(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        if "code" not in input_data:
            raise AgentError(self.agent_name, "Input must contain 'code'")
        code = input_data["code"]
        if not isinstance(code, str) or len(code.strip()) < 3:
            raise AgentError(self.agent_name, "Code must be at least 3 characters")
        return {
            "code": code.strip(),
            "error_message": input_data.get("error_message", ""),
            "expected_behavior": input_data.get("expected_behavior", ""),
            "language": input_data.get("language", ""),
            "stack_trace": input_data.get("stack_trace", ""),
            "context": input_data.get("context", ""),
        }

    async def parse_output(self, raw_output: str, input_data: Dict[str, Any]) -> Dict[str, Any]:
        parsed = self._extract_json(raw_output)
        if parsed and "diagnosis" in parsed:
            return {
                "diagnosis": parsed["diagnosis"],
                "fix": parsed.get("fix", {}),
                "prevention": parsed.get("prevention", []),
                "test_suggestion": parsed.get("test_suggestion", ""),
            }

        fix_blocks = self._extract_code_blocks(raw_output)
        return {
            "diagnosis": {"summary": raw_output[:300], "root_cause": "See analysis below"},
            "fix": {"code": fix_blocks[0]["code"] if fix_blocks else "", "changes": []},
            "prevention": [],
            "raw_output": raw_output,
        }

    async def process(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        validated = await self.validate_input(input_data)
        code = validated["code"]
        error_msg = validated["error_message"]
        expected = validated["expected_behavior"]
        language = validated["language"]
        stack_trace = validated["stack_trace"]
        context = validated["context"]

        prompt_parts = [f"Debug the following code:\n\n```{language}\n{self._truncate(code)}\n```"]
        if error_msg:
            prompt_parts.append(f"\nError message:\n{error_msg}")
        if stack_trace:
            prompt_parts.append(f"\nStack trace:\n{self._truncate(stack_trace, 3000)}")
        if expected:
            prompt_parts.append(f"\nExpected behavior:\n{expected}")
        if context:
            prompt_parts.append(f"\nContext:\n{context}")

        prompt = "\n".join(prompt_parts)
        messages = self._build_messages(prompt, {"language": language or "any"})
        raw = await self._call_with_retry(messages, self._make_step())
        return await self.parse_output(raw, input_data)

    def _make_step(self):
        from app.services.agents.base_agent import AgentStep
        return AgentStep(agent=self.agent_name, action="debugging", input_data={})
