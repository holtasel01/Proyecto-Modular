"""Detección + extracción de embeddings faciales con DeepFace.

Único lugar del proyecto que sabe "cómo convertir una imagen en un vector
facial" — lo usan tanto scripts/compute_embedding.py (enrolamiento, una
imagen) como src/main.py (reconocimiento en vivo, un frame de cámara), para
garantizar que ambos usan exactamente el mismo modelo y preprocesamiento
(docs/02-diseno.md §1 y §11).
"""

from __future__ import annotations

from deepface import DeepFace

from .similarity import cosine_similarity  # re-exportada por compatibilidad

# Facenet512 sobre dlib/VGG-Face: mejor balance precisión/velocidad en CPU
# para un solo laboratorio (docs/02-diseno.md §11). "opencv" como backend de
# detección por ser el más liviano — suficiente para una cámara fija de cerca.
MODEL_NAME = "Facenet512"
DETECTOR_BACKEND = "opencv"


class NoFaceDetectedError(Exception):
    """No se detectó ningún rostro en la imagen/frame."""


class MultipleFacesDetectedError(Exception):
    """Se detectó más de un rostro; la operación requiere exactamente uno."""

    def __init__(self, count: int):
        super().__init__(f"Se detectaron {count} rostros; se esperaba exactamente uno.")
        self.count = count


def compute_embedding(image) -> dict:
    """`image`: ruta de archivo (str) o un frame BGR (numpy array, formato OpenCV).

    Devuelve {"embedding": list[float], "modelo": str}.
    Lanza NoFaceDetectedError / MultipleFacesDetectedError según corresponda.
    """
    try:
        results = DeepFace.represent(
            img_path=image,
            model_name=MODEL_NAME,
            detector_backend=DETECTOR_BACKEND,
            enforce_detection=True,
        )
    except ValueError as exc:
        # DeepFace lanza ValueError (mensaje "Face could not be detected...")
        # cuando enforce_detection=True y no encuentra ningún rostro.
        raise NoFaceDetectedError(str(exc)) from exc

    if len(results) > 1:
        raise MultipleFacesDetectedError(len(results))

    embedding = results[0]["embedding"]

    return {
        "embedding": [float(v) for v in embedding],
        "modelo": MODEL_NAME,
    }
