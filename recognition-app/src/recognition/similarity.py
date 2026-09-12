"""Cálculo de similitud coseno entre embeddings, sin dependencias pesadas
(no importa DeepFace) — así matcher.py se puede probar de forma aislada.
"""

from __future__ import annotations

import numpy as np


def cosine_similarity(a: list[float], b: list[float]) -> float:
    """1.0 = idénticos, 0.0 = ortogonales, negativo = opuestos."""
    vec_a = np.asarray(a, dtype=float)
    vec_b = np.asarray(b, dtype=float)
    norm_a = np.linalg.norm(vec_a)
    norm_b = np.linalg.norm(vec_b)
    if norm_a == 0 or norm_b == 0:
        return 0.0
    return float(np.dot(vec_a, vec_b) / (norm_a * norm_b))
