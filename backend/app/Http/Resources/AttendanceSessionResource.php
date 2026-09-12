<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'duration_minutes' => $this->duration_minutes,
            'status' => $this->status,
        ];
    }
}
