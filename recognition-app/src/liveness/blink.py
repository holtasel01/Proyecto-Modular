"""Liveness básico por detección de parpadeo (Eye Aspect Ratio) usando
MediaPipe FaceMesh — docs/02-diseno.md §11.

Limitación documentada a propósito: esto bloquea el ataque más simple (foto
impresa o imagen estática en un teléfono/monitor), pero NO bloquea un ataque
con un video en reproducción que incluya parpadeos. Se documenta como
limitación aceptada para el alcance de un proyecto universitario; un modelo
de anti-spoofing dedicado queda como mejora futura (Etapa 1 §16).
"""

from __future__ import annotations

import math

import mediapipe as mp

# Índices de landmarks de MediaPipe FaceMesh para cada ojo, en el orden
# (esquina, párpado sup. 1, párpado sup. 2, esquina, párpado inf. 1, párpado inf. 2)
# que requiere la fórmula estándar de Eye Aspect Ratio (Soukupová & Čech, 2016).
RIGHT_EYE_IDX = [33, 160, 158, 133, 153, 144]
LEFT_EYE_IDX = [362, 385, 387, 263, 373, 380]


def _euclidean(a: tuple[float, float], b: tuple[float, float]) -> float:
    return math.dist(a, b)


def _eye_aspect_ratio(landmarks: list[tuple[float, float]], indices: list[int]) -> float:
    p1, p2, p3, p4, p5, p6 = (landmarks[i] for i in indices)
    vertical = _euclidean(p2, p6) + _euclidean(p3, p5)
    horizontal = _euclidean(p1, p4)

    if horizontal == 0:
        return 0.0

    return vertical / (2.0 * horizontal)


class BlinkDetector:
    def __init__(self, ear_threshold: float = 0.21, consecutive_frames: int = 2):
        self._ear_threshold = ear_threshold
        self._consecutive_frames = consecutive_frames
        self._below_count = 0
        self._blinked = False
        self._face_mesh = mp.solutions.face_mesh.FaceMesh(
            max_num_faces=1,
            refine_landmarks=False,
            min_detection_confidence=0.5,
            min_tracking_confidence=0.5,
        )

    def process_frame(self, frame_bgr) -> bool:
        """Alimenta un frame de la cámara. Devuelve True si ya se detectó un
        parpadeo desde el último reset()."""
        rgb = frame_bgr[:, :, ::-1]
        results = self._face_mesh.process(rgb)

        if not results.multi_face_landmarks:
            return self._blinked

        height, width = frame_bgr.shape[:2]
        landmarks = [
            (lm.x * width, lm.y * height) for lm in results.multi_face_landmarks[0].landmark
        ]

        ear = (
            _eye_aspect_ratio(landmarks, LEFT_EYE_IDX)
            + _eye_aspect_ratio(landmarks, RIGHT_EYE_IDX)
        ) / 2.0

        if ear < self._ear_threshold:
            self._below_count += 1
        else:
            if self._below_count >= self._consecutive_frames:
                self._blinked = True
            self._below_count = 0

        return self._blinked

    def reset(self) -> None:
        self._blinked = False
        self._below_count = 0
