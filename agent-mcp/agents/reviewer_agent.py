from __future__ import annotations

import json
from pathlib import Path
from typing import Any

import structlog

logger = structlog.get_logger(__name__)


class ReviewerAgent:
    def __init__(self, name: str = "Reviewer Agent"):
        self.name = name
        self.role = "code review and security"
        self.memory_file = Path(__file__).parent.parent / "memory" / "agent_memory.json"
        self._ensure_memory()

    def _ensure_memory(self) -> None:
        self.memory_file.parent.mkdir(parents=True, exist_ok=True)
        if not self.memory_file.exists():
            self.memory_file.write_text(json.dumps({"reviews": [], "bugs_found": 0}, indent=2))

    async def review_code(self, path: str) -> dict[str, Any]:
        full_path = Path(__file__).parent.parent.parent / path
        if not full_path.exists():
            return {"error": f"Path not found: {path}"}
        
        content = full_path.read_text()
        issues = self._analyze_content(content, path)
        
        review = {
            "path": path,
            "issues": issues,
            "security_checks": self._security_check(content),
            "quality_score": self._calculate_score(issues),
            "timestamp": str(Path(__file__).stat().st_mtime)
        }
        
        memory = json.loads(self.memory_file.read_text())
        memory.setdefault("reviews", []).append(review)
        memory["bugs_found"] = memory.get("bugs_found", 0) + len([i for i in issues if i["severity"] == "high"])
        self.memory_file.write_text(json.dumps(memory, indent=2))
        
        logger.info("Code reviewed", path=path, issues=len(issues))
        return review

    def _analyze_content(self, content: str, path: str) -> list[dict[str, Any]]:
        issues = []
        lines = content.split("\n")
        
        for i, line in enumerate(lines, 1):
            if "TODO" in line or "FIXME" in line:
                issues.append({
                    "line": i,
                    "type": "todo",
                    "severity": "medium",
                    "message": line.strip()
                })
            
            if "password" in line.lower() and "=" in line:
                issues.append({
                    "line": i,
                    "type": "security",
                    "severity": "high",
                    "message": "Potential hardcoded password"
                })
            
            if "print(" in line and "debug" in line.lower():
                issues.append({
                    "line": i,
                    "type": "debug",
                    "severity": "low",
                    "message": "Debug print statement found"
                })
        
        return issues

    def _security_check(self, content: str) -> dict[str, bool]:
        return {
            "no_hardcoded_secrets": "password =" not in content.lower(),
            "no_sql_injection": "DB::raw" not in content and "execute(" not in content,
            "uses_parameterized_queries": "?" in content or ":" in content
        }

    def _calculate_score(self, issues: list[dict]) -> int:
        score = 100
        for issue in issues:
            if issue["severity"] == "high":
                score -= 10
            elif issue["severity"] == "medium":
                score -= 5
            else:
                score -= 1
        return max(0, score)

    async def review_task(self, task: dict[str, Any]) -> dict[str, Any]:
        return {
            "task_id": task.get("id"),
            "review": "approved" if task.get("status") != "blocked" else "needs_revision",
            "comments": self._generate_review_comments(task)
        }

    def _generate_review_comments(self, task: dict[str, Any]) -> list[str]:
        comments = []
        if task.get("priority", 0) > 5:
            comments.append("High priority task - ensure adequate testing")
        if "security" in task.get("description", "").lower():
            comments.append("Security-sensitive - apply OWASP guidelines")
        return comments