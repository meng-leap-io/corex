from __future__ import annotations

from typing import Any, Dict, List, Optional


class AIGatewayError(Exception):
    status_code: int = 500
    detail: str = "Internal server error."
    code: str = "internal_error"

    def __init__(
        self,
        detail: Optional[str] = None,
        code: Optional[str] = None,
        status_code: Optional[int] = None,
        extra: Optional[Dict[str, Any]] = None,
    ):
        if detail:
            self.detail = detail
        if code:
            self.code = code
        if status_code:
            self.status_code = status_code
        self.extra = extra or {}
        super().__init__(self.detail)

    def to_dict(self) -> Dict[str, Any]:
        result = {
            "error": {
                "code": self.code,
                "message": self.detail,
            }
        }
        if self.extra:
            result["error"]["details"] = self.extra
        return result


class AuthenticationError(AIGatewayError):
    status_code = 401
    code = "authentication_error"
    detail = "Authentication failed."


class AuthorizationError(AIGatewayError):
    status_code = 403
    code = "authorization_error"
    detail = "You do not have permission to perform this action."


class RateLimitError(AIGatewayError):
    status_code = 429
    code = "rate_limit_exceeded"
    detail = "Rate limit exceeded. Please try again later."


class ProviderError(AIGatewayError):
    status_code = 502
    code = "provider_error"
    detail = "AI provider returned an error."

    def __init__(
        self,
        provider: str,
        detail: Optional[str] = None,
        original_error: Optional[str] = None,
    ):
        extra = {"provider": provider}
        if original_error:
            extra["original_error"] = original_error
        super().__init__(
            detail=detail or f"Provider '{provider}' returned an error.",
            code="provider_error",
            status_code=502,
            extra=extra,
        )


class ProviderTimeoutError(ProviderError):
    code = "provider_timeout"

    def __init__(self, provider: str, timeout: int):
        super().__init__(
            provider=provider,
            detail=f"Provider '{provider}' timed out after {timeout}s.",
            original_error="timeout",
        )


class ProviderRateLimitError(AIGatewayError):
    status_code = 429
    code = "provider_rate_limit"
    detail = "AI provider rate limit exceeded."

    def __init__(self, provider: str, retry_after: Optional[int] = None):
        extra = {"provider": provider}
        if retry_after:
            extra["retry_after"] = retry_after
        super().__init__(
            detail=f"Provider '{provider}' rate limit exceeded.",
            code="provider_rate_limit",
            status_code=429,
            extra=extra,
        )


class ModelNotFoundError(AIGatewayError):
    status_code = 404
    code = "model_not_found"
    detail = "The requested model is not available."

    def __init__(self, model: str):
        super().__init__(
            detail=f"Model '{model}' is not available.",
            code="model_not_found",
            status_code=404,
            extra={"model": model},
        )


class ValidationError(AIGatewayError):
    status_code = 422
    code = "validation_error"
    detail = "Request validation failed."

    def __init__(self, errors: List[Dict[str, Any]]):
        super().__init__(
            detail="Request validation failed.",
            code="validation_error",
            status_code=422,
            extra={"errors": errors},
        )


class InsufficientCreditsError(AIGatewayError):
    status_code = 402
    code = "insufficient_credits"
    detail = "Insufficient API credits."

    def __init__(self, credits_available: float, credits_required: float):
        super().__init__(
            detail="Insufficient API credits.",
            code="insufficient_credits",
            status_code=402,
            extra={
                "credits_available": credits_available,
                "credits_required": credits_required,
            },
        )


class ConfigurationError(AIGatewayError):
    status_code = 500
    code = "configuration_error"
    detail = "Service configuration error."


class ContextLengthExceededError(AIGatewayError):
    status_code = 400
    code = "context_length_exceeded"
    detail = "Request exceeds maximum context length."

    def __init__(self, context_length: int, max_length: int):
        super().__init__(
            detail=f"Request exceeds maximum context length ({context_length} > {max_length}).",
            code="context_length_exceeded",
            status_code=400,
            extra={"context_length": context_length, "max_length": max_length},
        )
