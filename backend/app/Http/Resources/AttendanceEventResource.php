<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'device_id' => $this->device_id,
            'type' => $this->type,
            'confidence' => $this->confidence,
            'occurred_at' => $this->occurred_at,
            'source' => $this->source,
        ];
    }
}
