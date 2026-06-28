from __future__ import annotations

import json
from pathlib import Path
from typing import Any

from pydantic import BaseModel
from pydantic_settings import BaseSettings


class ServerConfig(BaseModel):
    host: str = "0.0.0.0"
    port: int = 8080
    log_level: str = "INFO"


class ProjectPaths(BaseModel):
    backend: str = "../backend"
    frontend: str = "../frontend"
    ai_gateway: str = "../ai-gateway"


class MemoryConfig(BaseModel):
    tasks_db: str = "memory/tasks.db"
    messages_file: str = "memory/messages.json"
    agent_memory_file: str = "memory/agent_memory.json"


class AgentConfig(BaseModel):
    name: str
    model: str
    role: str


class MCPConfig(BaseModel):
    protocol_version: str = "2024-11-05"
    capabilities: list[str] = ["tools", "resources", "prompts"]


class Settings(BaseSettings):
    server: ServerConfig = ServerConfig()
    project_paths: ProjectPaths = ProjectPaths()
    memory: MemoryConfig = MemoryConfig()
    agents: dict[str, AgentConfig] = {}
    mcp: MCPConfig = MCPConfig()

    class Config:
        env_file = ".env"
        env_file_encoding = "utf-8"


def load_config() -> Settings:
    config_path = Path(__file__).parent.parent / "config" / "mcp.json"
    if config_path.exists():
        with open(config_path) as f:
            data = json.load(f)
        return Settings(**data)
    return Settings()