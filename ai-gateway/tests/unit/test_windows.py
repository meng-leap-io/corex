"""Tests for Windows-specific utilities (path handling, registry, event log).

These tests verify the cross-platform fallback behavior.
Windows-specific functionality (pywin32, winreg) is conditionally tested.
"""

from __future__ import annotations

import os
from pathlib import Path
from unittest.mock import MagicMock, patch

import pytest

from app.core.windows import (
    IS_WINDOWS,
    EVENT_LOG_SOURCE,
    configure_iocp,
    get_default_data_dir,
    get_default_log_dir,
    get_hostname,
    get_process_priority,
    get_prometheus_multiproc_dir,
    read_registry,
    resolve_path,
    safe_join,
    set_high_performance,
    set_tcp_fast_open,
    write_event_log,
    write_registry,
    register_event_log_source,
)


class TestPlatformDetection:
    def test_get_hostname_uses_env(self):
        with patch.dict(os.environ, {"COMPUTERNAME": "WIN-PC"}, clear=True):
            assert get_hostname() == "WIN-PC"

    def test_get_hostname_fallback(self):
        with patch.dict(os.environ, {}, clear=True):
            assert get_hostname() == "localhost"

    def test_get_hostname_posix(self):
        with patch.dict(os.environ, {"HOSTNAME": "linux-box"}, clear=True):
            assert get_hostname() == "linux-box"


class TestPathResolution:
    def test_get_default_data_dir_from_env(self):
        with patch.dict(os.environ, {"COREX_DATA_DIR": "/custom/data"}):
            assert get_default_data_dir() == Path("/custom/data")

    def test_get_default_data_dir_posix(self):
        with patch.dict(os.environ, {"HOME": "/home/user"}, clear=True):
            path = get_default_data_dir()
            assert str(path).endswith(".local/share/corex")

    def test_get_default_log_dir_from_env(self):
        with patch.dict(os.environ, {"COREX_LOG_DIR": "/var/log/corex"}):
            assert get_default_log_dir() == Path("/var/log/corex")

    def test_get_default_log_dir_fallback(self):
        with patch.dict(os.environ, {"HOME": "/home/user"}, clear=True):
            path = get_default_log_dir()
            assert str(path).endswith("logs")

    def test_get_prometheus_multiproc_dir(self):
        import tempfile
        with tempfile.TemporaryDirectory() as tmpdir:
            if IS_WINDOWS:
                with patch.dict(os.environ, {"TMP": tmpdir}, clear=True):
                    path = get_prometheus_multiproc_dir()
                    assert path.name == "prometheus"
            else:
                metrics_dir = str(Path(tmpdir) / "metrics")
                with patch.dict(os.environ, {"PROMETHEUS_MULTIPROC_DIR": metrics_dir}, clear=True):
                    path = get_prometheus_multiproc_dir()
                    assert path.name == "prometheus"
                    assert path.parent.samefile(Path(metrics_dir))

    def test_resolve_path_expands_vars_and_user(self):
        with patch.dict(os.environ, {"CUSTOM_DIR": "/my/dir"}):
            result = resolve_path("$CUSTOM_DIR/sub")
            assert str(result) == "/my/dir/sub"

    def test_safe_join(self):
        result = safe_join("C:", "Program Files", "Corex")
        assert str(result) == "C:/Program Files/Corex" or str(result) == "C:\\Program Files\\Corex"


class TestRegistryNonWindows:
    def test_read_registry_default(self):
        with patch.dict(os.environ, {}, clear=True):
            val = read_registry("test_key", "default_val")
            assert val == "default_val"

    def test_read_registry_from_env(self):
        from app.core.windows import _registry_cache
        _registry_cache.clear()
        with patch.dict(os.environ, {"COREX_TEST_KEY": "env_value"}, clear=True):
            val = read_registry("test_key", "default")
            assert val == "env_value"

    def test_write_registry_noop_on_linux(self):
        write_registry("test", "value")  # should not raise


class TestEventLog:
    def test_write_event_log_noop_on_linux(self):
        write_event_log("test message", level="info")  # should not raise

    def test_register_event_log_source_noop_on_linux(self):
        result = register_event_log_source()
        assert result is False

    def test_event_log_source_constant(self):
        assert EVENT_LOG_SOURCE == "CorexAIGateway"


class TestPerformance:
    def test_set_high_performance_noop_on_linux(self):
        set_high_performance()  # should not raise

    def test_set_tcp_fast_open_noop(self):
        set_tcp_fast_open()  # should not raise

    def test_configure_iocp_noop_on_linux(self):
        configure_iocp()  # should not raise

    def test_get_process_priority_default(self):
        prio = get_process_priority()
        if IS_WINDOWS:
            assert prio in ("idle", "below_normal", "normal", "above_normal", "high", "realtime")
        else:
            assert prio == "normal"


class TestOllamaProviderStrategy:
    def test_provider_strategy_enum(self):
        from app.core.config import ProviderStrategy
        assert ProviderStrategy.AUTO.value == "auto"
        assert ProviderStrategy.LOCAL_ONLY.value == "local_only"
        assert ProviderStrategy.REMOTE_ONLY.value == "remote_only"
        assert ProviderStrategy.LOCAL_FIRST.value == "local_first"
        assert ProviderStrategy.REMOTE_FIRST.value == "remote_first"

    def _make_settings(self, strategy: str):
        """Create a settings instance with a given provider strategy."""
        from app.core.config import Settings
        # Use the alias name PROVIDER_STRATEGY (Pydantic v2 alias handling)
        return Settings(
            PROVIDER_STRATEGY=strategy,
            OLLAMA_ENABLED=True,
        )

    def test_get_effective_providers_local_only(self):
        s = self._make_settings("local_only")
        providers = s.get_effective_providers()
        assert providers == ["ollama"]

    def test_get_effective_providers_remote_only(self):
        s = self._make_settings("remote_only")
        providers = s.get_effective_providers()
        assert "ollama" not in providers

    def test_get_effective_providers_local_first(self):
        s = self._make_settings("local_first")
        providers = s.get_effective_providers()
        assert providers[0] == "ollama"

    def test_get_effective_providers_remote_first(self):
        s = self._make_settings("remote_first")
        providers = s.get_effective_providers()
        assert providers[-1] == "ollama"

    def test_get_effective_providers_auto(self):
        s = self._make_settings("auto")
        providers = s.get_effective_providers()
        assert "ollama" in providers
        assert "openai" in providers
