"""Cola local (SQLite) de eventos de asistencia pendientes de enviar, para
tolerar cortes de conexión entre el laboratorio y el backend (RF7/RNF1,
docs/02-diseno.md §10). Un archivo, sin servidor adicional.
"""

from __future__ import annotations

import sqlite3
import time
from pathlib import Path


class Outbox:
    def __init__(self, db_path: str):
        self._db_path = db_path
        Path(db_path).parent.mkdir(parents=True, exist_ok=True)
        with self._connect() as conn:
            conn.execute(
                """
                CREATE TABLE IF NOT EXISTS pending_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    student_id INTEGER NOT NULL,
                    confidence REAL NOT NULL,
                    created_at REAL NOT NULL
                )
                """
            )

    def _connect(self) -> sqlite3.Connection:
        return sqlite3.connect(self._db_path)

    def enqueue(self, student_id: int, confidence: float) -> None:
        with self._connect() as conn:
            conn.execute(
                "INSERT INTO pending_events (student_id, confidence, created_at) VALUES (?, ?, ?)",
                (student_id, confidence, time.time()),
            )

    def pending(self) -> list[tuple[int, int, float]]:
        """Devuelve [(id, student_id, confidence), ...] en orden de llegada."""
        with self._connect() as conn:
            cursor = conn.execute(
                "SELECT id, student_id, confidence FROM pending_events ORDER BY id"
            )
            return cursor.fetchall()

    def remove(self, row_id: int) -> None:
        with self._connect() as conn:
            conn.execute("DELETE FROM pending_events WHERE id = ?", (row_id,))
