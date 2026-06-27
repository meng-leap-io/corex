"""Windows Service implementation for the Corex AI Gateway.

Uses pywin32 to register and run the FastAPI application as a Windows Service.
Supports start, stop, pause, continue, and shutdown events.

Usage:
    python -m app.core.windows_service install
    python -m app.core.windows_service start
    python -m app.core.windows_service stop
    python -m app.core.windows_service remove
"""

from __future__ import annotations

import os
import sys
import threading
import time
from pathlib import Path
from typing import Optional

import structlog

from app.core.windows import (
    IS_WINDOWS,
    EVENT_LOG_SOURCE,
    get_default_log_dir,
    write_event_log,
    register_event_log_source,
    set_high_performance,
    read_registry,
    write_registry,
)

logger = structlog.get_logger(__name__)

# ── Service Configuration ─────────────────────────────────────────────

SERVICE_NAME = "CorexAIGateway"
SERVICE_DISPLAY_NAME = "Corex AI Gateway"
SERVICE_DESCRIPTION = "FastAPI-based AI provider gateway with agent orchestration, rate limiting, and caching"
SERVICE_DEPENDENCIES = ["CorexRedis"]  # Start after Redis


class AIGatewayService:
    """Windows Service implementation using pywin32."""

    def __init__(self):
        self._stop_event = threading.Event()
        self._thread: Optional[threading.Thread] = None
        self._uvicorn_server = None
        self._running = False

    def start(self) -> None:
        """Called by the Windows Service Manager when the service is starting."""
        write_event_log("AI Gateway service starting", level="info", event_id=100)

        # Set high performance priority for the service
        set_high_performance()

        # Register Event Log source
        register_event_log_source()

        # Read settings from registry
        host = read_registry("host", "127.0.0.1")
        port = int(read_registry("port", "8000"))
        workers = int(read_registry("workers", "4"))
        log_dir = read_registry("log_dir", str(get_default_log_dir()))

        write_registry("service_status", "starting")
        write_registry("last_start_time", str(int(time.time())))

        store = ServiceStateStore(log_dir)
        store.save_state("starting")

        # Start the FastAPI/Uvicorn server in a background thread
        self._thread = threading.Thread(
            target=self._run_server,
            args=(host, port, workers, log_dir),
            daemon=True,
            name="uvicorn-server",
        )
        self._thread.start()
        self._running = True

        write_event_log(
            f"AI Gateway service started on {host}:{port} with {workers} workers",
            level="info",
            event_id=101,
        )
        write_registry("service_status", "running")

    def stop(self) -> None:
        """Called by the Windows Service Manager when the service is stopping."""
        write_event_log("AI Gateway service stopping", level="info", event_id=200)
        write_registry("service_status", "stopping")

        self._stop_event.set()

        if self._uvicorn_server:
            self._uvicorn_server.should_exit = True

        if self._thread and self._thread.is_alive():
            self._thread.join(timeout=30)

        self._running = False
        write_registry("service_status", "stopped")
        write_event_log("AI Gateway service stopped", level="info", event_id=201)

    def pause(self) -> None:
        """Called when the service is paused."""
        write_event_log("AI Gateway service paused", level="warning", event_id=300)
        write_registry("service_status", "paused")

    def resume(self) -> None:
        """Called when the service resumes from pause."""
        write_event_log("AI Gateway service resumed", level="info", event_id=301)
        write_registry("service_status", "running")

    def shutdown(self) -> None:
        """Called when the system is shutting down."""
        write_event_log("AI Gateway service shutting down", level="warning", event_id=400)
        self.stop()

    def _run_server(self, host: str, port: int, workers: int, log_dir: str) -> None:
        """Start the Uvicorn server in this thread."""
        os.environ["COREX_LOG_DIR"] = log_dir

        # Ensure Redis host resolves to localhost for local Windows Redis
        if read_registry("redis_local", "true") == "true":
            os.environ.setdefault("REDIS_HOST", "127.0.0.1")

        # Set Ollama URL for local Windows Ollama
        os.environ.setdefault("OLLAMA_BASE_URL", "http://127.0.0.1:11434")

        import uvicorn

        config = uvicorn.Config(
            "main:app",
            host=host,
            port=port,
            workers=workers,
            log_level=read_registry("log_level", "info"),
            reload=False,
            limit_max_requests=int(read_registry("limit_max_requests", "10000")),
            loop="uvloop" if not IS_WINDOWS else "asyncio",
            http="httptools" if not IS_WINDOWS else "auto",
        )

        self._uvicorn_server = uvicorn.Server(config)

        try:
            if not self._stop_event.is_set():
                self._uvicorn_server.run()
        except Exception as e:
            logger.error("uvicorn_server_failed", error=str(e))
            write_event_log(f"Uvicorn server failed: {e}", level="error", event_id=500)
            write_registry("service_status", "error")
            write_registry("last_error", str(e)[:500])
            store = ServiceStateStore(get_default_log_dir())
            store.save_state("error", error=str(e))
            raise


class ServiceStateStore:
    """Persist service state to disk for monitoring tools."""

    def __init__(self, log_dir: str | Path):
        self.path = Path(log_dir) / "service-state.json"
        self.path.parent.mkdir(parents=True, exist_ok=True)

    def save_state(self, state: str, error: str = "") -> None:
        import json
        data = {
            "service": SERVICE_NAME,
            "state": state,
            "timestamp": time.time(),
            "error": error,
            "pid": os.getpid(),
        }
        try:
            self.path.write_text(json.dumps(data, indent=2))
        except Exception as e:
            logger.warning("state_store_write_failed", error=str(e))

    def read_state(self) -> dict:
        import json
        try:
            return json.loads(self.path.read_text())
        except Exception:
            return {"state": "unknown"}


