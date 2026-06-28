from __future__ import annotations

import os
from pathlib import Path
from typing import Any

from mcp.server import NotificationOptions, Server
from mcp.server.models import InitializationOptions
import structlog

from config import Settings

logger = structlog.get_logger(__name__)


def register_files_tools(server: Server, settings: Settings) -> None:
    @server.list_resources
    async def handle_list_resources() -> list[dict[str, Any]]:
        resources = []
        for name, path in settings.project_paths.model_dump().items():
            full_path = Path(__file__).parent.parent.parent / path
            if full_path.exists():
                for root, dirs, files in os.walk(full_path):
                    for file in files:
                        if file.endswith(('.py', '.json', '.md', '.php', '.js', '.ts')):
                            rel_path = Path(root).relative_to(full_path) / file
                            resources.append({
                                "uri": f"file://{name}/{rel_path}",
                                "name": f"{name}/{rel_path}",
                                "mimeType": "text/plain"
                            })
        return resources

    @server.read_resource
    async def handle_read_resource(uri: str) -> str:
        if uri.startswith("file://"):
            parts = uri[7:].split("/", 1)
            if len(parts) == 2:
                project, rel_path = parts
                base_path = Path(__file__).parent.parent.parent
                full_path = base_path / settings.project_paths.model_dump().get(project, project) / rel_path
                if full_path.exists():
                    return full_path.read_text()
        return ""

    @server.call_tool
    async def handle_call_tool(name: str, arguments: dict[str, Any]) -> str:
        if name == "list_files":
            path = arguments.get("path", ".")
            full_path = Path(__file__).parent.parent.parent / path
            if full_path.exists():
                files = list(full_path.rglob("*"))
                return "\n".join(str(f.relative_to(full_path)) for f in files if f.is_file())
            return f"Path not found: {path}"
        return f"Unknown tool: {name}"