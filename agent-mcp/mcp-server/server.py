from __future__ import annotations

import asyncio
import json
import sqlite3
from pathlib import Path
from typing import Any

from mcp.server import NotificationOptions, Server
from mcp.server.models import InitializationOptions
import structlog
import uvicorn

from config import Settings, load_config
from tools.files import register_files_tools
from tools.tasks import register_tasks_tools
from tools.messages import register_messages_tools
from tools.context import register_context_tools

logger = structlog.get_logger(__name__)


class MCPAgentServer:
    def __init__(self, settings: Settings):
        self.settings = settings
        self.server = Server("agent-mcp-server")
        self._setup_handlers()
        self._register_tools()

    def _setup_handlers(self) -> None:
        @self.server.initialize
        async def handle_initialize(options: InitializationOptions) -> dict[str, Any]:
            logger.info("Server initialized", options=options)
            return {
                "protocolVersion": self.settings.mcp.protocol_version,
                "capabilities": {"tools": {}, "resources": {}, "prompts": {}},
                "serverInfo": {"name": "agent-mcp-server", "version": "1.0.0"}
            }

        @self.server.list_tools
        async def handle_list_tools() -> list[dict[str, Any]]:
            return [
                {"name": "list_files", "description": "List files in a directory"},
                {"name": "get_file_content", "description": "Get file content"},
                {"name": "create_task", "description": "Create a new task"},
                {"name": "update_task", "description": "Update task status"},
                {"name": "send_message", "description": "Send message between agents"},
                {"name": "get_messages", "description": "Get messages for an agent"},
                {"name": "read_project_context", "description": "Read project context"},
                {"name": "review_code", "description": "Review code for issues"}
            ]

    def _register_tools(self) -> None:
        register_files_tools(self.server, self.settings)
        register_tasks_tools(self.server, self.settings.memory.tasks_db)
        register_messages_tools(self.server, self.settings.memory.messages_file)
        register_context_tools(self.server, self.settings)

    async def run(self) -> None:
        logger.info("Starting MCP Agent Server", port=self.settings.server.port)
        async with self.server.run_stdio():
            pass


async def main_async() -> None:
    settings = load_config()
    structlog.configure(
        processors=[
            structlog.processors.TimeStamper(fmt="iso"),
            structlog.processors.add_log_level,
            structlog.processors.JSONRenderer()
        ]
    )
    server = MCPAgentServer(settings)
    await server.run()


def main() -> None:
    asyncio.run(main_async())


if __name__ == "__main__":
    main()