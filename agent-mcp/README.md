# MCP Agent Team

A multi-agent system for collaborative development using the MCP (Model Context Protocol).

## Architecture

```
agent-mcp/
├── mcp-server/     # MCP protocol server
├── agents/         # Specialized AI agents
├── memory/         # Shared state storage
└── config/         # Configuration
```

## Agents

| Agent | Role | Capabilities |
|-------|------|--------------|
| **Architect** | Architecture planning | Project analysis, design patterns, tech stack decisions |
| **Developer** | Code implementation | Task execution, code generation, permission workflow |
| **Reviewer** | Code quality | Security checks, bug detection, code standards |
| **Tester** | Quality assurance | Test planning, failure analysis, coverage estimation |

## MCP Tools

- `list_files` - List files in directories
- `get_file_content` - Read file contents
- `create_task` - Create tasks in shared queue
- `update_task` - Update task status
- `send_message` - Agent-to-agent communication
- `get_messages` - Retrieve messages
- `read_project_context` - Analyze project structure
- `review_code` - Static code analysis

## Quick Start

```bash
# Install dependencies
pip install -r requirements.txt

# Run MCP server
python -m mcp_server.server

# Or with Docker
docker build -t agent-mcp .
docker run -p 8080:8080 agent-mcp
```

## Integration

### OpenCode MCP Config
```json
{
  "mcpServers": {
    "agent-team": {
      "command": "python",
      "args": ["-m", "mcp_server.server"],
      "cwd": "/path/to/agent-mcp"
    }
  }
}
```

### Kilo AI Connection
The MCP server exposes a stdio interface compatible with Kilo AI's MCP client for agent communication.