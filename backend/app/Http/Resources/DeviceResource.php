<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'ubicacion' => $this->ubicacion,
            'last_seen_at' => $this->last_seen_at,
            // 'plain_text_token' solo se agrega una vez, al crear el device (ver DeviceController@store)
            'plain_text_token' => $this->when(isset($this->plain_text_token), $this->plain_text_token ?? null),
        ];
    }
}
