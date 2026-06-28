"""Corex.dev AI Gateway - Production FastAPI Application

Windows-aware: handles cross-platform path differences, Event Logging,
and local Ollama integration for offline AI operations.
"""

from __future__ import annotations

import logging
import os
import sys
import time
import uuid
from contextlib import asynccontextmanager
from typing import Any, Dict

import structlog
from fastapi import FastAPI, HTTPException, Request, Response, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.middleware.trustedhost import TrustedHostMiddleware
from fastapi.responses import JSONResponse
from prometheus_client import generate_latest, CONTENT_TYPE_LATEST
from pydantic import BaseModel, Field

from app.core.windows import (
    IS_WINDOWS,
    get_hostname,
    get_prometheus_multiproc_dir,
    write_event_log,
    set_high_performance,
    configure_iocp,
)

# Configure Windows-specific optimizations on startup
if IS_WINDOWS:
    set_high_performance()
    configure_iocp()
    # Set Prometheus multiprocess directory to a Windows-compatible path
    os.environ.setdefault(
        "PROMETHEUS_MULTIPROC_DIR",
        str(get_prometheus_multiproc_dir()),
    )

from app.middleware.security_middleware import (
    InputValidationMiddleware,
    InputSanitizationMiddleware,
    OutputFilterMiddleware,
    SecurityHeadersMiddleware,
    RateLimitMiddleware,
)
from app.core.health import (
    HealthRegistry,
    ServiceStatus,
    health_registry,
    check_redis,
    check_memory,
    check_disk,
    check_uptime,
    check_ollama,
    check_database,
    get_health_response,
)
from app.services.agents.orchestrator import AgentOrchestrator
from app.services.agents.base_agent import AgentError
from app.services.agents.workflows import WORKFLOW_REGISTRY, WORKFLOW_EXAMPLES, get_workflow

# ---------------------------------------------------------------------------
# Structured logging
# ---------------------------------------------------------------------------

def configure_structlog() -> None:
    """Configure structlog with Windows Event Log support."""
    processors = [
        structlog.stdlib.filter_by_level,
        structlog.processors.TimeStamper(fmt="iso"),
        structlog.processors.add_log_level,
        structlog.processors.StackInfoRenderer(),
        structlog.processors.format_exc_info,
        structlog.processors.JSONRenderer(),
    ]

    # On Windows, also write errors to the Event Log
    if IS_WINDOWS:
        from app.core.windows import write_event_log

        def event_log_processor(logger, method_name, event_dict):
            level = event_dict.get("level", "info")
            message = event_dict.get("event", "")
            write_event_log(message, level=level)
            return event_dict

        processors.insert(0, event_log_processor)

    structlog.configure(
        processors=processors,
        wrapper_class=structlog.make_filtering_bound_logger(logging.INFO),
        context_class=dict,
        logger_factory=structlog.PrintLoggerFactory(),
    )


configure_structlog()

logger = structlog.get_logger()

# ---------------------------------------------------------------------------
# Process metadata (Windows-compatible)
# ---------------------------------------------------------------------------
PROCESS_START_TIME = time.time()
HOSTNAME = get_hostname()

# ---------------------------------------------------------------------------
# Settings
# ---------------------------------------------------------------------------
from app.core.config import settings

# ---------------------------------------------------------------------------
# Sentry initialization
# ---------------------------------------------------------------------------
def init_sentry():
    dsn = settings.sentry_dsn or os.getenv("SENTRY_DSN")
    if not dsn:
        logger.info("sentry_disabled", reason="no_dsn")
        return
    try:
        import sentry_sdk
        from sentry_sdk.integrations.fastapi import FastApiIntegration
        from sentry_sdk.integrations.structlog import StructlogIntegration

        sentry_sdk.init(
            dsn=dsn,
            environment=settings.environment.value,
            release="1.0.0",
            sample_rate=1.0,
            traces_sample_rate=0.25,
            profiles_sample_rate=0.1,
            send_default_pii=False,
            integrations=[
                FastApiIntegration(transaction_style="endpoint"),
                StructlogIntegration(),
            ],
            ignore_errors=[ValueError],
            before_send=lambda event, _: (
                None if settings.environment == "development" else event
            ),
        )
        logger.info("sentry_initialized")
    except Exception as e:
        logger.warning("sentry_init_failed", error=str(e))


