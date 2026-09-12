"""Compara un embedding recién calculado contra el catálogo sincronizado
de estudiantes activos y devuelve la mejor coincidencia (docs/02-diseno.md §11).
"""

from __future__ import annotations

from .similarity import cosine_similarity


def best_match(probe_embedding: list[float], catalog_entries: list[dict]) -> dict | None:
    """`catalog_entries`: [{"student_id", "matricula", "embedding"}, ...]
    (formato de GET /api/sync/face-catalog, ver src/sync/catalog.py).

    Devuelve {"student_id", "matricula", "confidence"} del más parecido,
    o None si el catálogo está vacío.
    """
    if not catalog_entries:
        return None

    best: dict | None = None

    for entry in catalog_entries:
        confidence = cosine_similarity(probe_embedding, entry["embedding"])

        if best is None or confidence > best["confidence"]:
            best = {
                "student_id": entry["student_id"],
                "matricula": entry["matricula"],
                "confidence": confidence,
            }

    return best
