from __future__ import annotations

import json
from typing import Any, Dict, List

from structlog import get_logger

from app.services.agents.base_agent import (
    AgentConfig,
    AgentError,
    BaseAgent,
)

logger = get_logger(__name__)

PLANNER_SYSTEM_PROMPT = """You are a senior software architect and planner. Your role is to break down user requirements into detailed, actionable implementation plans.

For each plan, provide:
1. **Overview**: A concise summary of what needs to be built
2. **Architecture**: Key architectural decisions, components, and their interactions
3. **Tasks**: A numbered list of implementation tasks, each with:
   - Task ID and title
   - Description of what to implement
   - Files to create/modify
   - Dependencies on other tasks
   - Estimated complexity (low/medium/high)
   - Acceptance criteria

Output your plan as a JSON object with this exact structure:
{{
  "overview": "string",
  "architecture": ["string"],
  "tasks": [
    {{
      "id": "task-1",
      "title": "string",
      "description": "string",
      "files": ["string"],
      "dependencies": ["string"],
      "complexity": "low|medium|high",
      "acceptance_criteria": ["string"]
    }}
  ],
  "estimated_effort": "string",
  "risks": ["string"]
}}

Be specific and actionable. Include file paths, function names, and key implementation details."""


class PlannerAgent(BaseAgent):
    agent_name: str = "planner"
    agent_description: str = "Task breakdown and implementation planning"
    system_prompt_template: str = PLANNER_SYSTEM_PROMPT

    def __init__(self, config: Optional[AgentConfig] = None):
        super().__init__(config or AgentConfig(temperature=0.3, model="gpt-4o"))

    async def validate_input(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        if "prompt" not in input_data and "requirement" not in input_data:
            raise AgentError(
                self.agent_name,
                "Input must contain 'prompt' or 'requirement' field",
            )
        requirement = input_data.get("prompt") or input_data.get("requirement", "")
        if not isinstance(requirement, str) or len(requirement.strip()) < 10:
            raise AgentError(
                self.agent_name,
                "Requirement must be at least 10 characters",
            )
        return {"requirement": requirement.strip(), "context": input_data.get("context", "")}

    async def parse_output(self, raw_output: str, input_data: Dict[str, Any]) -> Dict[str, Any]:
        parsed = self._extract_json(raw_output)
        if not parsed:
            return {"plan_text": raw_output, "tasks": self._extract_tasks_fallback(raw_output)}
        if "tasks" not in parsed or not isinstance(parsed["tasks"], list):
            raise AgentError(self.agent_name, "Plan missing 'tasks' array")
        return {
            "overview": parsed.get("overview", ""),
            "architecture": parsed.get("architecture", []),
            "tasks": parsed["tasks"],
            "estimated_effort": parsed.get("estimated_effort", ""),
            "risks": parsed.get("risks", []),
            "raw": parsed,
        }

    async def process(self, input_data: Dict[str, Any]) -> Dict[str, Any]:
        validated = await self.validate_input(input_data)
        requirement = validated["requirement"]
        context = validated["context"]

        prompt = f"Create an implementation plan for:\n\n{requirement}\n"
        if context:
            prompt += f"\nAdditional context:\n{context}\n"

        messages = self._build_messages(prompt, {"requirement": requirement})
        raw = await self._call_with_retry(messages, self._make_step())
        return await self.parse_output(raw, input_data)

    def _make_step(self):
        from app.services.agents.base_agent import AgentStep
        return AgentStep(agent=self.agent_name, action="plan", input_data={})

    def _extract_tasks_fallback(self, text: str) -> List[Dict[str, Any]]:
        tasks = []
        lines = text.split("\n")
        current_task = {}
        for line in lines:
            import re
            m = re.match(r"^(?:#+\s*)?(?:Task\s*)?(\d+(?:\.\d+)?)[.:)]\s+(.+)", line.strip())
            if m:
                if current_task:
                    tasks.append(current_task)
                current_task = {
                    "id": f"task-{m.group(1)}",
                    "title": m.group(2).strip(),
                    "description": "",
                    "files": [],
                    "complexity": "medium",
                }
            elif current_task and line.strip():
                current_task["description"] = (current_task.get("description", "") + " " + line.strip()).strip()[:500]
        if current_task:
            tasks.append(current_task)
        return tasks