# ---------------------------------------------------------------------------
# Lifespan
# ---------------------------------------------------------------------------
@asynccontextmanager
async def lifespan(app: FastAPI):
    init_sentry()

    logger.info("agent_system_initializing")
    from app.services.agents.planner_agent import PlannerAgent
    from app.services.agents.coder_agent import CoderAgent
    from app.services.agents.tester_agent import TesterAgent
    from app.services.agents.reviewer_agent import ReviewerAgent
    from app.services.agents.debugger_agent import DebuggerAgent
    from app.services.agents.documentation_agent import DocumentationAgent
    from app.services.agents.security_agent import SecurityAgent

    agent_orchestrator.register_agent("planner", PlannerAgent())
    agent_orchestrator.register_agent("coder", CoderAgent())
    agent_orchestrator.register_agent("tester", TesterAgent())
    agent_orchestrator.register_agent("reviewer", ReviewerAgent())
    agent_orchestrator.register_agent("debugger", DebuggerAgent())
    agent_orchestrator.register_agent("documentation", DocumentationAgent())
    agent_orchestrator.register_agent("security", SecurityAgent())
    logger.info("agent_system_initialized", agents=len(agent_orchestrator._agents))

    health_registry.register("redis", check_redis)
    health_registry.register("memory", check_memory)
    health_registry.register("disk", check_disk)
    health_registry.register("uptime", lambda: check_uptime(PROCESS_START_TIME))

    if settings.ollama_enabled:
        health_registry.register("ollama", check_ollama)

    logger.info("health_checks_registered", count=len(health_registry._checks))

    # Initialize Supabase connection pool
    try:
        from app.core.supabase import supabase_pool
        from app.db.migrations import run_migrations, run_indexes

        await supabase_pool.connect()
        health_registry.register("supabase", check_database)

        # Auto-migrate tables and indexes on startup
        await run_migrations()
        await run_indexes()

        logger.info("supabase_initialized")
    except Exception as e:
        logger.warning("supabase_init_failed", error=str(e))

    if IS_WINDOWS:
        write_event_log("AI Gateway started", level="info", event_id=100)
        logger.info("windows_event_log_initialized")

    yield

    # Gracefully close Supabase pool
    try:
        from app.core.supabase import supabase_pool
        await supabase_pool.close()
        logger.info("supabase_pool_closed")
    except Exception as e:
        logger.warning("supabase_close_error", error=str(e))

    if IS_WINDOWS:
        write_event_log("AI Gateway shutting down", level="info", event_id=200)


agent_orchestrator = AgentOrchestrator()

app = FastAPI(
    title="Corex AI Gateway",
    version="1.0.0",
    lifespan=lifespan,
    docs_url=None if settings.environment != Environment.DEVELOPMENT else "/docs",
    redoc_url=None if settings.environment != Environment.DEVELOPMENT else "/redoc",
)

app.add_middleware(
    TrustedHostMiddleware,
    allowed_hosts=[
        "corex.dev",
        "*.corex.dev",
        "localhost",
        "127.0.0.1",
        "ai-gateway",
    ],
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.cors_origins,
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "PATCH", "OPTIONS"],
    allow_headers=["*"],
    expose_headers=["X-Request-ID", "X-Request-Time"],
    max_age=600,
)

app.add_middleware(OutputFilterMiddleware)
app.add_middleware(RateLimitMiddleware)
app.add_middleware(InputSanitizationMiddleware)
app.add_middleware(InputValidationMiddleware)
app.add_middleware(SecurityHeadersMiddleware)


# ---------------------------------------------------------------------------
# Middleware: Request ID
# ---------------------------------------------------------------------------
@app.middleware("http")
async def add_request_id(request: Request, call_next):
    request_id = request.headers.get("X-Request-ID") or str(uuid.uuid4())
    start_time = time.time()
    response: Response = await call_next(request)
    elapsed = (time.time() - start_time) * 1000
    response.headers["X-Request-ID"] = request_id
    response.headers["X-Request-Time"] = f"{elapsed:.0f}ms"
    return response


