from __future__ import annotations

import re
from typing import Any, Dict, List, Optional

from structlog import get_logger

logger = get_logger(__name__)


def estimate_tokens(text: str) -> int:
    if not text:
        return 0
    return len(text) // 3 + len(re.findall(r"\s+", text))


def count_tokens(text: str) -> int:
    return estimate_tokens(text)


class ContextWindowOptimizer:
    MODEL_CONTEXTS: Dict[str, int] = {
        "gpt-4o": 128000,
        "gpt-4o-mini": 128000,
        "gpt-4-turbo": 128000,
        "gpt-4": 8192,
        "gpt-3.5-turbo": 16385,
        "claude-3-opus-20240229": 200000,
        "claude-3-sonnet-20240229": 200000,
        "claude-3-haiku-20240307": 200000,
        "claude-3-5-sonnet-20240620": 200000,
        "llama3-70b-8192": 8192,
        "llama3-8b-8192": 8192,
        "mixtral-8x7b-32768": 32768,
        "gemma2-9b-it": 8192,
        "deepseek-chat": 65536,
        "deepseek-coder": 65536,
    }

    CONTEXT_RESERVE_RATIO = 0.2

    @classmethod
    def get_context_limit(cls, model: str) -> int:
        for model_key, limit in cls.MODEL_CONTEXTS.items():
            if model_key in model:
                return limit
        return 128000

    @classmethod
    def get_max_output_tokens(cls, model: str, requested_max_tokens: Optional[int] = None) -> int:
        context_limit = cls.get_context_limit(model)
        reserve = int(context_limit * cls.CONTEXT_RESERVE_RATIO)
        max_output = context_limit - reserve
        if requested_max_tokens:
            return min(requested_max_tokens, max_output)
        return max_output

    @classmethod
    def get_input_budget(cls, model: str, requested_max_tokens: int = 4096) -> int:
        context_limit = cls.get_context_limit(model)
        return context_limit - requested_max_tokens - int(context_limit * 0.05)

    @classmethod
    def truncate_messages(
        cls,
        messages: List[Dict[str, str]],
        model: str,
        max_tokens: int = 4096,
        system_preserved: bool = True,
    ) -> List[Dict[str, str]]:
        budget = cls.get_input_budget(model, max_tokens)

        preserved: List[Dict[str, str]] = []
        candidates: List[Dict[str, str]] = []

        for msg in messages:
            if system_preserved and msg.get("role") == "system":
                preserved.append(msg)
            else:
                candidates.append(msg)

        total_tokens = sum(count_tokens(m.get("content", "")) for m in preserved)

        for msg in candidates:
            msg_tokens = count_tokens(msg.get("content", ""))
            if total_tokens + msg_tokens <= budget:
                preserved.append(msg)
                total_tokens += msg_tokens
            else:
                continue

        return preserved


class TokenOptimizer:
    def __init__(self):
        self.context_optimizer = ContextWindowOptimizer()

    def optimize_messages(
        self,
        messages: List[Dict[str, str]],
        model: str,
        max_tokens: int = 4096,
    ) -> List[Dict[str, str]]:
        return self.context_optimizer.truncate_messages(messages, model, max_tokens)

    def optimize_prompt(
        self,
        messages: List[Dict[str, str]],
        model: str,
        max_tokens: int = 4096,
    ) -> List[Dict[str, str]]:
        optimized = self.optimize_messages(messages, model, max_tokens)
        return self._deduplicate_consecutive(optimized)

    def _deduplicate_consecutive(
        self, messages: List[Dict[str, str]]
    ) -> List[Dict[str, str]]:
        if not messages:
            return messages

        result = [messages[0]]
        for msg in messages[1:]:
            if msg["role"] == result[-1]["role"]:
                result[-1]["content"] += "\n" + msg["content"]
            else:
                result.append(msg)
        return result

    def estimate_request_cost(
        self,
        messages: List[Dict[str, str]],
        model: str,
        pricing: Dict[str, float],
    ) -> float:
        total_input = sum(count_tokens(m.get("content", "")) for m in messages)
        input_rate = pricing.get("input", 0) / 1_000_000
        output_rate = pricing.get("output", 0) / 1_000_000
        estimated_output = min(total_input, 4096)
        return (total_input * input_rate) + (estimated_output * output_rate)

    def should_cache(
        self,
        messages: List[Dict[str, str]],
        min_save_tokens: int = 500,
    ) -> bool:
        if len(messages) < 4:
            return False
        total = sum(count_tokens(m.get("content", "")) for m in messages)
        system_tokens = sum(
            count_tokens(m.get("content", ""))
            for m in messages
            if m.get("role") == "system"
        )
        return total > min_save_tokens and system_tokens > 0

    def get_cache_key(self, messages: List[Dict[str, str]], model: str) -> str:
        import hashlib
        import json

        key_parts = {
            "model": model,
            "messages": [
                {"role": m["role"], "content": m["content"][:200]}
                for m in messages
                if m.get("role") in ("system", "user")
            ],
        }
        raw = json.dumps(key_parts, sort_keys=True)
        return f"tcache:{model}:{hashlib.md5(raw.encode()).hexdigest()}"


token_optimizer = TokenOptimizer()
