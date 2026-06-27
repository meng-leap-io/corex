"""Sentry configuration for the AI Gateway."""

from __future__ import annotations

import sentry_sdk
from sentry_sdk.integrations.fastapi import FastApiIntegration
from sentry_sdk.integrations.redis import RedisIntegration
from sentry_sdk.integrations.httpx import HttpxIntegration

from app.core.config import settings


def _get_integrations() -> list:
    """Collect Sentry integrations, skipping unavailable ones."""
    integrations = [
        FastApiIntegration(transaction_style="endpoint"),
        RedisIntegration(),
        HttpxIntegration(),
    ]
    try:
        from sentry_sdk.integrations.structlog import StructlogIntegration
        integrations.append(StructlogIntegration())
    except ImportError:
        pass
    return integrations


def configure_sentry() -> None:
    """Initialize Sentry SDK with application context."""
    if not settings.sentry_dsn:
        return

    sentry_sdk.init(
        dsn=settings.sentry_dsn,
        environment=settings.environment.value,
        release=settings.app_version,
        sample_rate=1.0,
        traces_sample_rate=0.25,
        profiles_sample_rate=0.1,
        max_request_body_size="always",
        send_default_pii=False,
        attach_stacktrace=True,
        integrations=_get_integrations(),
        ignore_errors=[
            "ValueError",
            "ValidationError",
        ],
        before_send=_before_send,
        before_send_transaction=_before_send_transaction,
    )


def _before_send(event: dict, hint: dict) -> dict | None:
    """Filter events before sending to Sentry."""
    if settings.is_development:
        return None

    # Remove sensitive data
    if "request" in event and "headers" in event["request"]:
        headers = event["request"]["headers"]
        sensitive = {"authorization", "cookie", "x-api-key", "x-csrf-token"}
        for key in list(headers.keys()):
            if key.lower() in sensitive:
                headers[key] = "[redacted]"

    return event


def _before_send_transaction(event: dict, hint: dict) -> dict | None:
    """Filter transactions before sending to Sentry."""
    if settings.is_development:
        return None

    # Skip health check transactions
    transaction = event.get("transaction", "")
    if transaction in ("/health", "/", "/metrics", "/ready"):
        return None

    return event


def set_user_context(user_id: str, email: str | None = None) -> None:
    """Set Sentry user context for the current scope."""
    sentry_sdk.set_user({"id": user_id, "email": email or f"{user_id}@corex.dev"})


def set_extra_context(key: str, value: object) -> None:
    """Set extra context on the current Sentry scope."""
    sentry_sdk.set_extra(key, value)


def capture_error(error: Exception, context: dict | None = None) -> None:
    """Capture an exception with additional context."""
    with sentry_sdk.push_scope() as scope:
        if context:
            for key, value in context.items():
                scope.set_extra(key, value)
        sentry_sdk.capture_exception(error)
