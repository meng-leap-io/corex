from __future__ import annotations

import json
from pathlib import Path
from typing import Any

from mcp.server import Server
import structlog

logger = structlog.get_logger(__name__)


def register_context_tools(server: Server, settings: Any) -> None:
    @server.call_tool
    async def handle_call_tool(name: str, arguments: dict[str, Any]) -> str:
        if name == "read_project_context":
            path = arguments.get("path", ".")
            full_path = Path(__file__).parent.parent.parent / path
            if full_path.exists() and full_path.is_dir():
                context = {"files": [], "structure": {}}
                for f in full_path.rglob("*.py"):
                    if "agent-mcp" not in str(f):
                        context["files"].append(str(f.relative_to(full_path)))
                return json.dumps(context, indent=2)
            return f"Path not found: {path}"
        
        if name == "review_code":
            path = arguments.get("path")
            review = {
                "path": path,
                "issues": [],
                "recommendations": []
            }
            if path:
                full_path = Path(__file__).parent.parent.parent / path
                if full_path.exists():
                    content = full_path.read_text()
                    if "TODO" in content:
                        review["issues"].append("Contains TODO comments")
                    if "FIXME" in content:
                        review["issues"].append("Contains FIXME comments")
                    if len(content.split("\n")) > 500:
                        review["recommendations"].append("Large file may need refactoring")
            return json.dumps(review, indent=2)
        
        return f"Unknown tool: {name}"