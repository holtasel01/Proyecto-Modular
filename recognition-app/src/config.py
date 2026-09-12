"""Carga la configuración desde .env (ver config.example.env)."""

from __future__ import annotations

import os
from dataclasses import dataclass

from dotenv import load_dotenv


@dataclass(frozen=True)
class Config:
    api_base_url: str
    device_token: str
    device_name: str
    min_confidence_reject: float
    duplicate_window_seconds: int
    sync_interval_seconds: int
    local_queue_db_path: str
    catalog_cache_path: str
    camera_index: int


def load_config() -> Config:
    load_dotenv()

    return Config(
        api_base_url=os.getenv("API_BASE_URL", "http://localhost:8000/api"),
        device_token=os.getenv("DEVICE_TOKEN", ""),
        device_name=os.getenv("DEVICE_NAME", "recognition-app"),
        min_confidence_reject=float(os.getenv("MIN_CONFIDENCE_REJECT", "0.50")),
        duplicate_window_seconds=int(os.getenv("DUPLICATE_WINDOW_SECONDS", "30")),
        sync_interval_seconds=int(os.getenv("SYNC_INTERVAL_SECONDS", "300")),
        local_queue_db_path=os.getenv("LOCAL_QUEUE_DB_PATH", "./queue.sqlite3"),
        catalog_cache_path=os.getenv("CATALOG_CACHE_PATH", "./face_catalog_cache.json"),
        camera_index=int(os.getenv("CAMERA_INDEX", "0")),
    )
