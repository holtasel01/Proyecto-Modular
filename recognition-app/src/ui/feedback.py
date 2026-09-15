"""Retroalimentación visual al estudiante frente a la cámara (RF6, docs/02-diseno.md §6.2).

Antes de esta etapa, `main.py` solo imprimía el resultado del reconocimiento en la
consola. Esto dibuja ese mismo resultado sobre el propio frame de la cámara, para
que el estudiante lo vea en pantalla sin que nadie tenga que mirar una terminal.
"""

from __future__ import annotations

import time

import cv2

# BGR (OpenCV), no RGB
_COLORS = {
    "info": (210, 210, 210),
    "good": (90, 200, 90),
    "warn": (60, 170, 230),
    "bad": (70, 70, 220),
}


class FeedbackOverlay:
    """Guarda el último mensaje de reconocimiento y lo dibuja sobre cada frame
    hasta que expira (`duration`), momento en el que vuelve al mensaje de reposo."""

    def __init__(self, idle_text: str = "Facelog - colócate frente a la cámara") -> None:
        self._idle_text = idle_text
        self._text = idle_text
        self._level = "info"
        self._expires_at = 0.0

    def set(self, text: str, level: str = "info", duration: float = 3.0) -> None:
        self._text = text
        self._level = level if level in _COLORS else "info"
        self._expires_at = time.time() + duration

    def current_text(self) -> str:
        return self._text if time.time() < self._expires_at else self._idle_text

    def current_color(self) -> tuple[int, int, int]:
        return _COLORS[self._level] if time.time() < self._expires_at else _COLORS["info"]

    def draw(self, frame):
        """Dibuja una barra inferior con el mensaje actual. Devuelve el mismo
        frame modificado in-place (evita copiar el array en cada iteración)."""
        h, w = frame.shape[:2]
        bar_height = 46
        cv2.rectangle(frame, (0, h - bar_height), (w, h), (32, 32, 32), thickness=-1)
        cv2.putText(
            frame,
            self.current_text(),
            (14, h - 16),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.62,
            self.current_color(),
            2,
            cv2.LINE_AA,
        )
        return frame
