import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from src.api_client.client import ApiConnectionError
from src.sync.catalog import FaceCatalog


class FakeApiClient:
    def __init__(self, catalog=None, fail=False):
        self._catalog = catalog or []
        self._fail = fail

    def fetch_face_catalog(self):
        if self._fail:
            raise ApiConnectionError("sin conexión (prueba)")
        return self._catalog


def test_refresh_populates_entries_and_writes_cache(tmp_path):
    cache_path = tmp_path / "catalog.json"
    api = FakeApiClient(catalog=[{"student_id": 1, "matricula": "A001", "embedding": [1.0]}])

    catalog = FaceCatalog(api, str(cache_path))
    catalog.refresh_if_needed(force=True)

    assert catalog.entries() == [{"student_id": 1, "matricula": "A001", "embedding": [1.0]}]
    assert cache_path.exists()


def test_falls_back_to_cached_copy_when_offline(tmp_path):
    cache_path = tmp_path / "catalog.json"

    online_api = FakeApiClient(catalog=[{"student_id": 1, "matricula": "A001", "embedding": [1.0]}])
    FaceCatalog(online_api, str(cache_path)).refresh_if_needed(force=True)

    offline_api = FakeApiClient(fail=True)
    catalog = FaceCatalog(offline_api, str(cache_path))
    catalog.refresh_if_needed(force=True)

    # sigue disponible la última copia cacheada, no se vació por el fallo de red
    assert catalog.entries() == [{"student_id": 1, "matricula": "A001", "embedding": [1.0]}]


def test_does_not_refresh_before_interval_elapses(tmp_path):
    cache_path = tmp_path / "catalog.json"
    api = FakeApiClient(catalog=[{"student_id": 1, "matricula": "A001", "embedding": [1.0]}])
    catalog = FaceCatalog(api, str(cache_path), refresh_interval_seconds=9999)
    catalog.refresh_if_needed(force=True)

    api._catalog = [{"student_id": 2, "matricula": "A002", "embedding": [2.0]}]
    catalog.refresh_if_needed()  # sin force=True, y el intervalo no ha pasado

    assert catalog.entries()[0]["student_id"] == 1
