<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'attendance_session_id' => $this->attendance_session_id,
            'attendance_event_id' => $this->attendance_event_id,
            'description' => $this->description,
            'status' => $this->status,
            'resolved_by' => $this->resolved_by,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
        ];
    }
}
