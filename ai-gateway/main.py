"""Corex.dev AI Gateway - Production FastAPI Application"""

import logging
import os
import time
import uuid
from contextlib import asynccontextmanager
from typing import Any, Dict

import structlog
from fastapi import FastAPI, HTTPException, Request, Response, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.middleware.trustedhost import TrustedHostMiddleware
from fastapi.responses import JSONResponse, PlainTextResponse
from prometheus_client import generate_latest, CONTENT_TYPE_LATEST
from pydantic import BaseModel, Field
from pydantic_settings import BaseSettings

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
    get_health_response,
)
from app.services.agents.orchestrator import AgentOrchestrator
from app.services.agents.base_agent import AgentError
from app.services.agents.workflows import WORKFLOW_REGISTRY, WORKFLOW_EXAMPLES, get_workflow

# ---------------------------------------------------------------------------
# Structured logging
# ---------------------------------------------------------------------------
structlog.configure(
    processors=[
        structlog.stdlib.filter_by_level,
        structlog.processors.TimeStamper(fmt="iso"),
        structlog.processors.add_log_level,
        structlog.processors.StackInfoRenderer(),
        structlog.processors.format_exc_info,
        structlog.processors.JSONRenderer(),
    ],
    wrapper_class=structlog.make_filtering_bound_logger(logging.INFO),
    context_class=dict,
    logger_factory=structlog.PrintLoggerFactory(),
)

logger = structlog.get_logger()

# ---------------------------------------------------------------------------
# Process metadata
# ---------------------------------------------------------------------------
PROCESS_START_TIME = time.time()
HOSTNAME = os.uname().nodename


class Settings(BaseSettings):
    """Application settings loaded from environment variables."""
    environment: str = "production"
    log_level: str = "info"
    jwt_secret: str = ""
    redis_host: str = "redis"
    redis_password: str = ""
    openai_api_key: str = ""
    anthropic_api_key: str = ""
    sentry_dsn: str = ""
    prometheus_multiproc_dir: str = ""

    class Config:
        env_prefix = ""


settings = Settings()


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
            environment=settings.environment,
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
    logger.info("health_checks_registered", count=len(health_registry._checks))
    yield


agent_orchestrator = AgentOrchestrator()

# Initialize FastAPI app with security headers and CORS
app = FastAPI(
    title="Corex AI Gateway",
    version="1.0.0",
    lifespan=lifespan,
    docs_url=None if settings.environment == "production" else "/docs",
    redoc_url=None if settings.environment == "production" else "/redoc",
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
    allow_origins=[
        "https://corex.dev",
        "https://console.corex.dev",
        "http://localhost:3000",
        "http://localhost:8000",
    ],
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "PATCH", "OPTIONS"],
    allow_headers=["*"],
    expose_headers=["X-Request-ID", "X-Request-Time"],
    max_age=600,
)

# Security middleware (order matters: validate -> sanitize -> rate limit -> filter output)
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
    """Comprehensive health check for load balancers and monitoring."""
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
    """Readiness probe for Kubernetes - lightweight check."""
    return {"status": "ready", "service": "ai-gateway", "uptime_seconds": int(time.time() - PROCESS_START_TIME)}


@app.get("/metrics", tags=["observability"])
async def metrics():
    """Prometheus metrics endpoint."""
    from prometheus_client import generate_latest, CONTENT_TYPE_LATEST
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
        "uptime_seconds": int(time.time() - PROCESS_START_TIME),
    }


@app.post("/v1/chat/completions")
async def chat_completions(request: Request):
    """
    Proxy and augment chat completion requests to AI providers.
    Production implementation would include:
    - JWT authentication
    - Rate limiting via Redis
    - Request/response transformation
    - Usage tracking
    - Fallback between providers
    """
    body = await request.json()
    logger.info("chat_completion_request", model=body.get("model"))

    # Production code would route to OpenAI / Anthropic here
    return {"choices": [{"message": {"role": "assistant", "content": "Hello from Corex AI Gateway"}}]}


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


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
