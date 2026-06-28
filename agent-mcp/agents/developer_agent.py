from __future__ import annotations

import json
from pathlib import Path
from typing import Any

import structlog

logger = structlog.get_logger(__name__)


class DeveloperAgent:
    def __init__(self, name: str = "Developer Agent"):
        self.name = name
        self.role = "code implementation"
        self.memory_file = Path(__file__).parent.parent / "memory" / "agent_memory.json"
        self._ensure_memory()

    def _ensure_memory(self) -> None:
        self.memory_file.parent.mkdir(parents=True, exist_ok=True)
        if not self.memory_file.exists():
            self.memory_file.write_text(json.dumps({"tasks": [], "implemented": []}, indent=2))

    async def receive_task(self, task: dict[str, Any]) -> dict[str, Any]:
        logger.info("Task received", task_id=task.get("id"), title=task.get("title"))
        
        result = {
            "task_id": task.get("id"),
            "status": "analyzing",
            "suggestion": self._generate_suggestion(task),
            "requires_permission": True
        }
        
        memory = json.loads(self.memory_file.read_text())
        memory.setdefault("tasks", []).append(result)
        self.memory_file.write_text(json.dumps(memory, indent=2))
        
        return result

    def _generate_suggestion(self, task: dict[str, Any]) -> str:
        title = task.get("title", "")
        desc = task.get("description", "")
        
        suggestions = {
            "api": "Implement RESTful API endpoints with proper validation",
            "model": "Create Eloquent model with relationships and casts",
            "test": "Write pytest tests with mocked dependencies",
            "docker": "Update docker-compose.yml with new service configuration"
        }
        
        for key, suggestion in suggestions.items():
            if key in (title + desc).lower():
                return suggestion
        
        return "Review requirements and propose implementation approach"

    async def request_permission(self, task_id: int, changes: list[str]) -> dict[str, Any]:
        return {
            "task_id": task_id,
            "changes": changes,
            "status": "permission_requested",
            "message": f"Requesting permission to implement: {', '.join(changes)}"
        }

    async def implement(self, task_id: int, approved: bool) -> dict[str, Any]:
        if not approved:
            return {"task_id": task_id, "status": "cancelled", "reason": "Permission denied"}
        
        logger.info("Implementation started", task_id=task_id)
        return {"task_id": task_id, "status": "in_progress", "message": "Implementation started"}

    def get_status(self) -> dict[str, Any]:
        memory = json.loads(self.memory_file.read_text())
        return {
            "pending_tasks": len(memory.get("tasks", [])),
            "implemented_count": len(memory.get("implemented", []))
        }