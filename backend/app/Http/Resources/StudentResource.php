<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matricula' => $this->matricula,
            'nombre' => $this->nombre,
            'carrera' => $this->carrera,
            'horas_meta' => $this->horas_meta,
            'estado' => $this->estado,
            'tiene_enrolamiento_facial' => $this->whenCounted('faceEmbedding', fn () => $this->face_embedding_count > 0),
            'created_at' => $this->created_at,
        ];
    }
}
