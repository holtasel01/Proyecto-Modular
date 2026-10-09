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

# Laravel hace json_decode() directo del stdout de este proceso, así que debe
# quedar reservado exclusivamente para la respuesta final. Lo redirigimos a
# stderr desde ya: la primera vez que corre en una máquina nueva, DeepFace
# descarga los pesos del modelo (~95MB) y esa descarga imprime una barra de
# progreso en stdout — sin esto, esa sola vez rompería el contrato con un
# "Extra data" al parsear. _real_stdout es el único canal que usamos para
# la respuesta JSON real.
_real_stdout = sys.stdout
sys.stdout = sys.stderr

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from src.recognition.embedding import (
    MultipleFacesDetectedError,
    NoFaceDetectedError,
    compute_embedding,
)


def _respond(payload: dict) -> None:
    print(json.dumps(payload), file=_real_stdout, flush=True)


def main() -> int:
    if len(sys.argv) != 2:
        _respond({"error": "uso: compute_embedding.py <ruta_imagen>"})
        return 2

    image_path = sys.argv[1]

    try:
        result = compute_embedding(image_path)
    except NoFaceDetectedError:
        _respond({"error": "No se detectó ningún rostro en la imagen."})
        return 1
    except MultipleFacesDetectedError as exc:
        _respond({"error": str(exc)})
        return 1
    except Exception as exc:  # noqa: BLE001 — cualquier otro fallo se reporta igual, no se oculta
        _respond({"error": f"No se pudo procesar la imagen: {exc}"})
        return 1

    _respond(result)
    return 0


if __name__ == "__main__":
    sys.exit(main())
