FROM python:3.12-slim

WORKDIR /app

RUN pip install --no-cache-dir -r requirements.txt

COPY mcp-server/ /app/mcp-server/
COPY agents/ /app/agents/
COPY config/ /app/config/
COPY memory/ /app/memory/

EXPOSE 8080

CMD ["python", "-m", "mcp_server.server"]