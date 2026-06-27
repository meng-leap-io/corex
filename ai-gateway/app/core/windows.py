"""Windows-specific configuration, path handling, registry, and Event Log utilities."""

from __future__ import annotations

import os
import sys
import time
from pathlib import Path
from typing import Optional

import structlog

logger = structlog.get_logger(__name__)


# ── Platform Detection ─────────────────────────────────────────────────

IS_WINDOWS = sys.platform == "win32"


def get_hostname() -> str:
    """Return hostname, works on both Windows and POSIX."""
    return os.environ.get("COMPUTERNAME") or os.environ.get("HOSTNAME") or "localhost"


# ── Windows Path Resolution ────────────────────────────────────────────

def get_default_data_dir() -> Path:
    """Return the preferred data directory for cache, logs, etc.

    Priority:
      1. COREX_DATA_DIR env var
      2. %LOCALAPPDATA%/Corex (Windows)
      3. ~/.local/share/corex (POSIX)
      4. Fallback to ./data
    """
    if env := os.environ.get("COREX_DATA_DIR"):
        return Path(env)

    if IS_WINDOWS:
        base = os.environ.get("LOCALAPPDATA", "")
        if base:
            return Path(base) / "Corex"
        base = os.environ.get("USERPROFILE", "")
        if base:
            return Path(base) / "AppData" / "Local" / "Corex"

    base = os.environ.get("HOME", "")
    if base:
        return Path(base) / ".local" / "share" / "corex"

    return Path.cwd() / "data"


def get_default_log_dir() -> Path:
    """Return the preferred log directory."""
    env = os.environ.get("COREX_LOG_DIR")
    if env:
        return Path(env)
    return get_default_data_dir() / "logs"


def get_prometheus_multiproc_dir() -> Path:
    """Return a writable directory for Prometheus multiprocess metrics."""
    if IS_WINDOWS:
        base = os.environ.get("TMP") or os.environ.get("TEMP") or str(get_default_data_dir())
    else:
        base = os.environ.get("PROMETHEUS_MULTIPROC_DIR") or "/tmp"
    path = Path(base) / "prometheus"
    path.mkdir(parents=True, exist_ok=True)
    return path


def resolve_path(path: str | Path) -> Path:
    """Resolve a path, expanding ~ and environment variables, normalizing for Windows."""
    expanded = os.path.expandvars(os.path.expanduser(str(path)))
    return Path(expanded).resolve()


def safe_join(*parts: str) -> Path:
    """Join path parts safely, handling Windows drive letters."""
    return Path(*parts)


# ── Windows Registry ───────────────────────────────────────────────────

REGISTRY_PATH = r"SOFTWARE\Corex\AIGateway"

_registry_cache: dict[str, str] = {}


def read_registry(key: str, default: str = "") -> str:
    """Read a value from the Windows Registry (HKLM).

    Falls back to environment variable COREX_{KEY} or default.
    """
    if key in _registry_cache:
        return _registry_cache[key]

    env_key = f"COREX_{key.upper()}"
    env_val = os.environ.get(env_key)
    if env_val:
        _registry_cache[key] = env_val
        return env_val

    if not IS_WINDOWS:
        _registry_cache[key] = default
        return default

    try:
        import winreg
        with winreg.OpenKey(winreg.HKEY_LOCAL_MACHINE, REGISTRY_PATH, 0, winreg.KEY_READ) as hkey:
            value, _ = winreg.QueryValueEx(hkey, key)
            _registry_cache[key] = str(value)
            return str(value)
    except (ImportError, OSError):
        pass

    _registry_cache[key] = default
    return default


def write_registry(key: str, value: str) -> None:
    """Write a value to the Windows Registry (HKLM). Creates key if missing."""
    if not IS_WINDOWS:
        return

    try:
        import winreg
        hkey = winreg.CreateKey(winreg.HKEY_LOCAL_MACHINE, REGISTRY_PATH)
        winreg.SetValueEx(hkey, key, 0, winreg.REG_SZ, value)
        winreg.CloseKey(hkey)
        _registry_cache[key] = value
        logger.info("registry_written", key=key, path=REGISTRY_PATH)
    except (ImportError, PermissionError) as e:
        logger.warning("registry_write_failed", key=key, error=str(e))