# ---------------------------------------------------------------------------
# Global exception handlers
# ---------------------------------------------------------------------------
@app.exception_handler(ValueError)
async def value_error_handler(request: Request, exc: ValueError):
    logger.error("value_error", error=str(exc), path=str(request.url))
    return JSONResponse(
        status_code=status.HTTP_400_BAD_REQUEST,
        content={"detail": "Invalid input provided."},
    )


@app.exception_handler(HTTPException)
async def http_exception_handler(request: Request, exc: HTTPException):
    logger.warning(
        "http_exception",
        status_code=exc.status_code,
        detail=exc.detail,
        path=str(request.url),
    )
    return JSONResponse(
        status_code=exc.status_code,
        content={"detail": exc.detail},
    )


@app.exception_handler(Exception)
async def global_exception_handler(request: Request, exc: Exception):
    logger.error("unhandled_exception", error=str(exc), path=str(request.url))
    return JSONResponse(
        status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
        content={
            "detail": "An internal server error occurred.",
            "request_id": request.headers.get("X-Request-ID", ""),
        },
    )


# ---------------------------------------------------------------------------
# Health, Ready, Metrics, Root
# ---------------------------------------------------------------------------

@app.get("/health", tags=["observability"])
async def health_check(request: Request):
    results = await health_registry.run_all(use_cache=True)
    overall, failed = await health_registry.get_overall_status(results)
    status_code = 200 if overall == ServiceStatus.HEALTHY else 503

    return JSONResponse(
        content=get_health_response(results, overall, failed),
        status_code=status_code,
        headers={"X-Request-ID": request.headers.get("X-Request-ID", "")},
    )


@app.get("/ready", tags=["observability"])
async def readiness_check():
    return {
        "status": "ready",
        "service": "ai-gateway",
        "platform": "windows" if IS_WINDOWS else "linux",
        "uptime_seconds": int(time.time() - PROCESS_START_TIME),
    }


@app.get("/metrics", tags=["observability"])
async def metrics():
    return Response(
        content=generate_latest(),
        media_type=CONTENT_TYPE_LATEST,
    )


@app.get("/")
async def root():
    return {
        "message": "Corex AI Gateway",
        "version": "1.0.0",
        "hostname": HOSTNAME,
        "platform": "windows" if IS_WINDOWS else "linux",
        "uptime_seconds": int(time.time() - PROCESS_START_TIME),
        "local_models": settings.ollama_enabled,
    }


@app.post("/v1/chat/completions")
async def chat_completions(request: Request):
    body = await request.json()
    logger.info("chat_completion_request", model=body.get("model"))

    from app.services.ai_router import ai_router

    try:
        result = await ai_router.chat_completion_raw(body)
        return JSONResponse(content=result)
    except Exception as e:
        logger.error("chat_completion_failed", error=str(e))
        return JSONResponse(
            status_code=502,
            content={
                "error": {
                    "message": "AI provider error",
                    "type": "provider_error",
                }
            },
        )


@app.post("/v1/embeddings")
async def create_embeddings(request: Request):
    body = await request.json()
    logger.info("embedding_request", model=body.get("model"))
    return {"data": [], "model": body.get("model"), "usage": {"prompt_tokens": 0, "total_tokens": 0}}


# ---------------------------------------------------------------------------
# Agent System - Request/Response Models
# ---------------------------------------------------------------------------

class AgentExecuteRequest(BaseModel):
    workflow: str = Field(..., description="Workflow name to execute")
    input: Dict[str, Any] = Field(..., description="Input data for the workflow")
    run_id: str = Field(default="", description="Optional existing run ID to resume")


class AgentExecuteResponse(BaseModel):
    run_id: str
    workflow: str
    status: str
    steps: int
    errors: list
    duration_ms: float
    output: Dict[str, Any]


# ---------------------------------------------------------------------------
# Agent System - API Endpoints
# ---------------------------------------------------------------------------

@app.exception_handler(AgentError)
async def agent_error_handler(request: Request, exc: AgentError):
    logger.error("agent_error", agent=exc.agent, error=str(exc))
    return JSONResponse(
        status_code=status.HTTP_400_BAD_REQUEST,
        content={"error": {"agent": exc.agent, "message": str(exc), "recoverable": exc.recoverable}},
    )