# ── pywin32 Service Class ─────────────────────────────────────────────


def run_service() -> None:
    """Entry point: register and run as a Windows Service.

    This is called by the Service Manager via pywin32's service framework.
    """
    if not IS_WINDOWS:
        print("This module is only for Windows. Use `uvicorn main:app` on this platform.")
        sys.exit(1)

    import servicemanager
    import win32serviceutil
    import win32service

    class CorexAIGatewayService(win32serviceutil.ServiceFramework):
        _svc_name_ = SERVICE_NAME
        _svc_display_name_ = SERVICE_DISPLAY_NAME
        _svc_description_ = SERVICE_DESCRIPTION
        _svc_deps_ = SERVICE_DEPENDENCIES

        def __init__(self, args):
            super().__init__(args)
            self._gateway = AIGatewayService()
            self._svc_stop_event = threading.Event()

        def SvcDoRun(self):
            servicemanager.LogMsg(
                servicemanager.EVENTLOG_INFORMATION_TYPE,
                servicemanager.PYS_SERVICE_STARTED,
                (self._svc_name_, ""),
            )
            self._gateway.start()
            self._svc_stop_event.wait()

        def SvcStop(self):
            self.ReportServiceStatus(win32service.SERVICE_STOP_PENDING)
            self._gateway.stop()
            self._svc_stop_event.set()
            servicemanager.LogMsg(
                servicemanager.EVENTLOG_INFORMATION_TYPE,
                servicemanager.PYS_SERVICE_STOPPED,
                (self._svc_name_, ""),
            )

        def SvcPause(self):
            self._gateway.pause()

        def SvcResume(self):
            self._gateway.resume()

        def SvcShutdown(self):
            self._gateway.shutdown()

    win32serviceutil.HandleCommandLine(CorexAIGatewayService)


# ── CLI Entry Points ──────────────────────────────────────────────────

def install():
    """Install the Windows service."""
    if not IS_WINDOWS:
        print("Windows service installation is only supported on Windows.")
        return

    import win32serviceutil
    import servicemanager

    # Register Event Log source
    register_event_log_source()

    # Install the service
    try:
        win32serviceutil.InstallService(
            None,
            SERVICE_NAME,
            SERVICE_DISPLAY_NAME,
            SERVICE_DESCRIPTION,
            startType=win32serviceutil.ServiceFramework._svc_start_type_,
        )
        print(f"Service '{SERVICE_NAME}' installed successfully.")
        write_event_log(f"Service '{SERVICE_NAME}' installed", level="info", event_id=1000)

        # Write default registry settings
        write_registry("host", "127.0.0.1")
        write_registry("port", "8000")
        write_registry("workers", "2")
        write_registry("log_level", "info")
        write_registry("redis_local", "true")
        write_registry("service_status", "installed")

    except Exception as e:
        print(f"Failed to install service: {e}")
        write_event_log(f"Service installation failed: {e}", level="error", event_id=1001)
        raise


def remove():
    """Remove the Windows service."""
    if not IS_WINDOWS:
        return

    import win32serviceutil
    try:
        win32serviceutil.RemoveService(SERVICE_NAME)
        print(f"Service '{SERVICE_NAME}' removed.")
        write_event_log(f"Service '{SERVICE_NAME}' removed", level="info", event_id=1002)
    except Exception as e:
        print(f"Failed to remove service: {e}")


def start():
    """Start the service."""
    if not IS_WINDOWS:
        return

    import win32serviceutil
    try:
        win32serviceutil.StartService(SERVICE_NAME)
        print(f"Service '{SERVICE_NAME}' started.")
    except Exception as e:
        print(f"Failed to start service: {e}")


def stop():
    """Stop the service."""
    if not IS_WINDOWS:
        return

    import win32serviceutil
    try:
        win32serviceutil.StopService(SERVICE_NAME)
        print(f"Service '{SERVICE_NAME}' stopped.")
    except Exception as e:
        print(f"Failed to stop service: {e}")


def status():
    """Check service status."""
    if not IS_WINDOWS:
        return

    import win32serviceutil
    import win32service
    try:
        status = win32serviceutil.QueryServiceStatus(SERVICE_NAME)
        state_map = {
            win32service.SERVICE_STOPPED: "Stopped",
            win32service.SERVICE_START_PENDING: "Start Pending",
            win32service.SERVICE_STOP_PENDING: "Stop Pending",
            win32service.SERVICE_RUNNING: "Running",
            win32service.SERVICE_CONTINUE_PENDING: "Continue Pending",
            win32service.SERVICE_PAUSE_PENDING: "Pause Pending",
            win32service.SERVICE_PAUSED: "Paused",
        }
        state = state_map.get(status[1], f"Unknown ({status[1]})")
        print(f"Service '{SERVICE_NAME}': {state}")
    except Exception as e:
        print(f"Service not found: {e}")


# ── Main ──────────────────────────────────────────────────────────────

if __name__ == "__main__":
    if len(sys.argv) > 1:
        command = sys.argv[1]
        commands = {
            "install": install,
            "remove": remove,
            "start": start,
            "stop": stop,
            "status": status,
        }
        if command in commands:
            commands[command]()
        else:
            print(f"Unknown command: {command}")
            print(f"Usage: python -m app.core.windows_service [{','.join(commands)}]")
    else:
        run_service()
