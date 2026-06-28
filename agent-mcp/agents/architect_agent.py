from __future__ import annotations

import json
from pathlib import Path
from typing import Any

import structlog

logger = structlog.get_logger(__name__)


class ArchitectAgent:
    def __init__(self, name: str = "Architect Agent"):
        self.name = name
        self.role = "architecture planning and analysis"
        self.memory_file = Path(__file__).parent.parent / "memory" / "agent_memory.json"
        self._ensure_memory()

    def _ensure_memory(self) -> None:
        self.memory_file.parent.mkdir(parents=True, exist_ok=True)
        if not self.memory_file.exists():
            self.memory_file.write_text(json.dumps({"decisions": [], "plans": []}, indent=2))

    def read_project_structure(self, project_path: str) -> dict[str, Any]:
        path = Path(project_path)
        if not path.exists():
            return {"error": f"Path not found: {project_path}"}
        
        structure = {
            "files": [],
            "directories": [],
            "key_files": []
        }
        
        for item in path.rglob("*"):
            if "agent-mcp" in str(item):
                continue
            rel = item.relative_to(path)
            if item.is_file():
                structure["files"].append(str(rel))
                if item.suffix in [".py", ".php", ".js", ".ts"]:
                    structure["key_files"].append(str(rel))
            elif item.is_dir():
                structure["directories"].append(str(rel))
        
        logger.info("Project structure analyzed", path=project_path, file_count=len(structure["files"]))
        return structure

    def create_plan(self, project_path: str, requirements: list[str]) -> dict[str, Any]:
        structure = self.read_project_structure(project_path)
        plan = {
            "id": len(json.loads(self.memory_file.read_text()).get("plans", [])) + 1,
            "project": project_path,
            "requirements": requirements,
            "phases": [
                {"name": "Analysis", "status": "pending"},
                {"name": "Design", "status": "pending"},
                {"name": "Implementation", "status": "pending"},
                {"name": "Review", "status": "pending"}
            ],
            "structure": structure
        }
        
        memory = json.loads(self.memory_file.read_text())
        memory.setdefault("plans", []).append(plan)
        self.memory_file.write_text(json.dumps(memory, indent=2))
        
        logger.info("Plan created", plan_id=plan["id"])
        return plan

    def analyze_architecture(self, project_path: str) -> dict[str, Any]:
        structure = self.read_project_structure(project_path)
        analysis = {
            "languages": self._detect_languages(structure["files"]),
            "frameworks": self._detect_frameworks(structure["files"]),
            "architecture_style": self._infer_architecture(structure),
            "recommendations": []
        }
        
        if len(structure["directories"]) > 20:
            analysis["recommendations"].append("Consider modularizing the project")
        
        return analysis

    def _detect_languages(self, files: list[str]) -> dict[str, int]:
        langs = {}
        for f in files:
            ext = Path(f).suffix
            langs[ext] = langs.get(ext, 0) + 1
        return langs

    def _detect_frameworks(self, files: list[str]) -> list[str]:
        frameworks = []
        content = " ".join(files).lower()
        if "laravel" in content or "artisan" in content:
            frameworks.append("Laravel")
        if "fastapi" in content or "pydantic" in content:
            frameworks.append("FastAPI")
        if "react" in content or "vue" in content:
            frameworks.append("Frontend Framework")
        return frameworks

    def _infer_architecture(self, structure: dict[str, Any]) -> str:
        dirs = structure.get("directories", [])
        if "controllers" in str(dirs) and "models" in str(dirs):
            return "MVC"
        if "services" in str(dirs):
            return "Service-Oriented"
        return "Unknown"