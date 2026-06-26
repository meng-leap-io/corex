from __future__ import annotations

from typing import Any, Dict, List, Optional

from structlog import get_logger

from app.services.agents.base_agent import (
    AgentConfig,
    AgentError,
    BaseAgent,
)

logger = get_logger(__name__)

CODER_SYSTEM_PROMPT = """You are an expert software engineer. Your role is to write clean, production-ready code based on specifications.

Guidelines:
- Write complete, working code with proper error handling
- Follow language-specific best practices and conventions
- Include type hints where applicable
- Add docstrings for all public functions and classes
- Use dependency injection and proper separation of concerns
- Handle edge cases and invalid inputs gracefully
- Keep functions focused and single-purpose

Output your code response as a JSON object:
{{
  "files": [
    {{
      "path": "relative/file/path",
      "language": "python|javascript|php|...",
      "content": "full file content here",
      "description": "what this file does"
    }}
  ],
  "explanation": "brief explanation of the implementation",
  "dependencies": ["list of external dependencies"],
  "setup_instructions": "how to run/test this code"
}}

Always output complete files, never placeholders or "..."."""


class CoderAgent(BaseAgent):
    agent_name: str = "coder"
    agent_description: str = "Code generation from specifications"
    system_prompt_template: str = CODER_SYSTEM_PROMPT

    def __init__(self, config: Optional[AgentConfig] = None):
        super().__init__(config or AgentConfig(temperature=0.2, max_tokens=8192, model="gpt-4o"))

    async def validate_input(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        if "task" not in input_data and "specification" not in input_data and "prompt" not in input_data:
            raise AgentError(self.agent_name, "Input must contain 'task', 'specification', or 'prompt'")
        spec = input_data.get("task") or input_data.get("specification") or input_data.get("prompt", "")
        if not isinstance(spec, str) or len(spec.strip()) < 5:
            raise AgentError(self.agent_name, "Specification must be at least 5 characters")
        return {
            "specification": spec.strip(),
            "language": input_data.get("language", ""),
            "existing_code": input_data.get("existing_code", ""),
            "context": input_data.get("context", ""),
        }

    async def parse_output(self, raw_output: str, input_data: Dict[str, Any]) -> Dict[str, Any]:
        parsed = self._extract_json(raw_output)
        if parsed and "files" in parsed:
            return {
                "files": parsed["files"],
                "explanation": parsed.get("explanation", ""),
                "dependencies": parsed.get("dependencies", []),
                "setup_instructions": parsed.get("setup_instructions", ""),
            }

        code_blocks = self._extract_code_blocks(raw_output)
        if code_blocks:
            return {"files": code_blocks, "explanation": raw_output, "fallback": True}

        return {"files": [], "explanation": raw_output, "raw_content": raw_output}

    async def process(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        validated = await self.validate_input(input_data)
        spec = validated["specification"]
        language = validated["language"]
        existing = validated["existing_code"]
        context = validated["context"]

        prompt_parts = [f"Implement the following specification:\n\n{spec}"]
        if language:
            prompt_parts.append(f"\nLanguage: {language}")
        if existing:
            prompt_parts.append(f"\nExisting code context:\n```\n{self._truncate(existing)}\n```")
        if context:
            prompt_parts.append(f"\nAdditional context:\n{context}")
        prompt = "\n".join(prompt_parts)

        messages = self._build_messages(prompt, {"specification": spec, "language": language or "any"})
        raw = await self._call_with_retry(messages, self._make_step())
        return await self.parse_output(raw, input_data)

    def _make_step(self):
        from app.services.agents.base_agent import AgentStep
        return AgentStep(agent=self.agent_name, action="code_generation", input_data={})
