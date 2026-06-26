from __future__ import annotations

import time
from typing import Any, Awaitable, Callable, Dict, Optional

from fastapi import FastAPI, Request, Response
from fastapi.responses import JSONResponse
from starlette.middleware.base import BaseHTTPMiddleware

from app.services.input_sanitizer import InputSanitizer, OutputFilter
from app.core.config import settings

from structlog import get_logger

logger = get_logger(__name__)


class InputValidationMiddleware(BaseHTTPMiddleware):
    MAX_BODY_SIZE = 10 * 1024 * 1024
    MAX_STRING_LENGTH = 50000
    MAX_DEPTH = 10

    async def dispatch(self, request: Request, call_next: Callable[[Request], Awaitable[Response]]) -> Response:
        if request.method in ("GET", "HEAD", "OPTIONS"):
            return await call_next(request)

        content_length = request.headers.get("content-length")
        if content_length and int(content_length) > self.MAX_BODY_SIZE:
            logger.warning("security.request_too_large", size=content_length, path=str(request.url))
            return JSONResponse(
                status_code=413,
                content={"detail": "Request entity too large.", "code": "PAYLOAD_TOO_LARGE"},
            )

        return await call_next(request)


class InputSanitizationMiddleware(BaseHTTPMiddleware):
    SANITIZE_PATHS = {"/v1/chat/completions", "/v1/embeddings", "/v1/agent/execute"}

    async def dispatch(self, request: Request, call_next: Callable[[Request], Awaitable[Response]]) -> Response:
        if request.method in ("GET", "HEAD", "OPTIONS"):
            return await call_next(request)

        if request.url.path not in self.SANITIZE_PATHS:
            return await call_next(request)

        try:
            body = await request.json()
        except Exception:
            return await call_next(request)

        for key, value in self._extract_strings(body):
            is_malicious, pattern = InputSanitizer.has_malicious_content(value)
            if is_malicious:
                logger.warning(
                    "security.malicious_input_blocked",
                    pattern=pattern,
                    field=key,
                    path=str(request.url),
                    ip=request.client.host if request.client else None,
                )
                return JSONResponse(
                    status_code=400,
                    content={
                        "detail": "Input contains blocked content.",
                        "code": "INPUT_BLOCKED",
                    },
                )

        sanitized = InputSanitizer.sanitize_object(body, max_depth=self.MAX_DEPTH)

        async def receive():
            return {"type": "http.request", "body": json.dumps(sanitized).encode(), "more_body": False}

        import json
        request._body = json.dumps(sanitized).encode()

        response = await call_next(request)
        return response

    def _extract_strings(self, obj: Any, prefix: str = "") -> list[tuple[str, str]]:
        strings: list[tuple[str, str]] = []
        if isinstance(obj, dict):
            for key, value in obj.items():
                full_key = f"{prefix}.{key}" if prefix else key
                if isinstance(value, str):
                    strings.append((full_key, value))
                elif isinstance(value, (dict, list)):
                    strings.extend(self._extract_strings(value, full_key))
        elif isinstance(obj, list):
            for i, item in enumerate(obj):
                full_key = f"{prefix}[{i}]"
                if isinstance(item, str):
                    strings.append((full_key, item))
                elif isinstance(item, (dict, list)):
                    strings.extend(self._extract_strings(item, full_key))
        return strings

    @property
    def MAX_DEPTH(self) -> int:
        return 10


class OutputFilterMiddleware(BaseHTTPMiddleware):
    FILTER_PATHS = {"/v1/chat/completions", "/v1/agent/execute"}

    async def dispatch(self, request: Request, call_next: Callable[[Request], Awaitable[Response]]) -> Response:
        if request.url.path not in self.FILTER_PATHS:
            return await call_next(request)

        response = await call_next(request)

        if response.status_code >= 400:
            return response

        if hasattr(response, "body"):
            try:
                import json

                body = json.loads(response.body)

                if isinstance(body, dict):
                    body = self._filter_content(body)

                response = JSONResponse(
                    content=body,
                    status_code=response.status_code,
                    headers=dict(response.headers),
                )
            except (json.JSONDecodeError, AttributeError):
                pass

        return response

    def _filter_content(self, data: Dict[str, Any]) -> Dict[str, Any]:
        if "choices" in data:
            for choice in data["choices"]:
                if "message" in choice and "content" in choice["message"]:
                    choice["message"]["content"] = OutputFilter.sanitize_prompt_output(
                        choice["message"]["content"]
                    )

        if "output" in data:
            data["output"] = OutputFilter.filter_response(data["output"])

        return data


class SecurityHeadersMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next: Callable[[Request], Awaitable[Response]]) -> Response:
        response = await call_next(request)

        response.headers["X-Content-Type-Options"] = "nosniff"
        response.headers["X-Frame-Options"] = "DENY"
        response.headers["X-XSS-Protection"] = "1; mode=block"
        response.headers["Strict-Transport-Security"] = "max-age=63072000; includeSubDomains; preload"
        response.headers["Referrer-Policy"] = "strict-origin-when-cross-origin"
        response.headers["Permissions-Policy"] = "camera=(), microphone=(), geolocation=(), interest-cohort=()"
        response.headers["Cross-Origin-Embedder-Policy"] = "require-corp"
        response.headers["Cross-Origin-Opener-Policy"] = "same-origin"
        response.headers["Cross-Origin-Resource-Policy"] = "same-origin"

        response.headers["X-DNS-Prefetch-Control"] = "on"
        response.headers["X-Download-Options"] = "noopen"
        response.headers["X-Permitted-Cross-Domain-Policies"] = "none"

        if "Server" in response.headers:
            del response.headers["Server"]

        return response


class RateLimitMiddleware(BaseHTTPMiddleware):
    def __init__(
        self,
        app: FastAPI,
        redis_client: Optional[Any] = None,
        default_limit: int = 100,
        default_window: int = 60,
    ):
        super().__init__(app)
        self._redis = redis_client
        self.default_limit = default_limit
        self.default_window = default_window
        self._local_limits: Dict[str, tuple[int, int, float]] = {}

    async def dispatch(self, request: Request, call_next: Callable[[Request], Awaitable[Response]]) -> Response:
        if not settings.rate_limit_enabled:
            return await call_next(request)

        client_ip = request.client.host if request.client else "unknown"
        path = request.url.path
        key = f"{client_ip}:{path}"

        limit, window = self._get_path_limits(path)

        if self._redis:
            allowed = await self._check_redis_rate_limit(key, limit, window)
        else:
            allowed = self._check_local_rate_limit(key, limit, window)

        if not allowed:
            logger.warning("security.rate_limit_exceeded", ip=client_ip, path=path)
            return JSONResponse(
                status_code=429,
                content={
                    "detail": "Rate limit exceeded. Please try again later.",
                    "code": "RATE_LIMITED",
                    "retry_after": window,
                },
                headers={"Retry-After": str(window)},
            )

        return await call_next(request)

    def _get_path_limits(self, path: str) -> tuple[int, int]:
        strict_paths = {
            "/v1/chat/completions": (20, 60),
            "/v1/agent/execute": (10, 60),
        }

        for prefix, (limit, window) in strict_paths.items():
            if path.startswith(prefix):
                return limit, window

        return self.default_limit, self.default_window

    async def _check_redis_rate_limit(self, key: str, limit: int, window: int) -> bool:
        try:
            current = await self._redis.incr(key)
            if current == 1:
                await self._redis.expire(key, window)
            return current <= limit
        except Exception:
            return True

    def _check_local_rate_limit(self, key: str, limit: int, window: int) -> bool:
        now = time.time()
        if key not in self._local_limits:
            self._local_limits[key] = (1, limit, now + window)
            return True

        count, max_limit, expires_at = self._local_limits[key]

        if now > expires_at:
            self._local_limits[key] = (1, limit, now + window)
            return True

        if count >= max_limit:
            return False

        self._local_limits[key] = (count + 1, max_limit, expires_at)
        return True
