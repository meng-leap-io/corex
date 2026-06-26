from __future__ import annotations

import re
from typing import Any, Dict, List, Optional

from structlog import get_logger

logger = get_logger(__name__)


class InputSanitizer:
    BLOCKED_PATTERNS_HTML = [
        (re.compile(r'<script[\s>]', re.IGNORECASE), "script_tag"),
        (re.compile(r'javascript\s*:', re.IGNORECASE), "javascript_protocol"),
        (re.compile(r'onerror\s*=', re.IGNORECASE), "onerror_handler"),
        (re.compile(r'onload\s*=', re.IGNORECASE), "onload_handler"),
        (re.compile(r'onclick\s*=', re.IGNORECASE), "onclick_handler"),
        (re.compile(r'onmouseover\s*=', re.IGNORECASE), "onmouseover_handler"),
        (re.compile(r'onfocus\s*=', re.IGNORECASE), "onfocus_handler"),
        (re.compile(r'onchange\s*=', re.IGNORECASE), "onchange_handler"),
        (re.compile(r'<embed[\s>]', re.IGNORECASE), "embed_tag"),
        (re.compile(r'<object[\s>]', re.IGNORECASE), "object_tag"),
        (re.compile(r'<applet[\s>]', re.IGNORECASE), "applet_tag"),
        (re.compile(r'<iframe[\s>]', re.IGNORECASE), "iframe_tag"),
        (re.compile(r'<link[\s>]', re.IGNORECASE), "link_tag"),
        (re.compile(r'<meta[\s>]', re.IGNORECASE), "meta_tag"),
        (re.compile(r'<style[\s>]', re.IGNORECASE), "style_tag"),
        (re.compile(r'expression\s*\(', re.IGNORECASE), "css_expression"),
        (re.compile(r'data\s*:\s*text/html', re.IGNORECASE), "data_uri_html"),
    ]

    BLOCKED_PATTERNS_SQL = [
        (re.compile(r'\bUNION\b.*\bSELECT\b', re.IGNORECASE), "union_select"),
        (re.compile(r'\bSELECT\b.*\bFROM\b', re.IGNORECASE), "select_from"),
        (re.compile(r'\bDROP\s+TABLE\b', re.IGNORECASE), "drop_table"),
        (re.compile(r'\bDELETE\s+FROM\b', re.IGNORECASE), "delete_from"),
        (re.compile(r'\bINSERT\s+INTO\b', re.IGNORECASE), "insert_into"),
        (re.compile(r'\bUPDATE\s+\w+\s+SET\b', re.IGNORECASE), "update_set"),
        (re.compile(r'\bCREATE\s+TABLE\b', re.IGNORECASE), "create_table"),
        (re.compile(r'\bALTER\s+TABLE\b', re.IGNORECASE), "alter_table"),
        (re.compile(r'\bTRUNCATE\s+TABLE\b', re.IGNORECASE), "truncate"),
        (re.compile(r'\bEXEC\s*\(', re.IGNORECASE), "exec_call"),
        (re.compile(r'\bEXECUTE\s*\(', re.IGNORECASE), "execute_call"),
        (re.compile(r'\bXP_CMDSHELL\b', re.IGNORECASE), "xp_cmdshell"),
        (re.compile(r'\bSP_EXECUTESQL\b', re.IGNORECASE), "sp_executesql"),
        (re.compile(r'\bWAITFOR\s+DELAY\b', re.IGNORECASE), "waitfor_delay"),
        (re.compile(r'\bPG_SLEEP\b', re.IGNORECASE), "pg_sleep"),
        (re.compile(r'\bSLEEP\s*\(', re.IGNORECASE), "sleep_call"),
        (re.compile(r'\bBENCHMARK\s*\(', re.IGNORECASE), "benchmark"),
    ]

    BLOCKED_PATTERNS_PATH = [
        (re.compile(r'\.\.(?:\\|/|%2f|%5c)'), "path_traversal"),
        (re.compile(r'(?:/etc/passwd|/etc/shadow|/etc/hosts)'), "system_file"),
        (re.compile(r'\.git/config'), "git_config"),
    ]

    BLOCKED_PATTERNS_CMD = [
        (re.compile(r'[|;&]\s*(?:id|whoami|uname|cat|chmod|chown|rm\s+-rf|wget|curl|bash|sh|python|perl|ruby)\s', re.IGNORECASE), "command_injection"),
        (re.compile(r'`[^`]+`'), "backtick_command"),
        (re.compile(r'\$\([^)]+\)'), "subshell_command"),
    ]

    SHELL_METACHARACTERS = re.compile(r'[|;&$`\'"()\[\]{}<>#!*?~]')

    @classmethod
    def sanitize_string(cls, value: str, max_length: int = 10000) -> str:
        if not isinstance(value, str):
            return ""
        if len(value) > max_length:
            value = value[:max_length]
        return value.strip()

    @classmethod
    def strip_html(cls, value: str) -> str:
        return re.sub(r'<[^>]*>', '', value)

    @classmethod
    def has_malicious_content(cls, value: str) -> tuple[bool, Optional[str]]:
        if not isinstance(value, str):
            return False, None

        for pattern, name in cls.BLOCKED_PATTERNS_HTML:
            if pattern.search(value):
                logger.warning("input_sanitizer.blocked", pattern=name, type="html")
                return True, name

        for pattern, name in cls.BLOCKED_PATTERNS_SQL:
            if pattern.search(value):
                logger.warning("input_sanitizer.blocked", pattern=name, type="sql")
                return True, name

        for pattern, name in cls.BLOCKED_PATTERNS_PATH:
            if pattern.search(value):
                logger.warning("input_sanitizer.blocked", pattern=name, type="path_traversal")
                return True, name

        for pattern, name in cls.BLOCKED_PATTERNS_CMD:
            if pattern.search(value):
                logger.warning("input_sanitizer.blocked", pattern=name, type="command_injection")
                return True, name

        return False, None

    @classmethod
    def sanitize_object(cls, obj: Any, max_depth: int = 5) -> Any:
        if max_depth <= 0:
            return str(obj)[:100] if obj else obj

        if isinstance(obj, str):
            return cls.sanitize_string(obj)

        if isinstance(obj, dict):
            return {
                str(k): cls.sanitize_object(v, max_depth - 1)
                for k, v in obj.items()
            }

        if isinstance(obj, list):
            return [cls.sanitize_object(item, max_depth - 1) for item in obj]

        if isinstance(obj, (int, float, bool)):
            return obj

        if obj is None:
            return None

        return str(obj)[:1000]

    @classmethod
    def contains_shell_metacharacters(cls, value: str) -> bool:
        return bool(cls.SHELL_METACHARACTERS.search(value))


