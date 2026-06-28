"""Supabase async client with connection pooling, retry logic, and lifecycle.

Provides a singleton connection pool to Supabase PostgreSQL, raw SQL execution,
REST API helpers, and seamless integration with the FastAPI lifespan.
"""

from __future__ import annotations

import asyncio
import time
from dataclasses import dataclass, field
from typing import Any, Optional

import asyncpg
import httpx
from structlog import get_logger

from app.core.config import settings

logger = get_logger(__name__)


@dataclass
class PoolStats:
    """Connection pool statistics for monitoring."""
    created: int = 0
    closed: int = 0
    acquired: int = 0
    released: int = 0
    errors: int = 0
    queries_executed: int = 0
    total_query_time_ms: float = 0.0
    cache_hits: int = 0
    cache_misses: int = 0

    @property
    def avg_query_time_ms(self) -> float:
        if self.queries_executed == 0:
            return 0.0
        return round(self.total_query_time_ms / self.queries_executed, 2)


pool_stats = PoolStats()


class SupabaseError(Exception):
    """Base exception for Supabase operations."""

    def __init__(self, message: str, original: Optional[Exception] = None):
        self.original = original
        super().__init__(message)


class SupabaseConnectionError(SupabaseError):
    """Raised when connection to Supabase fails."""
    pass


class SupabaseQueryError(SupabaseError):
    """Raised when a database query fails."""
    pass


