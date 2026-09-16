<?php

namespace App\Exceptions;

use Exception;

/**
 * El script de Python (compute_clusters.py) no pudo agrupar a los
 * estudiantes: muy pocos con datos suficientes, o el proceso falló.
 * El controlador la traduce a un mensaje claro para el admin.
 */
class StudentClusteringException extends Exception
{
}