@app.post("/v1/agent/execute")
async def agent_execute(body: AgentExecuteRequest, request: Request):
    logger.info(
        "agent_execute_request",
        workflow=body.workflow,
        run_id=body.run_id or "new",
    )

    workflow_def = get_workflow(body.workflow)
    start_time = time.time()

    state = await agent_orchestrator.execute_workflow(
        workflow=workflow_def,
        input_data=body.input,
        run_id=body.run_id or None,
    )

    duration = (time.time() - start_time) * 1000

    return AgentExecuteResponse(
        run_id=state.run_id,
        workflow=body.workflow,
        status=state.status,
        steps=len(state.steps),
        errors=state.errors,
        duration_ms=round(duration, 2),
        output=state.artifacts,
    )


@app.get("/v1/agent/workflows")
async def list_workflows():
    return {
        "workflows": list(WORKFLOW_REGISTRY.keys()),
        "examples": WORKFLOW_EXAMPLES,
    }


@app.get("/v1/agent/workflows/{workflow_name}")
async def get_workflow_detail(workflow_name: str):
    try:
        wf = get_workflow(workflow_name)
        return {"workflow": wf.to_dict()}
    except ValueError as e:
        raise HTTPException(status_code=404, detail=str(e))


@app.get("/v1/agent/runs")
async def list_runs():
    return {"runs": agent_orchestrator.list_states()}


@app.get("/v1/agent/runs/{run_id}")
async def get_run(run_id: str):
    state = agent_orchestrator.get_state(run_id)
    if not state:
        raise HTTPException(status_code=404, detail=f"Run '{run_id}' not found")
    return {
        "run_id": state.run_id,
        "workflow": state.workflow_name,
        "status": state.status,
        "steps": [
            {
                "agent": s.agent,
                "action": s.action,
                "status": s.status,
                "duration_ms": s.duration_ms,
                "tokens_used": s.tokens_used,
                "error": s.error,
            }
            for s in state.steps
        ],
        "errors": state.errors,
        "output": state.artifacts,
    }


# ---------------------------------------------------------------------------
# Ollama Management Endpoints (Windows/local)
# ---------------------------------------------------------------------------
from pydantic import BaseModel as PydanticBaseModel


class PullModelRequest(PydanticBaseModel):
    model: str = Field(..., min_length=1)


class PullModelResponse(PydanticBaseModel):
    success: bool
    model: str
    message: str


@app.post("/v1/ollama/pull", tags=["ollama"])
async def ollama_pull(body: PullModelRequest):
    """Pull a model from Ollama's registry to local."""
    from app.services.providers.ollama import OllamaProvider

    provider = OllamaProvider()
    try:
        result = await provider.pull_model(body.model)
        return PullModelResponse(
            success=True,
            model=body.model,
            message=result.get("status", "pulled"),
        )
    except Exception as e:
        return PullModelResponse(
            success=False,
            model=body.model,
            message=str(e),
        )


@app.get("/v1/ollama/models", tags=["ollama"])
async def ollama_models():
    """List all available local Ollama models."""
    from app.services.providers.ollama import OllamaProvider

    provider = OllamaProvider()
    models = await provider.list_models()
    running = await provider.get_running_models()
    return {
        "models": models,
        "running": running,
        "enabled": settings.ollama_enabled,
        "ollama_running": len(models) > 0,
    }


@app.get("/v1/ollama/status", tags=["ollama"])
async def ollama_status():
    """Check Ollama health and running models."""
    from app.services.providers.ollama import OllamaProvider

    provider = OllamaProvider()
    healthy = await provider.check_health()
    running = await provider.get_running_models() if healthy else []
    return {
        "healthy": healthy,
        "running_models": running,
        "base_url": settings.ollama_base_url,
        "enabled": settings.ollama_enabled,
    }


if __name__ == "__main__":
    import uvicorn

    if IS_WINDOWS:
        # Windows requires asyncio event loop (uvloop not available)
        import asyncio
        asyncio.set_event_loop_policy(asyncio.WindowsProactorEventLoopPolicy())

    uvicorn.run(
        "main:app",
        host=settings.host,
        port=settings.port,
        workers=settings.workers,
        log_level=settings.log_level.value,
        reload=settings.is_development,
        loop="asyncio" if IS_WINDOWS else "uvloop",
        http="auto" if IS_WINDOWS else "httptools",
        limit_max_requests=10000,
    )
