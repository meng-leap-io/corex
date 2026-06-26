FROM python:3.12-slim AS builder

WORKDIR /build

RUN apt-get update && apt-get install -y --no-install-recommends \
    gcc python3-dev libpq-dev \
    && rm -rf /var/lib/apt/lists/*

COPY ai-gateway/requirements.txt .
RUN pip install --no-cache-dir --user --no-warn-script-location \
    -r requirements.txt

FROM python:3.12-slim AS runtime

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl libpq5 ca-certificates \
    && rm -rf /var/lib/apt/lists/*

COPY --from=builder /root/.local /root/.local

ENV PATH=/root/.local/bin:$PATH \
    PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PYTHONFAULTHANDLER=1 \
    PYTHONHASHSEED=random

RUN groupadd -r appgroup && useradd -r -g appgroup -m -u 1000 appuser \
    && mkdir -p /app/logs /app/tmp && chown -R appuser:appgroup /app

COPY --chown=appuser:appgroup ai-gateway/ .

USER appuser
EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD curl -f http://localhost:8000/health || exit 1

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000", "--workers", "4", "--limit-max-requests", "10000"]

FROM runtime AS dev
USER root
RUN pip install --no-cache-dir pytest pytest-asyncio httpx watchdog
USER appuser
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000", "--reload"]
