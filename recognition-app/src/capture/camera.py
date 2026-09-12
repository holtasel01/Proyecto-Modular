"""Acceso a la cámara del laboratorio vía OpenCV (docs/02-diseno.md §2.3)."""

from __future__ import annotations

import cv2


class Camera:
    def __init__(self, index: int = 0):
        self._index = index
        self._cap: cv2.VideoCapture | None = None

    def __enter__(self) -> "Camera":
        self._cap = cv2.VideoCapture(self._index)
        if not self._cap.isOpened():
            raise RuntimeError(f"No se pudo abrir la cámara (índice {self._index}).")
        return self

    def __exit__(self, exc_type, exc_val, exc_tb) -> None:
        if self._cap is not None:
            self._cap.release()

    def read(self):
        """Devuelve un frame BGR (numpy array) o None si falló la captura."""
        ok, frame = self._cap.read()
        return frame if ok else None
