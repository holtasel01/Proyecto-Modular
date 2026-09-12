"""Pruebas del módulo de embeddings (DeepFace). Etapa 5, docs/02-diseno.md §11.

NOTA: no hay todavía un dataset de fotos de estudiantes (Etapa 1, decisión 3),
así que solo se prueba automáticamente la ruta de error "sin rostro". Falta
una prueba con una foto real de una persona para validar el caso feliz
(detección + embedding correctos) y el caso de "varios rostros" — agregarlas
en tests/fixtures/ en cuanto se consiga una foto de prueba real.
"""

import sys
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from src.recognition.embedding import NoFaceDetectedError, compute_embedding

FIXTURES = Path(__file__).resolve().parent / "fixtures"


def test_no_face_detected_raises():
    with pytest.raises(NoFaceDetectedError):
        compute_embedding(str(FIXTURES / "no_face.jpg"))
