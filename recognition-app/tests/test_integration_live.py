"""Prueba de integración real Python <-> Laravel (Etapa 7, docs/02-diseno.md §6.2).

A diferencia del resto de tests/ (que usan fakes/imágenes sintéticas), esta
prueba habla por HTTP con un backend Laravel REAL ya corriendo, usando el
`ApiClient` y el `matcher` reales de recognition-app. Se salta automáticamente
si no hay un backend disponible o si no se le pasan las variables de entorno
necesarias — así no rompe `pytest tests/` en un entorno sin backend levantado.

Precondición (hacerlo una vez, vía `php artisan tinker` en backend/):
    - un Student activo con un FaceEmbedding asociado (para una prueba real de
      reconocimiento haría falta un embedding calculado de una foto real; para
      probar solo el flujo de datos, un vector sintético del mismo tamaño que
      produce el modelo real (512 para Facenet512) es suficiente y así se hizo
      para verificar esta Etapa 7 — no valida precisión de reconocimiento,
      solo que Python, Laravel y PostgreSQL se comunican correctamente).
    - un Device con un token con abilities ["sync", "attendance:write"].

Cómo correrla:
    FACELOG_INTEGRATION_DEVICE_TOKEN=<token> \
    FACELOG_INTEGRATION_STUDENT_ID=<id> \
    venv/Scripts/python.exe -m pytest tests/test_integration_live.py -q
"""

import os
import sys
import time
from pathlib import Path

import pytest
import requests

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from src.api_client.client import ApiClient, ApiConnectionError
from src.recognition.matcher import best_match

API_BASE = os.environ.get("FACELOG_INTEGRATION_API_BASE", "http://localhost:8000/api")
DEVICE_TOKEN = os.environ.get("FACELOG_INTEGRATION_DEVICE_TOKEN")
STUDENT_ID = os.environ.get("FACELOG_INTEGRATION_STUDENT_ID")


def _backend_reachable() -> bool:
    try:
        root = API_BASE.rsplit("/api", 1)[0]
        return requests.get(f"{root}/up", timeout=2).status_code == 200
    except requests.RequestException:
        return False


pytestmark = pytest.mark.skipif(
    not (DEVICE_TOKEN and STUDENT_ID and _backend_reachable()),
    reason=(
        "requiere un backend Laravel real corriendo y "
        "FACELOG_INTEGRATION_DEVICE_TOKEN / FACELOG_INTEGRATION_STUDENT_ID"
    ),
)


@pytest.fixture
def api() -> ApiClient:
    return ApiClient(API_BASE, DEVICE_TOKEN)


def test_sync_and_match_against_live_catalog(api: ApiClient):
    catalog = api.fetch_face_catalog()
    assert len(catalog) > 0, "el catálogo vino vacío — ¿el estudiante de prueba está activo?"

    entry = next((e for e in catalog if str(e["student_id"]) == STUDENT_ID), None)
    assert entry is not None, f"student_id {STUDENT_ID} no aparece en el catálogo sincronizado"

    # Usamos el propio embedding del catálogo como "probe" — simula un
    # reconocimiento perfecto sin necesitar una foto real todavía.
    match = best_match(entry["embedding"], catalog)
    assert match["student_id"] == entry["student_id"]
    assert match["confidence"] > 0.99


def test_attendance_event_round_trip(api: ApiClient):
    student_id = int(STUDENT_ID)

    first = api.post_attendance_event(student_id, confidence=0.95)
    assert first["status"] in ("created", "duplicate_ignored")

    # Inmediatamente después, la ventana anti-duplicado del backend debe
    # ignorarlo (RF4 aplicado del lado de Laravel, docs/02-diseno.md §4.2).
    second = api.post_attendance_event(student_id, confidence=0.95)
    assert second["status"] == "duplicate_ignored"


def test_connection_error_is_distinguishable_from_rejection():
    unreachable = ApiClient("http://127.0.0.1:1/api", DEVICE_TOKEN, timeout=1)
    with pytest.raises(ApiConnectionError):
        unreachable.fetch_face_catalog()
