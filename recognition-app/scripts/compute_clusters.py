"""Agrupa estudiantes por patrón de asistencia con K-Means (docs/08-mineria-datos.md).

Invocado por Laravel (StudentClusteringService) como subproceso: recibe por
stdin un JSON con los vectores de características ya calculados en PHP desde
`attendance_sessions`, y devuelve por stdout a qué grupo pertenece cada
estudiante. Este script no toca la base de datos ni sabe nada de Laravel —
solo recibe números y hace el trabajo de minería de datos.

Entrada (stdin):
    {"students": [
        {"student_id": 1, "matricula": "218900001", "nombre": "...",
         "features": {"horas_acumuladas": 42.5, "numero_sesiones": 12,
                      "duracion_promedio_sesion_min": 180.0,
                      "horas_promedio_semana": 5.2,
                      "sesiones_promedio_semana": 1.5,
                      "variabilidad_semanal": 1.1}},
        ...
    ]}

Salida (stdout), éxito:
    {"clusters": [{"student_id": 1, "matricula": "...", "nombre": "...",
                    "cluster": 2, "cluster_label": "alta actividad",
                    "features": {...}}, ...],
     "centroids": [{"cluster": 0, "cluster_label": "baja actividad",
                     "horas_acumuladas": ..., ...}, ...]}

Salida (stdout), error: {"error": "mensaje"}
"""

from __future__ import annotations

import json
import sys

import numpy as np
from sklearn.cluster import KMeans
from sklearn.preprocessing import StandardScaler

FEATURE_NAMES = [
    "horas_acumuladas",
    "numero_sesiones",
    "duracion_promedio_sesion_min",
    "horas_promedio_semana",
    "sesiones_promedio_semana",
    "variabilidad_semanal",
]

N_CLUSTERS = 3
CLUSTER_LABELS = ["baja actividad", "actividad moderada", "alta actividad"]


def main() -> int:
    try:
        payload = json.load(sys.stdin)
        students = payload["students"]
    except (json.JSONDecodeError, KeyError, TypeError):
        print(json.dumps({"error": "Entrada inválida: se esperaba JSON con la clave 'students'."}))
        return 1

    if len(students) < N_CLUSTERS:
        print(json.dumps({
            "error": f"Hacen falta al menos {N_CLUSTERS} estudiantes con datos para agrupar; llegaron {len(students)}.",
        }))
        return 1

    try:
        # Orden fijo de columnas (FEATURE_NAMES) — así el índice de cada
        # valor en el vector siempre corresponde a la misma característica,
        # sin depender del orden en que Laravel serializó el diccionario.
        matrix = np.array(
            [[s["features"][name] for name in FEATURE_NAMES] for s in students],
            dtype=float,
        )
    except (KeyError, TypeError):
        print(json.dumps({"error": "Cada estudiante debe traer las 6 características esperadas."}))
        return 1

    # K-Means mide distancia euclidiana entre puntos: sin estandarizar,
    # "horas_acumuladas" (decenas/cientos) dominaría por completo sobre
    # "sesiones_promedio_semana" (unidades) solo por la escala, no porque
    # importe más. StandardScaler pone las 6 variables en la misma escala
    # (media 0, desviación estándar 1) antes de calcular distancias.
    scaler = StandardScaler()
    scaled = scaler.fit_transform(matrix)

    kmeans = KMeans(n_clusters=N_CLUSTERS, random_state=42, n_init=10)
    raw_labels = kmeans.fit_predict(scaled)

    # KMeans numera los clusters de forma arbitraria (0/1/2 no tienen orden
    # inherente) — se reordenan por "nivel de actividad" del centroide
    # (horas + sesiones promedio por semana, ya estandarizadas) para que la
    # etiqueta "alta actividad" siempre sea, de verdad, la más activa.
    activity_index = FEATURE_NAMES.index("horas_promedio_semana")
    sessions_index = FEATURE_NAMES.index("sesiones_promedio_semana")
    activity_score = kmeans.cluster_centers_[:, activity_index] + kmeans.cluster_centers_[:, sessions_index]
    label_order = np.argsort(activity_score)  # de menor a mayor actividad
    raw_to_label = {int(raw_cluster): CLUSTER_LABELS[rank] for rank, raw_cluster in enumerate(label_order)}
    raw_to_rank = {int(raw_cluster): rank for rank, raw_cluster in enumerate(label_order)}

    centroids_original_scale = scaler.inverse_transform(kmeans.cluster_centers_)

    clusters_out = []
    for student, raw_cluster in zip(students, raw_labels):
        clusters_out.append({
            "student_id": student["student_id"],
            "matricula": student["matricula"],
            "nombre": student["nombre"],
            "cluster": raw_to_rank[int(raw_cluster)],
            "cluster_label": raw_to_label[int(raw_cluster)],
            "features": student["features"],
        })

    centroids_out = []
    for raw_cluster in range(N_CLUSTERS):
        centroid_values = dict(zip(FEATURE_NAMES, centroids_original_scale[raw_cluster].tolist()))
        centroids_out.append({
            "cluster": raw_to_rank[raw_cluster],
            "cluster_label": raw_to_label[raw_cluster],
            **{name: round(value, 2) for name, value in centroid_values.items()},
        })

    centroids_out.sort(key=lambda c: c["cluster"])

    print(json.dumps({"clusters": clusters_out, "centroids": centroids_out}))
    return 0


if __name__ == "__main__":
    sys.exit(main())
