<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enrolamiento facial (docs/02-diseno.md §1)
    |--------------------------------------------------------------------------
    |
    | Laravel invoca recognition-app/scripts/compute_embedding.py como
    | subproceso, así que asume que corre en la misma máquina (o al menos
    | que el intérprete de Python y ese script son accesibles desde aquí).
    |
    */

    'python_bin' => env('FACELOG_PYTHON_BIN', base_path('../recognition-app/venv/Scripts/python.exe')),

    'compute_embedding_script' => env(
        'FACELOG_COMPUTE_EMBEDDING_SCRIPT',
        base_path('../recognition-app/scripts/compute_embedding.py'),
    ),

    'embedding_timeout_seconds' => env('FACELOG_EMBEDDING_TIMEOUT_SECONDS', 30),

    /*
    |--------------------------------------------------------------------------
    | Minería de datos: agrupamiento de estudiantes por patrón de asistencia
    |--------------------------------------------------------------------------
    |
    | Igual que el enrolamiento facial, Laravel arma los datos (aquí, vectores
    | de características por estudiante) y se los pasa por stdin a un script
    | de Python que hace el trabajo real (K-Means con scikit-learn).
    |
    */

    'compute_clusters_script' => env(
        'FACELOG_COMPUTE_CLUSTERS_SCRIPT',
        base_path('../recognition-app/scripts/compute_clusters.py'),
    ),

    'clustering_timeout_seconds' => env('FACELOG_CLUSTERING_TIMEOUT_SECONDS', 30),

];
