<?php

namespace App\Exceptions;

use Exception;

/**
 * El script de Python (compute_embedding.py) no pudo generar un embedding:
 * sin rostro detectado, varios rostros, imagen inválida, o el proceso falló.
 * El controlador la traduce a un 422 con el mensaje tal cual, para que el
 * estudiante/admin sepa qué corregir en la foto.
 */
class FaceEmbeddingComputationException extends Exception
{
}
