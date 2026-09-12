<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Metadatos del embedding activo de un estudiante. Nunca expone el vector
 * (docs/02-diseno.md §5) — ni siquiera al propio estudiante ni al admin.
 */
class FaceProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'existe' => true,
            'modelo' => $this->modelo,
            'created_at' => $this->created_at,
        ];
    }
}
