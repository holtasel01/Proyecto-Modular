<?php

namespace App\Exceptions;

use Exception;

/**
 * El reconocimiento llegó por debajo de `min_confidence_reject` — se rechaza
 * por completo (docs/02-diseno.md §4.1). El controlador la traduce a un 422.
 */
class LowConfidenceRecognitionException extends Exception
{
    public function __construct()
    {
        parent::__construct('La confianza del reconocimiento es demasiado baja para aceptarlo.');
    }
}
