"""CLI invocada por Laravel (Process) para el enrolamiento vía web.

Contrato (ver docs/02-diseno.md, sección 1):
    python compute_embedding.py <ruta_imagen>

Salida por stdout (JSON), exit code 0 si tuvo éxito:
    {"embedding": [...], "modelo": "Facenet512"}

En caso de error (sin rostro, varios rostros, imagen inválida), exit code
distinto de 0 y un JSON de error por stdout: {"error": "..."}
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from src.recognition.embedding import (
    MultipleFacesDetectedError,
    NoFaceDetectedError,
    compute_embedding,
)


def main() -> int:
    if len(sys.argv) != 2:
        print(json.dumps({"error": "uso: compute_embedding.py <ruta_imagen>"}))
        return 2

    image_path = sys.argv[1]

    try:
        result = compute_embedding(image_path)
    except NoFaceDetectedError:
        print(json.dumps({"error": "No se detectó ningún rostro en la imagen."}))
        return 1
    except MultipleFacesDetectedError as exc:
        print(json.dumps({"error": str(exc)}))
        return 1
    except Exception as exc:  # noqa: BLE001 — cualquier otro fallo se reporta igual, no se oculta
        print(json.dumps({"error": f"No se pudo procesar la imagen: {exc}"}))
        return 1

    print(json.dumps(result))
    return 0


if __name__ == "__main__":
    sys.exit(main())