class OutputFilter:
    SENSITIVE_FIELDS = {
        "password", "secret", "token", "api_key", "api-key",
        "access_token", "refresh_token", "jwt", "authorization",
        "credit_card", "cvv", "ssn", "pin", "private_key",
        "ssh_key", "passphrase", "auth_token", "bearer",
    }

    REDACTED_TEXT = "***REDACTED***"

    @classmethod
    def filter_response(cls, data: Any, depth: int = 0, max_depth: int = 10) -> Any:
        if depth > max_depth:
            return cls.REDACTED_TEXT if isinstance(data, (dict, list)) else data

        if isinstance(data, dict):
            return {
                key: cls.REDACTED_TEXT if cls._is_sensitive(key) else cls.filter_response(val, depth + 1, max_depth)
                for key, val in data.items()
            }

        if isinstance(data, list):
            return [cls.filter_response(item, depth + 1, max_depth) for item in data]

        if isinstance(data, str) and len(data) > 10000:
            return data[:10000] + "..."

        return data

    @classmethod
    def _is_sensitive(cls, key: str) -> bool:
        key_lower = key.lower().replace("-", "_")
        return any(sensitive in key_lower for sensitive in cls.SENSITIVE_FIELDS)

    @classmethod
    def sanitize_prompt_output(cls, content: str) -> str:
        content = re.sub(
            r'(?i)(api[-_]?key|secret|token|bearer)\s*[:=]\s*["\']?[a-zA-Z0-9_\-\.]{16,}["\']?',
            r'\1: ***REDACTED***',
            content,
        )

        content = re.sub(
            r'(?i)(sk-[a-zA-Z0-9]{32,}|pk-[a-zA-Z0-9]{32,})',
            'sk-***REDACTED***',
            content,
        )

        content = re.sub(
            r'\b(?:\d{4}[-\s]?){3}\d{4}\b',
            '****-****-****-****',
            content,
        )

        return content

    @classmethod
    def sanitize_error_message(cls, error: str) -> str:
        error = re.sub(
            r"(?i)(?:path|file|line|stack)\s*[:=]\s*[^\s,;]+",
            "***REDACTED***",
            error,
        )
        return error[:500]