class SupabasePool:
    """Async connection pool manager for Supabase PostgreSQL.

    Usage:
        pool = SupabasePool()
        await pool.connect()
        row = await pool.fetchrow("SELECT * FROM users WHERE id = $1", uid)
        await pool.close()
    """

    def __init__(self):
        self._pool: Optional[asyncpg.Pool] = None
        self._http_client: Optional[httpx.AsyncClient] = None
        self._connected = False
        self._lock = asyncio.Lock()
        self._retry_backoff = settings.supabase_retry_delay

    async def connect(self) -> None:
        """Initialize the connection pool and HTTP client."""
        async with self._lock:
            if self._connected:
                return

            try:
                self._pool = await asyncpg.create_pool(
                    dsn=settings.supabase_db_dsn,
                    min_size=settings.supabase_pool_min_size,
                    max_size=settings.supabase_pool_max_size,
                    max_queries=settings.supabase_pool_max_queries,
                    max_inactive_connection_lifetime=settings.supabase_pool_max_inactive_seconds,
                    command_timeout=settings.supabase_command_timeout,
                    timeout=10,
                )

                self._http_client = httpx.AsyncClient(
                    base_url=settings.supabase_rest_url,
                    headers={
                        "apikey": settings.supabase_key,
                        "Authorization": f"Bearer {settings.supabase_key}",
                        "Content-Type": "application/json",
                        "Prefer": "return=representation",
                    },
                    timeout=httpx.Timeout(
                        connect=5.0,
                        read=settings.supabase_command_timeout,
                        write=settings.supabase_command_timeout,
                    ),
                    limits=httpx.Limits(
                        max_connections=settings.supabase_pool_max_size,
                        max_keepalive_connections=settings.supabase_pool_min_size,
                    ),
                )

                self._connected = True
                self._retry_backoff = settings.supabase_retry_delay
                pool_stats.created += 1

                logger.info(
                    "supabase_pool_connected",
                    dsn=settings.supabase_db_dsn.replace(
                        settings.supabase_db_password, "****"
                    ),
                    min_size=settings.supabase_pool_min_size,
                    max_size=settings.supabase_pool_max_size,
                )
            except Exception as e:
                logger.error("supabase_pool_connect_failed", error=str(e))
                raise SupabaseConnectionError(
                    f"Failed to connect to Supabase: {e}", original=e
                ) from e

    async def close(self) -> None:
        """Gracefully close the pool and HTTP client."""
        async with self._lock:
            if self._http_client:
                await self._http_client.aclose()
                self._http_client = None

            if self._pool:
                await self._pool.close()
                self._pool = None

            self._connected = False
            logger.info("supabase_pool_closed")

    async def execute(self, query: str, *args: Any) -> str:
        """Execute a SQL command and return status.

        Includes automatic retry with exponential backoff.
        """
        return await self._with_retry("execute", self._do_execute, query, *args)

    async def fetchrow(self, query: str, *args: Any) -> Optional[asyncpg.Record]:
        """Fetch a single row."""
        return await self._with_retry("fetchrow", self._do_fetchrow, query, *args)

    async def fetchval(self, query: str, *args: Any) -> Any:
        """Fetch a single value from the first column of the first row."""
        return await self._with_retry("fetchval", self._do_fetchval, query, *args)

    async def fetch(self, query: str, *args: Any) -> list[asyncpg.Record]:
        """Fetch multiple rows."""
        return await self._with_retry("fetch", self._do_fetch, query, *args)

    async def executemany(self, command: str, args: list[tuple]) -> None:
        """Execute the same SQL for each set of parameters."""
        return await self._with_retry("executemany", self._do_executemany, command, args)

    async def transaction(self) -> asyncpg.connection.TxContext:
        """Enter a transaction context manager."""
        conn = await self._acquire()
        return conn.transaction()

    async def _acquire(self) -> asyncpg.Connection:
        """Acquire a connection from the pool."""
        if not self._pool or not self._connected:
            raise SupabaseConnectionError("Connection pool not initialized")

        try:
            conn = await self._pool.acquire()
            pool_stats.acquired += 1
            return conn
        except Exception as e:
            pool_stats.errors += 1
            raise SupabaseConnectionError(f"Failed to acquire connection: {e}") from e

    async def _release(self, conn: asyncpg.Connection) -> None:
        """Release a connection back to the pool."""
        try:
            await self._pool.release(conn)
            pool_stats.released += 1
        except Exception as e:
            pool_stats.errors += 1
            logger.warning("supabase_release_failed", error=str(e))

    async def _do_execute(self, query: str, *args: Any) -> str:
        conn = await self._acquire()
        try:
            result = await conn.execute(query, *args)
            pool_stats.queries_executed += 1
            return result
        finally:
            await self._release(conn)

    async def _do_fetchrow(self, query: str, *args: Any) -> Optional[asyncpg.Record]:
        conn = await self._acquire()
        try:
            result = await conn.fetchrow(query, *args)
            pool_stats.queries_executed += 1
            return result
        finally:
            await self._release(conn)

    async def _do_fetchval(self, query: str, *args: Any) -> Any:
        conn = await self._acquire()
        try:
            result = await conn.fetchval(query, *args)
            pool_stats.queries_executed += 1
            return result
        finally:
            await self._release(conn)

    async def _do_fetch(self, query: str, *args: Any) -> list[asyncpg.Record]:
        conn = await self._acquire()
        try:
            result = await conn.fetch(query, *args)
            pool_stats.queries_executed += 1
            return result
        finally:
            await self._release(conn)

    async def _do_executemany(self, command: str, args: list[tuple]) -> None:
        conn = await self._acquire()
        try:
            await conn.executemany(command, args)
            pool_stats.queries_executed += len(args)
        finally:
            await self._release(conn)

    async def _with_retry(
        self, operation: str, fn, *args: Any
    ) -> Any:
        """Execute a database operation with exponential backoff retry."""
        last_error: Optional[Exception] = None
        delay = self._retry_backoff

        for attempt in range(settings.supabase_retry_attempts):
            try:
                start = time.monotonic()
                result = await fn(*args)
                elapsed = (time.monotonic() - start) * 1000
                pool_stats.total_query_time_ms += elapsed
                return result
            except (asyncpg.InterfaceError, asyncpg.ConnectionDoesNotExistError) as e:
                last_error = e
                pool_stats.errors += 1
                logger.warning(
                    "supabase_retry_connection",
                    operation=operation,
                    attempt=attempt + 1,
                    error=str(e),
                )
                await self._reconnect()
            except asyncpg.PostgresError as e:
                pool_stats.errors += 1
                logger.error(
                    "supabase_query_error",
                    operation=operation,
                    error=str(e),
                )
                raise SupabaseQueryError(str(e), original=e) from e
            except Exception as e:
                pool_stats.errors += 1
                logger.error(
                    "supabase_unexpected_error",
                    operation=operation,
                    error=str(e),
                )
                raise SupabaseQueryError(f"Unexpected error: {e}", original=e) from e

            if attempt < settings.supabase_retry_attempts - 1:
                jitter = delay * (0.5 + asyncio.get_event_loop().time() % 0.5)
                await asyncio.sleep(min(jitter, settings.supabase_retry_max_delay))
                delay = min(delay * 2, settings.supabase_retry_max_delay)

        raise SupabaseConnectionError(
            f"Operation '{operation}' failed after {settings.supabase_retry_attempts} retries",
            original=last_error,
        )

    async def _reconnect(self) -> None:
        """Force reconnection of the pool."""
        logger.info("supabase_reconnecting")
        await self.close()
        await self.connect()

    # ── REST API helpers ─────────────────────────────────────────────

    async def rest_get(
        self, table: str, params: Optional[dict] = None,
    ) -> list[dict]:
        """GET from Supabase REST API (PostgREST)."""
        if not self._http_client:
            raise SupabaseConnectionError("HTTP client not initialized")
        try:
            response = await self._http_client.get(f"/{table}", params=params)
            response.raise_for_status()
            return response.json()
        except httpx.HTTPStatusError as e:
            logger.error("supabase_rest_get_error", table=table, status=e.response.status_code)
            raise SupabaseQueryError(f"REST GET /{table} failed: {e}") from e

    async def rest_post(
        self, table: str, data: dict, params: Optional[dict] = None,
    ) -> list[dict]:
        """POST to Supabase REST API."""
        if not self._http_client:
            raise SupabaseConnectionError("HTTP client not initialized")
        try:
            response = await self._http_client.post(f"/{table}", json=data, params=params)
            response.raise_for_status()
            return response.json()
        except httpx.HTTPStatusError as e:
            logger.error("supabase_rest_post_error", table=table, status=e.response.status_code)
            raise SupabaseQueryError(f"REST POST /{table} failed: {e}") from e

    async def rest_patch(
        self, table: str, data: dict, params: Optional[dict] = None,
    ) -> list[dict]:
        """PATCH to Supabase REST API."""
        if not self._http_client:
            raise SupabaseConnectionError("HTTP client not initialized")
        try:
            response = await self._http_client.patch(f"/{table}", json=data, params=params)
            response.raise_for_status()
            return response.json()
        except httpx.HTTPStatusError as e:
            logger.error("supabase_rest_patch_error", table=table, status=e.response.status_code)
            raise SupabaseQueryError(f"REST PATCH /{table} failed: {e}") from e

    async def rest_delete(
        self, table: str, params: Optional[dict] = None,
    ) -> None:
        """DELETE from Supabase REST API."""
        if not self._http_client:
            raise SupabaseConnectionError("HTTP client not initialized")
        try:
            response = await self._http_client.delete(f"/{table}", params=params)
            response.raise_for_status()
        except httpx.HTTPStatusError as e:
            logger.error("supabase_rest_delete_error", table=table, status=e.response.status_code)
            raise SupabaseQueryError(f"REST DELETE /{table} failed: {e}") from e

    async def rest_rpc(self, fn_name: str, params: Optional[dict] = None) -> Any:
        """Call a Supabase RPC function."""
        if not self._http_client:
            raise SupabaseConnectionError("HTTP client not initialized")
        try:
            response = await self._http_client.post(
                f"/rpc/{fn_name}", json=params or {},
            )
            response.raise_for_status()
            return response.json()
        except httpx.HTTPStatusError as e:
            logger.error("supabase_rest_rpc_error", fn=fn_name, status=e.response.status_code)
            raise SupabaseQueryError(f"RPC {fn_name} failed: {e}") from e

    # ── Health & Stats ───────────────────────────────────────────────

    async def health(self) -> dict:
        """Check pool health and return status."""
        checks = {
            "connected": self._connected,
            "pool_initialized": self._pool is not None,
            "http_initialized": self._http_client is not None,
        }

        if self._pool:
            try:
                val = await self.fetchval("SELECT 1 AS ok")
                checks["query_ok"] = val == 1
            except Exception as e:
                checks["query_ok"] = False
                checks["query_error"] = str(e)

        if self._http_client:
            try:
                hc = await self._http_client.get("/")
                checks["rest_ok"] = hc.status_code < 500
            except Exception as e:
                checks["rest_ok"] = False

        return checks

    def is_connected(self) -> bool:
        return self._connected and self._pool is not None

    def get_stats(self) -> dict:
        return {
            "connected": self._connected,
            "pool_created": pool_stats.created,
            "pool_closed": pool_stats.closed,
            "connections_acquired": pool_stats.acquired,
            "connections_released": pool_stats.released,
            "errors": pool_stats.errors,
            "queries_executed": pool_stats.queries_executed,
            "total_query_time_ms": round(pool_stats.total_query_time_ms, 2),
            "avg_query_time_ms": pool_stats.avg_query_time_ms,
            "cache_hits": pool_stats.cache_hits,
            "cache_misses": pool_stats.cache_misses,
        }


supabase_pool = SupabasePool()
