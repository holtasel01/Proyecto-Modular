"""Descarga y cachea localmente el catálogo de embeddings de estudiantes
activos (GET /api/sync/face-catalog), para poder comparar sin depender de la
red en cada reconocimiento (docs/02-diseno.md §10).
"""

from __future__ import annotations

import json
import time
from pathlib import Path

from src.api_client.client import ApiClient, ApiConnectionError


class FaceCatalog:
    def __init__(self, api_client: ApiClient, cache_path: str, refresh_interval_seconds: int = 300):
        self._api_client = api_client
        self._cache_path = Path(cache_path)
        self._refresh_interval = refresh_interval_seconds
        self._entries: list[dict] = []
        self._last_refresh = 0.0
        self._load_from_disk()

    def _load_from_disk(self) -> None:
        try:
            self._entries = json.loads(self._cache_path.read_text(encoding="utf-8"))
        except (FileNotFoundError, json.JSONDecodeError):
            self._entries = []

    def _save_to_disk(self) -> None:
        self._cache_path.parent.mkdir(parents=True, exist_ok=True)
        self._cache_path.write_text(json.dumps(self._entries), encoding="utf-8")

    def refresh_if_needed(self, force: bool = False) -> None:
        if not force and (time.time() - self._last_refresh) < self._refresh_interval:
            return

        try:
            self._entries = self._api_client.fetch_face_catalog()
            self._save_to_disk()
            self._last_refresh = time.time()
        except ApiConnectionError:
            # Sin conexión: seguimos operando con la última copia cacheada en disco.
            pass

    def entries(self) -> list[dict]:
        return self._entries
