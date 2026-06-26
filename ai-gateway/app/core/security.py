from __future__ import annotations

from datetime import datetime, timedelta, timezone
from typing import Any, Dict, List, Optional

from fastapi import Depends, HTTPException, Request, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from jose import JWTError, ExpiredSignatureError, jwt

from app.core.config import settings

security_scheme = HTTPBearer(auto_error=False)


def create_access_token(
    subject: str,
    scopes: Optional[List[str]] = None,
    expires_delta: Optional[timedelta] = None,
) -> str:
    expires = datetime.now(timezone.utc) + (
        expires_delta or timedelta(seconds=settings.jwt_expiration)
    )
    payload: Dict[str, Any] = {
        "sub": subject,
        "exp": expires,
        "iat": datetime.now(timezone.utc),
        "type": "access",
    }
    if scopes:
        payload["scopes"] = scopes
    return jwt.encode(payload, settings.jwt_secret, algorithm=settings.jwt_algorithm)


def create_refresh_token(subject: str) -> str:
    expires = datetime.now(timezone.utc) + timedelta(days=7)
    payload = {
        "sub": subject,
        "exp": expires,
        "iat": datetime.now(timezone.utc),
        "type": "refresh",
    }
    return jwt.encode(payload, settings.jwt_secret, algorithm=settings.jwt_algorithm)


def decode_token(token: str) -> Dict[str, Any]:
    try:
        payload = jwt.decode(
            token,
            settings.jwt_secret,
            algorithms=[settings.jwt_algorithm],
        )
        return payload
    except ExpiredSignatureError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token has expired.",
            headers={"WWW-Authenticate": "Bearer"},
        )
    except JWTError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid token.",
            headers={"WWW-Authenticate": "Bearer"},
        )


async def get_current_user(
    credentials: Optional[HTTPAuthorizationCredentials] = Depends(security_scheme),
    request: Optional[Request] = None,
) -> Dict[str, Any]:
    token = None
    if credentials:
        token = credentials.credentials
    elif request:
        token = request.cookies.get("jwt_token")
        auth_header = request.headers.get("Authorization")
        if auth_header and auth_header.startswith("Bearer "):
            token = auth_header[7:]

    if not token:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Authentication required.",
        )

    try:
        payload = jwt.decode(
            token,
            settings.jwt_secret,
            algorithms=[settings.jwt_algorithm],
        )
        return payload
    except ExpiredSignatureError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Token has expired.",
        )
    except JWTError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid token.",
        )


async def get_optional_user(
    credentials: Optional[HTTPAuthorizationCredentials] = Depends(security_scheme),
) -> Optional[Dict[str, Any]]:
    if not credentials:
        return None
    try:
        return decode_token(credentials.credentials)
    except HTTPException:
        return None


def verify_scope(payload: Dict[str, Any], required_scope: str) -> bool:
    scopes = payload.get("scopes", [])
    return required_scope in scopes or "admin" in scopes


def require_scope(scope: str):
    async def _scope_checker(payload: Dict[str, Any] = Depends(get_current_user)):
        if not verify_scope(payload, scope):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail=f"Scope '{scope}' is required.",
            )
        return payload
    return _scope_checker
