"""Health check system for all services.

Provides comprehensive health checks used by /health and /ready endpoints.
Integrates with Prometheus metrics and Sentry for monitoring.
Windows-aware: handles platform-specific paths and process info.
"""

from __future__ import annotations

import asyncio
import time
from dataclasses import dataclass, field
from datetime import datetime, timezone
from enum import Enum
from typing import Any, Dict, List, Optional

import httpx
import redis.asyncio as aioredis
import structlog

from app.core.windows import IS_WINDOWS

logger = structlog.get_logger(__name__)


class ServiceStatus(str, Enum):
    HEALTHY = "healthy"
    DEGRADED = "degraded"
    UNHEALTHY = "unhealthy"


@dataclass
class HealthCheck:
    name: str
    status: ServiceStatus
    latency_ms: float
    message: str = ""
    details: dict = field(default_factory=dict)

    def to_dict(self) -> dict:
        return {
            "name": self.name,
            "status": self.status.value,
            "latency_ms": round(self.latency_ms, 2),
            "message": self.message,
            "details": self.details,
        }


class HealthRegistry:
    def __init__(self):
        self._checks: dict[str, callable] = {}
        self._cache: dict[str, HealthCheck] = {}
        self._cache_ttl: float = 5.0
        self._last_cache_update: float = 0.0

    def register(self, name: str, check_fn: callable) -> None:
        self._checks[name] = check_fn

    async def run_all(self, use_cache: bool = True) -> dict[str, HealthCheck]:
        now = time.time()
        if use_cache and (now - self._last_cache_update) < self._cache_ttl:
            return self._cache

        results: dict[str, HealthCheck] = {}
        tasks = []

        for name, check_fn in self._checks.items():
            tasks.append(self._run_single(name, check_fn))

        completed = await asyncio.gather(*tasks, return_exceptions=True)

        for i, (name, _) in enumerate(self._checks.items()):
            result = completed[i]
            if isinstance(result, Exception):
                results[name] = HealthCheck(
                    name=name,
                    status=ServiceStatus.UNHEALTHY,
                    latency_ms=0,
                    message=f"Check failed: {result}",
                )
            else:
                results[name] = result

        self._cache = results
        self._last_cache_update = now
        return results

    async def get_overall_status(
        self, results: dict[str, HealthCheck]
    ) -> tuple[ServiceStatus, list[str]]:
        statuses = {}
        for name, check in results.items():
            statuses[name] = check.status

        unhealthy = [n for n, s in statuses.items() if s == ServiceStatus.UNHEALTHY]
        degraded = [n for n, s in statuses.items() if s == ServiceStatus.DEGRADED]

        if unhealthy:
            return ServiceStatus.UNHEALTHY, unhealthy + degraded
        if degraded:
            return ServiceStatus.DEGRADED, degraded
        return ServiceStatus.HEALTHY, []

    async def _run_single(self, name: str, check_fn: callable) -> HealthCheck:
        start = time.time()
        try:
            result = await check_fn()
            latency = (time.time() - start) * 1000

            if isinstance(result, HealthCheck):
                return result

            if isinstance(result, tuple):
                status, message, details = result
                return HealthCheck(
                    name=name,
                    status=ServiceStatus(status),
                    latency_ms=latency,
                    message=message,
                    details=details or {},
                )

            return HealthCheck(
                name=name,
                status=ServiceStatus.HEALTHY,
                latency_ms=latency,
                message=str(result),
            )
        except Exception as e:
            latency = (time.time() - start) * 1000
            logger.error("health_check_failed", name=name, error=str(e))
            return HealthCheck(
                name=name,
                status=ServiceStatus.UNHEALTHY,
                latency_ms=latency,
                message=str(e),
            )


health_registry = HealthRegistry()


# ── Built-in health checks ──────────────────────────────────────────────

async def check_redis() -> HealthCheck | tuple:
    from app.core.config import settings

    start = time.time()
    try:
        r = aioredis.from_url(
            settings.redis_url,
            socket_connect_timeout=2,
            socket_timeout=2,
        )
        pong = await r.ping()
        latency = (time.time() - start) * 1000

        info = {}
        try:
            info_bytes = await r.info(section="server")
            info = {
                "redis_version": info_bytes.get("redis_version", "unknown"),
                "uptime_seconds": info_bytes.get("uptime_in_seconds", 0),
            }
        except Exception:
            pass

        if pong:
            return HealthCheck(
                name="redis",
                status=ServiceStatus.HEALTHY,
                latency_ms=latency,
                message="Redis connected",
                details=info,
            )
        return HealthCheck(
            name="redis",
            status=ServiceStatus.UNHEALTHY,
            latency_ms=latency,
            message="Redis ping failed",
        )
    except Exception as e:
        return HealthCheck(
            name="redis",
            status=ServiceStatus.UNHEALTHY,
            latency_ms=0,
            message=f"Redis connection failed: {e}",
        )


