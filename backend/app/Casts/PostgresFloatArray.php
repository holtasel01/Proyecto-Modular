<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use InvalidArgumentException;

/**
 * Convierte entre un array de PHP (float[]) y el literal de array nativo de
 * PostgreSQL ("{1.0,2.0,...}") usado por la columna `face_embeddings.vector`
 * (float4[] / real[]). Ver docs/02-diseno.md §3.
 *
 * @implements CastsAttributes<array<int, float>, array<int, float>>
 */
class PostgresFloatArray implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): array
    {
        if ($value === null) {
            return [];
        }

        $trimmed = trim($value, '{}');

        if ($trimmed === '') {
            return [];
        }

        return array_map('floatval', explode(',', $trimmed));
    }

    public function set($model, string $key, $value, array $attributes): string
    {
        if (! is_array($value) || $value === []) {
            throw new InvalidArgumentException("El embedding facial ('$key') no puede estar vacío.");
        }

        return '{'.implode(',', array_map(static fn (float $v) => (string) $v, $value)).'}';
    }
}
