<?php

namespace App\Services;

use App\Exceptions\FaceEmbeddingComputationException;
use Illuminate\Support\Facades\Process;

/**
 * Invoca recognition-app/scripts/compute_embedding.py como subproceso para
 * convertir una foto de enrolamiento en un embedding facial (docs/02-diseno.md §1).
 *
 * El script en sí (detección + DeepFace) se implementa en la Etapa 5; hasta
 * entonces esta clase funciona de punta a punta pero el script responde
 * "no implementado todavía", por lo que create()/replace() de face-photo
 * fallarán con un 422 hasta esa etapa — es el comportamiento esperado, no un bug.
 */
class FaceEmbeddingComputer
{
    /**
     * @return array{embedding: array<int, float>, modelo: string}
     */
    public function compute(string $imagePath): array
    {
        $result = Process::timeout((int) config('facelog.embedding_timeout_seconds'))
            ->run([config('facelog.python_bin'), config('facelog.compute_embedding_script'), $imagePath]);

        $output = json_decode($result->output() ?: $result->errorOutput(), associative: true);

        if (! $result->successful() || ! is_array($output) || isset($output['error'])) {
            $message = is_array($output) && isset($output['error'])
                ? $output['error']
                : 'No se pudo procesar la imagen para calcular el embedding facial.';

            throw new FaceEmbeddingComputationException($message);
        }

        if (! isset($output['embedding'], $output['modelo']) || ! is_array($output['embedding'])) {
            throw new FaceEmbeddingComputationException('Respuesta inesperada del script de reconocimiento facial.');
        }

        return [
            'embedding' => array_map('floatval', $output['embedding']),
            'modelo' => (string) $output['modelo'],
        ];
    }
}
