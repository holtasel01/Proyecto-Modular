"""Estado del overlay de retroalimentación visual (src/ui/feedback.py).
No prueba el renderizado en sí (requeriría OCR sobre píxeles); prueba la
máquina de estados: qué mensaje/color está activo antes y después de expirar.
"""

from __future__ import annotations

import time

import numpy as np

from src.ui.feedback import FeedbackOverlay


def test_starts_with_idle_message():
    overlay = FeedbackOverlay(idle_text="esperando")
    assert overlay.current_text() == "esperando"


def test_set_message_is_shown_until_it_expires():
    overlay = FeedbackOverlay(idle_text="esperando")
    overlay.set("Bienvenido, A001", level="good", duration=0.05)

    assert overlay.current_text() == "Bienvenido, A001"
    time.sleep(0.08)
    assert overlay.current_text() == "esperando"


def test_unknown_level_falls_back_to_info():
    overlay = FeedbackOverlay()
    overlay.set("mensaje", level="no-existe", duration=1)
    # no debe lanzar excepción y debe quedar en un color válido (tupla BGR de 3 enteros)
    color = overlay.current_color()
    assert len(color) == 3


def test_draw_preserves_frame_shape_and_does_not_raise():
    overlay = FeedbackOverlay()
    overlay.set("Hasta luego, A001", level="good", duration=2)
    frame = np.zeros((240, 320, 3), dtype="uint8")

    out = overlay.draw(frame)

    assert out.shape == frame.shape