async def check_memory() -> HealthCheck:
    try:
        import psutil

        process = psutil.Process()
        mem = process.memory_info()
        percent = process.memory_percent()

        status = ServiceStatus.HEALTHY
        message = "Memory usage normal"

        if percent > 85:
            status = ServiceStatus.UNHEALTHY
            message = "Memory usage critical"
        elif percent > 70:
            status = ServiceStatus.DEGRADED
            message = "Memory usage high"

        return HealthCheck(
            name="memory",
            status=status,
            latency_ms=0,
            message=message,
            details={
                "rss_bytes": mem.rss,
                "vms_bytes": mem.vms,
                "percent": round(percent, 1),
                "available_mb": round(psutil.virtual_memory().available / 1024 / 1024, 1),
            },
        )
    except ImportError:
        return HealthCheck(
            name="memory",
            status=ServiceStatus.HEALTHY,
            latency_ms=0,
            message="psutil not available",
            details={"note": "Install psutil for memory monitoring"},
        )


async def check_disk() -> HealthCheck:
    try:
        import psutil

        # Use the Windows system drive or POSIX root
        disk_path = "C:\\" if IS_WINDOWS else "/"
        disk = psutil.disk_usage(disk_path)
        status = ServiceStatus.HEALTHY
        message = "Disk usage normal"

        if disk.percent > 95:
            status = ServiceStatus.UNHEALTHY
            message = "Disk usage critical"
        elif disk.percent > 85:
            status = ServiceStatus.DEGRADED
            message = "Disk usage high"

        return HealthCheck(
            name="disk",
            status=status,
            latency_ms=0,
            message=message,
            details={
                "path": disk_path,
                "total_gb": round(disk.total / 1024 / 1024 / 1024, 2),
                "used_gb": round(disk.used / 1024 / 1024 / 1024, 2),
                "free_gb": round(disk.free / 1024 / 1024 / 1024, 2),
                "percent": disk.percent,
            },
        )
    except ImportError:
        return HealthCheck(
            name="disk",
            status=ServiceStatus.HEALTHY,
            latency_ms=0,
            message="psutil not available",
        )


async def check_ollama() -> HealthCheck:
    """Check if local Ollama instance is running and healthy."""
    from app.core.config import settings

    if not settings.ollama_enabled:
        return HealthCheck(
            name="ollama",
            status=ServiceStatus.HEALTHY,
            latency_ms=0,
            message="Ollama disabled",
            details={"enabled": False},
        )

    start = time.time()
    try:
        async with httpx.AsyncClient(timeout=3.0) as client:
            response = await client.get(f"{settings.ollama_base_url}/api/version")
            latency = (time.time() - start) * 1000

            if response.status_code == 200:
                version = response.json().get("version", "unknown")
                return HealthCheck(
                    name="ollama",
                    status=ServiceStatus.HEALTHY,
                    latency_ms=latency,
                    message=f"Ollama {version} running",
                    details={"version": version, "enabled": True},
                )

            return HealthCheck(
                name="ollama",
                status=ServiceStatus.DEGRADED,
                latency_ms=latency,
                message=f"Ollama returned {response.status_code}",
                details={"enabled": True},
            )
    except Exception as e:
        latency = (time.time() - start) * 1000
        return HealthCheck(
            name="ollama",
            status=ServiceStatus.DEGRADED,
            latency_ms=latency,
            message=f"Ollama not reachable: {e}",
            details={"enabled": True, "url": settings.ollama_base_url},
        )


async def check_database() -> HealthCheck | tuple:
    return (
        ServiceStatus.HEALTHY,
        "Database check not configured",
        {"driver": "none", "configured": False},
    )


async def check_uptime(process_start_time: float) -> HealthCheck:
    uptime_seconds = time.time() - process_start_time
    return HealthCheck(
        name="uptime",
        status=ServiceStatus.HEALTHY,
        latency_ms=0,
        message="Process running",
        details={
            "uptime_seconds": round(uptime_seconds),
            "uptime_hours": round(uptime_seconds / 3600, 1),
            "started_at": datetime.fromtimestamp(
                process_start_time, tz=timezone.utc
            ).isoformat(),
        },
    )


def get_health_response(
    results: dict[str, HealthCheck],
    overall: ServiceStatus,
    failed: list[str],
    service: str = "ai-gateway",
) -> dict:
    now = datetime.now(timezone.utc)
    return {
        "status": overall.value,
        "service": service,
        "timestamp": now.isoformat(),
        "checks": {name: c.to_dict() for name, c in results.items()},
        "failed_checks": failed,
        "checks_count": len(results),
        "healthy_count": sum(
            1 for c in results.values() if c.status == ServiceStatus.HEALTHY
        ),
        "degraded_count": sum(
            1 for c in results.values() if c.status == ServiceStatus.DEGRADED
        ),
        "unhealthy_count": sum(
            1 for c in results.values() if c.status == ServiceStatus.UNHEALTHY
        ),
    }
