from __future__ import annotations

import sqlite3
from pathlib import Path
from typing import Any

from mcp.server import Server
import structlog

logger = structlog.get_logger(__name__)


def init_tasks_db(db_path: str) -> sqlite3.Connection:
    full_path = Path(__file__).parent.parent.parent / db_path
    full_path.parent.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(str(full_path))
    conn.execute("""
        CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            agent_id TEXT NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            status TEXT DEFAULT 'pending',
            priority INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    """)
    return conn


def register_tasks_tools(server: Server, db_path: str) -> None:
    conn = init_tasks_db(db_path)

    @server.call_tool
    async def handle_call_tool(name: str, arguments: dict[str, Any]) -> str:
        if name == "create_task":
            cursor = conn.cursor()
            cursor.execute(
                "INSERT INTO tasks (agent_id, title, description, priority) VALUES (?, ?, ?, ?)",
                (arguments.get("agent_id", "unknown"), arguments["title"], 
                 arguments.get("description", ""), arguments.get("priority", 0))
            )
            conn.commit()
            return f"Task created with id: {cursor.lastrowid}"
        
        if name == "update_task":
            cursor = conn.cursor()
            cursor.execute(
                "UPDATE tasks SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                (arguments["status"], arguments["task_id"])
            )
            conn.commit()
            return f"Task {arguments['task_id']} updated"
        
        if name == "list_tasks":
            cursor = conn.cursor()
            cursor.execute("SELECT * FROM tasks ORDER BY priority DESC, created_at DESC")
            tasks = cursor.fetchall()
            return "\n".join(f"[{t[0]}] {t[2]} - {t[4]}" for t in tasks)
        
        return f"Unknown tool: {name}"