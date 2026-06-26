from __future__ import annotations

from typing import Any, Dict, List, Optional

from structlog import get_logger

from app.services.agents.base_agent import (
    AgentConfig,
    AgentError,
    BaseAgent,
)

logger = get_logger(__name__)

DOCUMENTATION_SYSTEM_PROMPT = """You are a technical writer. Your role is to create clear, comprehensive documentation for code and APIs.

Documentation types you can generate:
1. **README** — Project overview, setup, usage, examples
2. **API Reference** — Endpoint descriptions, params, responses
3. **Architecture Guide** — System design, component relationships
4. **User Guide** — How-to guides and tutorials
5. **Inline Docs** — Code comments, docstrings

Guidelines:
- Write for the target audience (beginners or experts)
- Include code examples for all major operations
- Use clear section headings and a logical structure
- Document edge cases, error states, and limitations
- Keep it concise but complete

Output your documentation as a JSON object:
{{
  "type": "readme|api_reference|architecture_guide|user_guide|docstrings",
  "title": "document title",
  "sections": [
    {{
      "heading": "section heading",
      "content": "section content in markdown",
      "code_examples": [
        {{
          "language": "python",
          "code": "example code",
          "description": "what this example shows"
        }}
      ]
    }}
  ],
  "documentation": "full markdown document",
  "suggestions": ["additional docs that should be created"]
}}

Always include practical code examples."""


class DocumentationAgent(BaseAgent):
    agent_name: str = "documentation"
    agent_description: str = "Documentation and API reference generation"
    system_prompt_template: str = DOCUMENTATION_SYSTEM_PROMPT

    def __init__(self, config: Optional[AgentConfig] = None):
        super().__init__(config or AgentConfig(temperature=0.3, max_tokens=8192, model="gpt-4o"))

    async def validate_input(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        if "code" not in input_data and "source" not in input_data and "prompt" not in input_data:
            raise AgentError(self.agent_name, "Input must contain 'code', 'source', or 'prompt'")
        source = input_data.get("code") or input_data.get("source") or input_data.get("prompt", "")
        return {
            "source": source.strip(),
            "doc_type": input_data.get("doc_type", "readme"),
            "language": input_data.get("language", ""),
            "audience": input_data.get("audience", "developers"),
            "context": input_data.get("context", ""),
        }

    async def parse_output(self, raw_output: str, input_data: Dict[str, Any]) -> Dict[str, Any]:
        parsed = self._extract_json(raw_output)
        if parsed and "sections" in parsed:
            return {
                "type": parsed.get("type", "readme"),
                "title": parsed.get("title", ""),
                "sections": parsed.get("sections", []),
                "documentation": parsed.get("documentation", raw_output),
                "suggestions": parsed.get("suggestions", []),
            }
        return {
            "type": input_data.get("doc_type", "readme"),
            "title": "Documentation",
            "sections": [{"heading": "Content", "content": raw_output}],
            "documentation": raw_output,
            "suggestions": [],
        }

    async def process(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        validated = await self.validate_input(input_data)
        source = validated["source"]
        doc_type = validated["doc_type"]
        language = validated["language"]
        audience = validated["audience"]
        context = validated["context"]

        doc_type_names = {
            "readme": "README / Project Overview",
            "api_reference": "API Reference",
            "architecture_guide": "Architecture Guide",
            "user_guide": "User Guide",
            "docstrings": "Inline Documentation / Docstrings",
        }
        type_name = doc_type_names.get(doc_type, doc_type)

        prompt_parts = [f"Generate a {type_name} for:\n\n"]
        if source:
            prompt_parts.append(f"```{language}\n{self._truncate(source)}\n```\n")
        prompt_parts.append(f"\nTarget audience: {audience}")
        if context:
            prompt_parts.append(f"\nContext:\n{context}")

        prompt = "\n".join(prompt_parts)
        messages = self._build_messages(prompt, {"doc_type": type_name, "audience": audience})
        raw = await self._call_with_retry(messages, self._make_step())
        return await self.parse_output(raw, input_data)

    def _make_step(self):
        from app.services.agents.base_agent import AgentStep
        return AgentStep(agent=self.agent_name, action="documentation_generation", input_data={})
