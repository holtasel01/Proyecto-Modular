"""Prueba scripts/compute_clusters.py como lo invoca Laravel: como proceso
aparte con el JSON de entrada por stdin, verificando el contrato de
stdout/exit-code (docs/08-mineria-datos.md).
"""

import json
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SCRIPT = ROOT / "scripts" / "compute_clusters.py"

FEATURE_NAMES = [
    "horas_acumuladas",
    "numero_sesiones",
    "duracion_promedio_sesion_min",
    "horas_promedio_semana",
    "sesiones_promedio_semana",
    "variabilidad_semanal",
]


def make_student(student_id: int, **overrides) -> dict:
    features = {
        "horas_acumuladas": 10.0,
        "numero_sesiones": 5,
        "duracion_promedio_sesion_min": 120.0,
        "horas_promedio_semana": 2.0,
        "sesiones_promedio_semana": 1.0,
        "variabilidad_semanal": 0.5,
    }
    features.update(overrides)
    return {
        "student_id": student_id,
        "matricula": f"10000000{student_id}",
        "nombre": f"Estudiante {student_id}",
        "features": features,
    }


def run_script(payload: dict) -> subprocess.CompletedProcess:
    return subprocess.run(
        [sys.executable, str(SCRIPT)],
        input=json.dumps(payload),
        capture_output=True,
        text=True,
        timeout=60,
    )


def test_groups_low_medium_high_activity_students_correctly():
    students = [
        make_student(1, horas_acumuladas=5, horas_promedio_semana=0.8, sesiones_promedio_semana=0.5),
        make_student(2, horas_acumuladas=6, horas_promedio_semana=1.0, sesiones_promedio_semana=0.6),
        make_student(3, horas_acumuladas=40, horas_promedio_semana=5.0, sesiones_promedio_semana=1.5),
        make_student(4, horas_acumuladas=42, horas_promedio_semana=5.2, sesiones_promedio_semana=1.6),
        make_student(5, horas_acumuladas=120, horas_promedio_semana=15.0, sesiones_promedio_semana=3.8),
        make_student(6, horas_acumuladas=115, horas_promedio_semana=14.2, sesiones_promedio_semana=3.5),
    ]

    result = run_script({"students": students})

    assert result.returncode == 0
    payload = json.loads(result.stdout)

    assert len(payload["clusters"]) == 6
    assert len(payload["centroids"]) == 3

    by_id = {c["student_id"]: c for c in payload["clusters"]}
    assert by_id[1]["cluster_label"] == "baja actividad"
    assert by_id[2]["cluster_label"] == "baja actividad"
    assert by_id[3]["cluster_label"] == "actividad moderada"
    assert by_id[4]["cluster_label"] == "actividad moderada"
    assert by_id[5]["cluster_label"] == "alta actividad"
    assert by_id[6]["cluster_label"] == "alta actividad"

    # las etiquetas están ordenadas de menor a mayor actividad por índice de cluster
    labels_by_cluster = {c["cluster"]: c["cluster_label"] for c in payload["centroids"]}
    assert labels_by_cluster[0] == "baja actividad"
    assert labels_by_cluster[1] == "actividad moderada"
    assert labels_by_cluster[2] == "alta actividad"


def test_rejects_fewer_than_three_students():
    students = [make_student(1), make_student(2)]

    result = run_script({"students": students})

    assert result.returncode == 1
    payload = json.loads(result.stdout)
    assert "error" in payload


def test_rejects_invalid_json():
    result = subprocess.run(
        [sys.executable, str(SCRIPT)],
        input="esto no es json",
        capture_output=True,
        text=True,
        timeout=60,
    )

    assert result.returncode == 1
    payload = json.loads(result.stdout)
    assert "error" in payload


def test_rejects_a_student_missing_features():
    students = [
        make_student(1),
        make_student(2),
        {"student_id": 3, "matricula": "100000003", "nombre": "Sin datos"},
    ]

    result = run_script({"students": students})

    assert result.returncode == 1
    payload = json.loads(result.stdout)
    assert "error" in payload
