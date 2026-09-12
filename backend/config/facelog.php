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

];