def delete_registry_key(key: str) -> None:
    """Delete a value from the Windows Registry."""
    if not IS_WINDOWS:
        return

    try:
        import winreg
        with winreg.OpenKey(winreg.HKEY_LOCAL_MACHINE, REGISTRY_PATH, 0, winreg.KEY_WRITE) as hkey:
            winreg.DeleteValue(hkey, key)
        _registry_cache.pop(key, None)
    except (ImportError, OSError, PermissionError) as e:
        logger.warning("registry_delete_failed", key=key, error=str(e))


# ── Windows Event Log ──────────────────────────────────────────────────

EVENT_LOG_SOURCE = "CorexAIGateway"


def register_event_log_source() -> bool:
    """Register the Event Log source for the AI Gateway."""
    if not IS_WINDOWS:
        return False

    try:
        import win32evtlog
        import win32evtlogutil
        win32evtlogutil.AddSourceToRegistry(
            EVENT_LOG_SOURCE,
            application_message_file=None,
            category_count=0,
        )
        return True
    except ImportError:
        logger.warning("pywin32 not installed; Windows Event Log unavailable")
        return False
    except Exception as e:
        logger.warning("event_log_source_registration_failed", error=str(e))
        return False


def write_event_log(
    message: str,
    level: str = "info",
    event_id: int = 1000,
) -> None:
    """Write a message to the Windows Application Event Log.

    Falls back to structlog if pywin32 is not available.
    """
    if not IS_WINDOWS:
        return

    try:
        import win32evtlogutil
        type_map = {
            "info": win32evtlogutil.EVENTLOG_INFORMATION_TYPE,
            "warning": win32evtlogutil.EVENTLOG_WARNING_TYPE,
            "error": win32evtlogutil.EVENTLOG_ERROR_TYPE,
        }
        event_type = type_map.get(level, win32evtlogutil.EVENTLOG_INFORMATION_TYPE)
        win32evtlogutil.ReportEvent(
            EVENT_LOG_SOURCE,
            event_id,
            0,
            event_type,
            None,
            message,
            None,
        )
    except ImportError:
        pass
    except Exception as e:
        logger.warning("event_log_write_failed", error=str(e))


# ── Windows Process Utilities ──────────────────────────────────────────

def get_process_priority() -> str:
    """Return the current process priority class."""
    if not IS_WINDOWS:
        return "normal"

    try:
        import win32process
        import win32con
        handle = win32process.GetCurrentProcess()
        priority = win32process.GetPriorityClass(handle)
        mapping = {
            win32con.IDLE_PRIORITY_CLASS: "idle",
            win32con.BELOW_NORMAL_PRIORITY_CLASS: "below_normal",
            win32con.NORMAL_PRIORITY_CLASS: "normal",
            win32con.ABOVE_NORMAL_PRIORITY_CLASS: "above_normal",
            win32con.HIGH_PRIORITY_CLASS: "high",
            win32con.REALTIME_PRIORITY_CLASS: "realtime",
        }
        return mapping.get(priority, "normal")
    except ImportError:
        return "normal"


def set_high_performance() -> None:
    """Set Windows power policy to high performance for this process."""
    if not IS_WINDOWS:
        return

    try:
        import win32process
        import win32con
        handle = win32process.GetCurrentProcess()
        win32process.SetPriorityClass(handle, win32con.HIGH_PRIORITY_CLASS)
        logger.info("process_priority_set", priority="high")
    except ImportError:
        pass
    except Exception as e:
        logger.warning("set_priority_failed", error=str(e))


def set_tcp_fast_open() -> None:
    """Enable TCP Fast Open on Windows — no-op for now but reserved.

    On modern Windows 10+ builds, TCP Fast Open is enabled by default
    via the registry key:
      HKLM\\SYSTEM\\CurrentControlSet\\Services\\Tcpip\\Parameters\\EnableTCPFastOpen
    """
    pass


def configure_iocp() -> None:
    """Configure IOCP thread pool for async I/O on Windows.

    Uvicorn uses asyncio's proactor event loop on Windows which already
    uses IOCP internally. This is a placeholder for any additional tuning.
    """
    if IS_WINDOWS:
        import asyncio
        try:
            asyncio.set_event_loop_policy(asyncio.WindowsProactorEventLoopPolicy())
            logger.info("iocp_proactor_configured")
        except AttributeError:
            pass
