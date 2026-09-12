<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use App\Models\AttendanceSession;
use App\Models\Incident;

/**
 * Crea incidencias a partir de anomalías detectadas por AttendanceService o por
 * el comando programado CloseStaleSessions. Ver docs/02-diseno.md §4 y §8.
 */
class IncidentService
{
    public function create(
        string $type,
        ?AttendanceSession $session = null,
        ?AttendanceEvent $event = null,
        ?string $description = null,
    ): Incident {
        return Incident::query()->create([
            'type' => $type,
            'attendance_session_id' => $session?->id,
            'attendance_event_id' => $event?->id,
            'description' => $description,
            'status' => 'open',
        ]);
    }
}
