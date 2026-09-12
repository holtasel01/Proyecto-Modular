"""Punto de entrada de recognition-app: reconocimiento facial en vivo para
el laboratorio (docs/02-diseno.md §6.2). Ejecutar con la cámara conectada:

    python src/main.py

Flujo por cada "ventana" de reconocimiento (cada RECOGNITION_WINDOW_SECONDS):
1. detecta rostro(s) en el frame más reciente;
2. si hay más de uno, ignora (no se adivina a quién registrar);
3. si hay exactamente uno, calcula su embedding y lo compara contra el
   catálogo sincronizado;
4. exige que durante la ventana se haya detectado un parpadeo (liveness);
5. aplica el umbral de confianza y la ventana anti-duplicado local (RF3/RF4);
6. si todo pasa, reporta el evento a Laravel — o lo encola si no hay red.
"""

from __future__ import annotations

import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from src.api_client.client import ApiClient, ApiConnectionError, ApiRejectedError
from src.capture.camera import Camera
from src.config import load_config
from src.liveness.blink import BlinkDetector
from src.queue.outbox import Outbox
from src.recognition.embedding import (
    MultipleFacesDetectedError,
    NoFaceDetectedError,
    compute_embedding,
)
from src.recognition.matcher import best_match
from src.sync.catalog import FaceCatalog

RECOGNITION_WINDOW_SECONDS = 3.0


def flush_outbox(api: ApiClient, outbox: Outbox) -> None:
    for row_id, student_id, confidence in outbox.pending():
        try:
            api.post_attendance_event(student_id, confidence)
            outbox.remove(row_id)
        except ApiConnectionError:
            break  # seguimos sin conexión; se reintentará en el próximo ciclo
        except ApiRejectedError:
            # el backend ya no acepta este evento (p. ej. quedó obsoleto) — se descarta
            outbox.remove(row_id)


def handle_recognition_attempt(
    frame,
    catalog: FaceCatalog,
    blinked: bool,
    config,
    outbox: Outbox,
    api: ApiClient,
    last_sent_at: dict[int, float],
) -> None:
    try:
        result = compute_embedding(frame)
    except NoFaceDetectedError:
        return  # nadie frente a la cámara en este momento; no es un error
    except MultipleFacesDetectedError:
        print("Varios rostros detectados a la vez; se ignora este intento.")
        return

    if not blinked:
        print("No se detectó parpadeo (liveness); se ignora este intento.")
        return

    match = best_match(result["embedding"], catalog.entries())
    if match is None or match["confidence"] < config.min_confidence_reject:
        print("No reconocido.")
        return

    student_id = match["student_id"]
    now = time.time()
    if now - last_sent_at.get(student_id, 0.0) < config.duplicate_window_seconds:
        return  # anti-duplicado local, RF4

    last_sent_at[student_id] = now

    try:
        response = api.post_attendance_event(student_id, match["confidence"])
        print(f"{match['matricula']}: {response.get('status')}")
    except ApiConnectionError:
        outbox.enqueue(student_id, match["confidence"])
        print(f"{match['matricula']}: sin conexión, evento encolado para reintento")
    except ApiRejectedError as exc:
        print(f"{match['matricula']}: el backend rechazó el evento ({exc})")


def main() -> int:
    config = load_config()

    if not config.device_token:
        print("ERROR: falta DEVICE_TOKEN en .env (ver config.example.env).")
        return 1

    api = ApiClient(config.api_base_url, config.device_token)
    catalog = FaceCatalog(api, config.catalog_cache_path, config.sync_interval_seconds)
    outbox = Outbox(config.local_queue_db_path)

    catalog.refresh_if_needed(force=True)
    flush_outbox(api, outbox)

    last_sent_at: dict[int, float] = {}
    blink_detector = BlinkDetector()

    print("Facelog recognition-app — reconocimiento en vivo. Ctrl+C para salir.")

    with Camera(config.camera_index) as camera:
        window_start = time.time()

        while True:
            frame = camera.read()
            if frame is None:
                time.sleep(0.1)
                continue

            blinked = blink_detector.process_frame(frame)

            catalog.refresh_if_needed()

            now = time.time()
            if now - window_start >= RECOGNITION_WINDOW_SECONDS:
                flush_outbox(api, outbox)
                handle_recognition_attempt(frame, catalog, blinked, config, outbox, api, last_sent_at)
                blink_detector.reset()
                window_start = now


if __name__ == "__main__":
    try:
        sys.exit(main())
    except KeyboardInterrupt:
        print("\nDetenido por el usuario.")
        sys.exit(0)
