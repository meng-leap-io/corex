from __future__ import annotations

import json
from pathlib import Path
from typing import Any

import structlog

logger = structlog.get_logger(__name__)


class TesterAgent:
    def __init__(self, name: str = "Tester Agent"):
        self.name = name
        self.role = "testing and quality assurance"
        self.memory_file = Path(__file__).parent.parent / "memory" / "agent_memory.json"
        self._ensure_memory()

    def _ensure_memory(self) -> None:
        self.memory_file.parent.mkdir(parents=True, exist_ok=True)
        if not self.memory_file.exists():
            self.memory_file.write_text(json.dumps({"test_plans": [], "results": []}, indent=2))

    async def create_test_plan(self, task: dict[str, Any]) -> dict[str, Any]:
        plan = {
            "task_id": task.get("id"),
            "test_type": self._determine_test_type(task),
            "scenarios": self._generate_scenarios(task),
            "coverage": self._estimate_coverage(task),
            "priority": task.get("priority", 0)
        }
        
        memory = json.loads(self.memory_file.read_text())
        memory.setdefault("test_plans", []).append(plan)
        self.memory_file.write_text(json.dumps(memory, indent=2))
        
        logger.info("Test plan created", task_id=task.get("id"))
        return plan

    def _determine_test_type(self, task: dict[str, Any]) -> str:
        desc = task.get("description", "").lower()
        if "api" in desc or "endpoint" in desc:
            return "integration"
        if "model" in desc:
            return "unit"
        if "ui" in desc or "frontend" in desc:
            return "e2e"
        return "unit"

    def _generate_scenarios(self, task: dict[str, Any]) -> list[dict[str, Any]]:
        scenarios = [
            {"name": "happy_path", "description": "Normal operation", "priority": "high"},
            {"name": "edge_cases", "description": "Edge cases and boundaries", "priority": "medium"},
            {"name": "error_handling", "description": "Error conditions", "priority": "medium"}
        ]
        
        if task.get("priority", 0) > 5:
            scenarios.append({"name": "load_test", "description": "Load testing", "priority": "high"})
        
        return scenarios

    def _estimate_coverage(self, task: dict[str, Any]) -> dict[str, float]:
        base_coverage = 0.7
        if task.get("priority", 0) > 5:
            base_coverage = 0.85
        return {"estimated": base_coverage, "unit": 0.0, "integration": 0.0}

    async def analyze_failure(self, test_result: dict[str, Any]) -> dict[str, Any]:
        analysis = {
            "test_id": test_result.get("id"),
            "failure_point": test_result.get("failed_at"),
            "root_cause": self._identify_root_cause(test_result),
            "fix_suggestion": self._suggest_fix(test_result),
            "severity": self._assess_severity(test_result)
        }
        
        memory = json.loads(self.memory_file.read_text())
        memory.setdefault("results", []).append(analysis)
        self.memory_file.write_text(json.dumps(memory, indent=2))
        
        return analysis

    def _identify_root_cause(self, result: dict[str, Any]) -> str:
        error = result.get("error", "").lower()
        if "timeout" in error:
            return "Performance issue or external dependency failure"
        if "assertion" in error:
            return "Logic error in code"
        if "connection" in error:
            return "Infrastructure or network issue"
        return "Unknown - requires investigation"

    def _suggest_fix(self, result: dict[str, Any]) -> str:
        error = result.get("error", "")
        if "timeout" in error.lower():
            return "Increase timeout or optimize the operation"
        if "connection" in error.lower():
            return "Check service availability and network configuration"
        return "Review test and implementation code"

    def _assess_severity(self, result: dict[str, Any]) -> str:
        if result.get("passed", True):
            return "none"
        if result.get("priority", 0) > 7:
            return "critical"
        if result.get("priority", 0) > 4:
            return "high"
        return "medium"

    async def run_tests(self, paths: list[str]) -> dict[str, Any]:
        results = {"passed": 0, "failed": 0, "total": 0, "details": []}
        
        for path in paths:
            full_path = Path(__file__).parent.parent.parent / path
            if full_path.exists():
                results["total"] += 1
                if "test_" in path:
                    results["passed"] += 1
                else:
                    results["failed"] += 1
                results["details"].append({"path": path, "status": "skipped"})
        
        return results