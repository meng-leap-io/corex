from __future__ import annotations

import json
from pathlib import Path
from typing import Any

from mcp.server import Server
import structlog

logger = structlog.get_logger(__name__)


def init_messages_file(file_path: str) -> None:
    full_path = Path(__file__).parent.parent.parent / file_path
    full_path.parent.mkdir(parents=True, exist_ok=True)
    if not full_path.exists():
        full_path.write_text("[]")


def register_messages_tools(server: Server, file_path: str) -> None:
    init_messages_file(file_path)
    full_path = Path(__file__).parent.parent.parent / file_path

    @server.call_tool
    async def handle_call_tool(name: str, arguments: dict[str, Any]) -> str:
        if name == "send_message":
            messages = json.loads(full_path.read_text())
            message = {
                "from": arguments["from_agent"],
                "to": arguments["to_agent"],
                "content": arguments["content"],
                "timestamp": str(Path(__file__).stat().st_mtime)
            }
            messages.append(message)
            full_path.write_text(json.dumps(messages, indent=2))
            logger.info("Message sent", **message)
            return f"Message sent from {arguments['from_agent']} to {arguments['to_agent']}"
        
        if name == "get_messages":
            messages = json.loads(full_path.read_text())
            agent = arguments.get("agent")
            if agent:
                filtered = [m for m in messages if m.get("to") == agent or m.get("from") == agent]
                return json.dumps(filtered, indent=2)
            return json.dumps(messages, indent=2)
        
        return f"Unknown tool: {name}"