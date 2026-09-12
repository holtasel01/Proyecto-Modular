<?php

namespace App\Console\Commands;

use App\Models\AttendanceSession;
use App\Models\Setting;
use App\Services\IncidentService;
use Illuminate\Console\Command;

/**
 * docs/02-diseno.md §4.4: sesiones que quedaron "open" demasiado tiempo
 * (el estudiante nunca registró salida) se marcan "inconsistent" y generan
 * una incidencia para que el admin las cierre manualmente.
 */
class CloseStaleSessions extends Command
{
    protected $signature = 'attendance:close-stale-sessions';

    protected $description = 'Marca como inconsistentes las sesiones de asistencia abiertas por demasiado tiempo';

    public function handle(IncidentService $incidents): int
    {
        $maxHours = (int) Setting::get('max_session_hours', 12);

        $staleSessions = AttendanceSession::query()
            ->where('status', 'open')
            ->where('started_at', '<=', now()->subHours($maxHours))
            ->get();

        foreach ($staleSessions as $session) {
            $session->update(['status' => 'inconsistent']);

            $incidents->create(
                type: 'entrada_sin_salida',
                session: $session,
                description: "La sesión sigue abierta después de {$maxHours} horas sin registrar salida.",
            );
        }

        $this->info("Sesiones marcadas como inconsistentes: {$staleSessions->count()}");

        return self::SUCCESS;
    }
}
