<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\UpdateSessionRequest;
use App\Http\Resources\AttendanceSessionResource;
use App\Models\AttendanceSession;
use App\Services\AuditLogger;
use App\Services\IncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceSessionController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly IncidentService $incidents,
    ) {
    }

    /**
     * GET /attendance/sessions — admin, con filtros (docs/02-diseno.md §5).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', AttendanceSession::class);

        $sessions = AttendanceSession::query()
            ->when($request->filled('student_id'), fn ($q) => $q->where('student_id', $request->integer('student_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('from'), fn ($q) => $q->where('started_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('started_at', '<=', $request->date('to')))
            ->orderByDesc('started_at')
            ->paginate(20);

        return AttendanceSessionResource::collection($sessions);
    }

    /**
     * PATCH /attendance/sessions/{attendance_session} — corrección manual del admin,
     * siempre auditada y siempre genera una incidencia visible (docs/02-diseno.md §4.5).
     */
    public function update(UpdateSessionRequest $request, AttendanceSession $attendanceSession): AttendanceSessionResource
    {
        $old = $attendanceSession->only(['started_at', 'ended_at', 'status']);

        $attendanceSession->update($request->safe()->except('reason'));

        if ($attendanceSession->ended_at && $attendanceSession->started_at) {
            // Carbon 3 (Laravel 11) devuelve diffInMinutes() como float; la columna es integer.
            $attendanceSession->duration_minutes = (int) $attendanceSession->started_at->diffInMinutes($attendanceSession->ended_at);
            $attendanceSession->save();
        }

        $this->auditLogger->log(
            actor: $request->user(),
            auditable: $attendanceSession,
            action: 'manual_correction',
            oldValues: $old,
            newValues: $attendanceSession->only(['started_at', 'ended_at', 'status']),
        );

        $this->incidents->create(
            type: 'corregido_manualmente',
            session: $attendanceSession,
            description: $request->string('reason')->toString(),
        );

        return new AttendanceSessionResource($attendanceSession->fresh());
    }

    /**
     * GET /me/attendance — el propio estudiante.
     */
    public function myAttendance(Request $request)
    {
        $student = $request->user()->student()->firstOrFail();

        $sessions = AttendanceSession::query()
            ->where('student_id', $student->id)
            ->orderByDesc('started_at')
            ->paginate(20);

        return AttendanceSessionResource::collection($sessions);
    }

    /**
     * GET /me/summary — horas acumuladas, meta y progreso del propio estudiante.
     */
    public function mySummary(Request $request): JsonResponse
    {
        $student = $request->user()->student()->firstOrFail();

        $totalMinutes = (int) AttendanceSession::query()
            ->where('student_id', $student->id)
            ->where('status', 'closed')
            ->sum('duration_minutes');

        $horasAcumuladas = round($totalMinutes / 60, 2);
        $progreso = $student->horas_meta > 0
            ? round(min(100, ($horasAcumuladas / $student->horas_meta) * 100), 1)
            : 0.0;

        return response()->json([
            'horas_acumuladas' => $horasAcumuladas,
            'horas_meta' => $student->horas_meta,
            'progreso_porcentaje' => $progreso,
        ]);
    }

    /**
     * GET /lab/status — admin: quién está dentro ahora mismo.
     */
    public function labStatus(): JsonResponse
    {
        $this->authorize('viewAny', AttendanceSession::class);

        $open = AttendanceSession::query()
            ->with('student:id,matricula,nombre')
            ->where('status', 'open')
            ->orderBy('started_at')
            ->get();

        return response()->json([
            'estudiantes_dentro' => $open->map(fn ($session) => [
                'session_id' => $session->id,
                'student_id' => $session->student_id,
                'matricula' => $session->student->matricula,
                'nombre' => $session->student->nombre,
                'started_at' => $session->started_at,
            ]),
        ]);
    }
}
