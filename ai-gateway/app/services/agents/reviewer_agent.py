from __future__ import annotations

from typing import Any, Dict, List, Optional

from structlog import get_logger

from app.services.agents.base_agent import (
    AgentConfig,
    AgentError,
    BaseAgent,
)

logger = get_logger(__name__)

REVIEWER_SYSTEM_PROMPT = """You are a senior code reviewer. Your role is to analyze code for quality, correctness, security, and maintainability.

Review criteria:
1. **Correctness**: Does the code do what it's supposed to? Any logical errors?
2. **Security**: SQL injection, XSS, CSRF, auth bypass, data leaks?
3. **Performance**: N+1 queries, memory leaks, unnecessary allocations?
4. **Maintainability**: Is the code clear? Are there proper abstractions?
5. **Style**: Follows project conventions? Proper naming, formatting?
6. **Error handling**: Are errors caught and handled appropriately?
7. **Testing**: Is the code testable? Are there tests?

Output your review as a JSON object:
{{
  "verdict": "approved|changes_requested|rejected",
  "summary": "overall assessment",
  "issues": [
    {{
      "severity": "critical|major|minor|nitpick",
      "category": "correctness|security|performance|maintainability|style",
      "file": "file path",
      "line": line_number_or_null,
      "description": "what's wrong",
      "suggestion": "how to fix it"
    }}
  ],
  "strengths": ["what's good about this code"],
  "recommendations": ["actionable improvement suggestions"],
  "score": 0-100
}}

Be constructive, specific, and actionable."""


class ReviewerAgent(BaseAgent):
    agent_name: str = "reviewer"
    agent_description: str = "Code review and quality analysis"
    system_prompt_template: str = REVIEWER_SYSTEM_PROMPT

    def __init__(self, config: Optional[AgentConfig] = None):
        super().__init__(config or AgentConfig(temperature=0.2, max_tokens=4096, model="gpt-4o"))

    async def validate_input(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        if "code" not in input_data and "files" not in input_data:
            raise AgentError(self.agent_name, "Input must contain 'code' or 'files'")
        code = input_data.get("code", "")
        files = input_data.get("files", [])
        if not code and not files:
            raise AgentError(self.agent_name, "No code provided for review")
        return {
            "code": code.strip(),
            "files": files,
            "language": input_data.get("language", ""),
            "context": input_data.get("context", ""),
        }

    async def parse_output(self, raw_output: str, input_data: Dict[str, Any]) -> Dict[str, Any]:
        parsed = self._extract_json(raw_output)
        if parsed and "verdict" in parsed:
            return parsed
        return {
            "verdict": "changes_requested",
            "summary": "Review completed",
            "issues": [],
            "strengths": [],
            "recommendations": [],
            "score": 0,
            "raw_review": raw_output,
        }

    async def process(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        validated = await self.validate_input(input_data)
        code = validated["code"]
        files = validated["files"]
        language = validated["language"]
        context = validated["context"]

        prompt_parts = ["Review the following code:\n"]
        if files:
            for f in files:
                path = f.get("path", "unknown")
                content = f.get("content", f.get("code", ""))
                prompt_parts.append(f"\n### {path}\n```\n{self._truncate(content)}\n```")
        if code:
            prompt_parts.append(f"\n```{language}\n{self._truncate(code)}\n```")
        if context:
            prompt_parts.append(f"\nContext:\n{context}")

        prompt = "\n".join(prompt_parts)
        messages = self._build_messages(prompt, {"language": language or "any"})
        raw = await self._call_with_retry(messages, self._make_step())
        return await self.parse_output(raw, input_data)

    def _make_step(self):
        from app.services.agents.base_agent import AgentStep
        return AgentStep(agent=self.agent_name, action="code_review", input_data={})
