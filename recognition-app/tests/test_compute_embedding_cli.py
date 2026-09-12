"""Prueba scripts/compute_embedding.py como lo invoca Laravel: como proceso
aparte, verificando el contrato de stdout/exit-code (docs/02-diseno.md §1).
"""

import json
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SCRIPT = ROOT / "scripts" / "compute_embedding.py"
FIXTURES = Path(__file__).resolve().parent / "fixtures"


def run_script(*args: str) -> subprocess.CompletedProcess:
    return subprocess.run(
        [sys.executable, str(SCRIPT), *args],
        capture_output=True,
        text=True,
        timeout=60,
    )


def test_missing_argument_returns_usage_error():
    result = run_script()

    assert result.returncode == 2
    payload = json.loads(result.stdout)
    assert "error" in payload


def test_no_face_image_returns_clean_error_json():
    result = run_script(str(FIXTURES / "no_face.jpg"))

    assert result.returncode == 1
    # stdout debe ser JSON puro, sin ruido de TensorFlow (eso va a stderr) —
    # ver docs/02-diseno.md §1: Laravel hace json_decode() directo de esto.
    payload = json.loads(result.stdout)
    assert "error" in payload
    assert "rostro" in payload["error"]
