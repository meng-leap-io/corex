from __future__ import annotations

from typing import Any, Dict, List, Optional

from structlog import get_logger

from app.services.agents.base_agent import (
    AgentConfig,
    AgentError,
    BaseAgent,
)

logger = get_logger(__name__)

TESTER_SYSTEM_PROMPT = """You are a QA engineer specialized in writing comprehensive unit tests. Your role is to create thorough test suites that validate correctness, edge cases, and error handling.

Requirements:
- Cover happy path, edge cases, and error conditions
- Use the project's existing test framework and conventions
- Include descriptive test names that explain the scenario
- Mock external dependencies appropriately
- Test both public API and internal logic where valuable
- Include setup/teardown where needed
- Aim for >80% code coverage on the target code

Output your tests as a JSON object:
{{
  "test_files": [
    {{
      "path": "tests/test_file.py",
      "language": "python",
      "content": "full test file content",
      "framework": "pytest|jest|phpunit|..."
    }}
  ],
  "coverage_estimate": "percentage or description",
  "testing_notes": ["list of testing considerations"],
  "test_plan": "brief description of test strategy"
}}

Focus on meaningful tests that catch real bugs."""


class TesterAgent(BaseAgent):
    agent_name: str = "tester"
    agent_description: str = "Unit test generation"
    system_prompt_template: str = TESTER_SYSTEM_PROMPT

    def __init__(self, config: Optional[AgentConfig] = None):
        super().__init__(config or AgentConfig(temperature=0.2, max_tokens=6144, model="gpt-4o"))

    async def validate_input(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        if "code" not in input_data and "source" not in input_data and "file" not in input_data:
            raise AgentError(self.agent_name, "Input must contain 'code', 'source', or 'file'")
        code = input_data.get("code") or input_data.get("source") or ""
        return {
            "code": code.strip(),
            "language": input_data.get("language", ""),
            "test_framework": input_data.get("test_framework", ""),
            "existing_tests": input_data.get("existing_tests", ""),
            "context": input_data.get("context", ""),
        }

    async def parse_output(self, raw_output: str, input_data: Dict[str, Any]) -> Dict[str, Any]:
        parsed = self._extract_json(raw_output)
        if parsed and "test_files" in parsed:
            return {
                "test_files": parsed["test_files"],
                "coverage_estimate": parsed.get("coverage_estimate", ""),
                "testing_notes": parsed.get("testing_notes", []),
                "test_plan": parsed.get("test_plan", ""),
            }

        code_blocks = self._extract_code_blocks(raw_output)
        if code_blocks:
            return {"test_files": code_blocks, "test_plan": raw_output, "fallback": True}

        return {"test_files": [], "test_plan": raw_output}

    async def process(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        validated = await self.validate_input(input_data)
        code = validated["code"]
        language = validated["language"]
        framework = validated["test_framework"]
        existing = validated["existing_tests"]
        context = validated["context"]

        prompt_parts = []
        if code:
            prompt_parts.append(f"Write unit tests for the following code:\n\n```{language}\n{self._truncate(code)}\n```")
        if language:
            prompt_parts.append(f"\nLanguage: {language}")
        if framework:
            prompt_parts.append(f"\nTest framework: {framework}")
        if existing:
            prompt_parts.append(f"\nExisting test patterns:\n{self._truncate(existing)}\n")
        if context:
            prompt_parts.append(f"\nContext:\n{context}")

        prompt = "\n".join(prompt_parts)
        messages = self._build_messages(prompt, {"language": language or "any"})
        raw = await self._call_with_retry(messages, self._make_step())
        return await self.parse_output(raw, input_data)

    def _make_step(self):
        from app.services.agents.base_agent import AgentStep
        return AgentStep(agent=self.agent_name, action="test_generation", input_data={})
