"""Cliente HTTP hacia la API de Laravel, autenticado con el token del
device (Sanctum, abilities `sync` y `attendance:write`). docs/02-diseno.md §10.
"""

from __future__ import annotations

import requests


class ApiConnectionError(Exception):
    """No se pudo contactar al backend (red caída, timeout, servidor abajo).
    Quien la reciba debe encolar el evento y reintentar después, no descartarlo."""


class ApiRejectedError(Exception):
    """El backend respondió pero rechazó la solicitud (4xx) — no tiene sentido
    reintentarla tal cual; se descarta o se reporta, según el contexto."""

    def __init__(self, status_code: int, message: str):
        super().__init__(f"HTTP {status_code}: {message}")
        self.status_code = status_code


class ApiClient:
    def __init__(self, base_url: str, token: str, timeout: float = 5.0):
        self._base_url = base_url.rstrip("/")
        self._timeout = timeout
        self._session = requests.Session()
        self._session.headers.update({
            "Authorization": f"Bearer {token}",
            "Accept": "application/json",
        })

    def fetch_face_catalog(self) -> list[dict]:
        response = self._request("GET", "/sync/face-catalog")
        return response.json()["catalog"]

    def post_attendance_event(self, student_id: int, confidence: float) -> dict:
        response = self._request(
            "POST",
            "/attendance/events",
            json={"student_id": student_id, "confidence": confidence},
        )
        return response.json()

    def _request(self, method: str, path: str, **kwargs) -> requests.Response:
        try:
            response = self._session.request(
                method, f"{self._base_url}{path}", timeout=self._timeout, **kwargs
            )
        except requests.exceptions.RequestException as exc:
            raise ApiConnectionError(str(exc)) from exc

        if response.status_code >= 500:
            raise ApiConnectionError(f"El backend respondió {response.status_code}")

        if response.status_code >= 400:
            raise ApiRejectedError(response.status_code, response.text)

        return response
