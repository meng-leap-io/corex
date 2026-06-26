from __future__ import annotations

import hashlib
import hmac
import secrets
import time
from datetime import datetime, timedelta, timezone
from typing import Any, Dict, List, Optional, Tuple

from structlog import get_logger

logger = get_logger(__name__)


class APIKeyManager:
    def __init__(self, rotation_interval_days: int = 90):
        self._rotation_interval = timedelta(days=rotation_interval_days)
        self._key_store: Dict[str, Dict[str, Any]] = {}
        self._current_key_id: Optional[str] = None

    def generate_key(self, prefix: str = "cx") -> Tuple[str, str]:
        key_bytes = secrets.token_bytes(32)
        key_id = hashlib.sha256(key_bytes).hexdigest()[:16]
        raw_key = f"{prefix}_{secrets.token_hex(24)}"

        self._key_store[key_id] = {
            "key_hash": self._hash_key(raw_key),
            "created_at": datetime.now(timezone.utc),
            "active": True,
            "prefix": prefix,
        }

        self._current_key_id = key_id

        logger.info("api_key.generated", key_id=key_id, prefix=prefix)
        return key_id, raw_key

    def validate_key(self, raw_key: str) -> Optional[str]:
        key_hash = self._hash_key(raw_key)

        for key_id, metadata in self._key_store.items():
            if not metadata["active"]:
                continue
            if hmac.compare_digest(metadata["key_hash"], key_hash):
                return key_id

        return None

    def revoke_key(self, key_id: str) -> bool:
        if key_id not in self._key_store:
            logger.warning("api_key.revoke_not_found", key_id=key_id)
            return False

        self._key_store[key_id]["active"] = False
        self._key_store[key_id]["revoked_at"] = datetime.now(timezone.utc)

        if self._current_key_id == key_id:
            self._current_key_id = None

        logger.info("api_key.revoked", key_id=key_id)
        return True

    def rotate_key(self, prefix: str = "cx") -> Tuple[str, str, str]:
        if self._current_key_id:
            old_key_id = self._current_key_id
            self.revoke_key(old_key_id)
        else:
            old_key_id = ""

        new_key_id, raw_key = self.generate_key(prefix)
        logger.info("api_key.rotated", old_key_id=old_key_id, new_key_id=new_key_id)

        return old_key_id, new_key_id, raw_key

    def get_key_metadata(self, key_id: str) -> Optional[Dict[str, Any]]:
        return self._key_store.get(key_id)

    def list_active_keys(self) -> List[str]:
        return [
            key_id
            for key_id, metadata in self._key_store.items()
            if metadata["active"]
        ]

    def list_expired_keys(self) -> List[str]:
        now = datetime.now(timezone.utc)
        return [
            key_id
            for key_id, metadata in self._key_store.items()
            if not metadata["active"]
            and (now - metadata.get("revoked_at", metadata["created_at"])) > self._rotation_interval
        ]

    def cleanup_expired_keys(self, max_age_days: int = 180) -> int:
        now = datetime.now(timezone.utc)
        cutoff = now - timedelta(days=max_age_days)
        expired = [
            key_id
            for key_id, metadata in self._key_store.items()
            if not metadata["active"]
            and metadata["created_at"] < cutoff
        ]

        for key_id in expired:
            del self._key_store[key_id]

        if expired:
            logger.info("api_key.cleanup", count=len(expired))

        return len(expired)

    def get_current_key_id(self) -> Optional[str]:
        return self._current_key_id

    @staticmethod
    def _hash_key(raw_key: str) -> str:
        return hashlib.sha512(raw_key.encode()).hexdigest()

    @property
    def active_key_count(self) -> int:
        return len(self.list_active_keys())

    @property
    def total_key_count(self) -> int:
        return len(self._key_store)
