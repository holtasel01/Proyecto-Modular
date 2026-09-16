<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceEvent;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudentPredictionTest extends TestCase
{
    private function closedSessionAt(Student $student, string $startedAt, int $minutes): AttendanceSession
    {
        $start = Carbon::parse($startedAt);
        $end = $start->copy()->addMinutes($minutes);

        $entry = AttendanceEvent::create([
            'student_id' => $student->id, 'type' => 'entry', 'confidence' => 0.9,
            'occurred_at' => $start, 'source' => 'face_recognition',
        ]);
        $exit = AttendanceEvent::create([
            'student_id' => $student->id, 'type' => 'exit', 'confidence' => 0.9,
            'occurred_at' => $end, 'source' => 'face_recognition',
        ]);

        return AttendanceSession::create([
            'student_id' => $student->id, 'entry_event_id' => $entry->id, 'exit_event_id' => $exit->id,
            'started_at' => $start, 'ended_at' => $end, 'duration_minutes' => $minutes, 'status' => 'closed',
        ]);
    }

    public function test_student_with_no_sessions_yet_gets_a_clean_no_data_response(): void
    {
        $student = Student::factory()->create(['horas_meta' => 480]);
        $user = User::factory()->create();
        $student->update(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/me/prediction');

        $response->assertOk();
        $response->assertJsonPath('tiene_datos', false);
        $response->assertJsonPath('horas_acumuladas', 0);
        $response->assertJsonPath('horas_restantes', 480);
        $response->assertJsonPath('meta_cumplida', false);
        $response->assertJsonPath('semanas_restantes_estimadas', null);
        $response->assertJsonPath('fecha_estimada_finalizacion', null);
    }

    public function test_computes_the_full_projection_for_a_consistent_student(): void
    {
        $student = Student::factory()->create(['horas_meta' => 480]);
        $user = User::factory()->create();
        $student->update(['user_id' => $user->id]);

        // 4 sesiones de 2h, una por semana durante 4 semanas exactas —
        // mismo caso de referencia que StudentClusterTest, ya verificado a mano.
        $this->closedSessionAt($student, '2026-01-05 08:00:00', 120);
        $this->closedSessionAt($student, '2026-01-12 08:00:00', 120);
        $this->closedSessionAt($student, '2026-01-19 08:00:00', 120);
        $this->closedSessionAt($student, '2026-01-26 08:00:00', 120);

        $response = $this->actingAs($user)->getJson('/api/me/prediction');

        $response->assertOk();
        $response->assertJsonPath('tiene_datos', true);
        $response->assertJsonPath('horas_acumuladas', 8);
        $response->assertJsonPath('horas_meta', 480);
        $response->assertJsonPath('horas_restantes', 472);
        $response->assertJsonPath('progreso_porcentaje', 1.7); // 8 / 480 * 100
        $response->assertJsonPath('meta_cumplida', false);
        $response->assertJsonPath('numero_sesiones', 4);
        $response->assertJsonPath('duracion_promedio_sesion_min', 120);
        $response->assertJsonPath('horas_promedio_semana', 2);
        $response->assertJsonPath('horas_promedio_dia', 0.29); // round(2 / 7, 2)
        $response->assertJsonPath('sesiones_promedio_semana', 1);
        $response->assertJsonPath('variabilidad_semanal', 0); // exactamente constante
        $response->assertJsonPath('semanas_restantes_estimadas', 236); // 472h restantes / 2h por semana
        $response->assertJsonPath('dias_restantes_estimados', 1652); // 236 semanas * 7
        $response->assertJsonPath('sesiones_restantes_estimadas', 236); // 472h / 2h por sesión
        $this->assertNotNull($response->json('fecha_estimada_finalizacion'));
    }

    public function test_meta_already_met_reports_zero_remaining_without_a_date_estimate(): void
    {
        $student = Student::factory()->create(['horas_meta' => 5]);
        $user = User::factory()->create();
        $student->update(['user_id' => $user->id]);

        $this->closedSessionAt($student, '2026-01-05 08:00:00', 480); // 8h de una sola vez, ya supera la meta de 5h

        $response = $this->actingAs($user)->getJson('/api/me/prediction');

        $response->assertOk();
        $response->assertJsonPath('meta_cumplida', true);
        $response->assertJsonPath('horas_restantes', 0);
        $response->assertJsonPath('semanas_restantes_estimadas', null);
        $response->assertJsonPath('dias_restantes_estimados', null);
        $response->assertJsonPath('sesiones_restantes_estimadas', null);
        $response->assertJsonPath('fecha_estimada_finalizacion', null);
    }

    public function test_admin_cannot_call_the_students_own_prediction_endpoint(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/me/prediction')->assertStatus(404);
    }

    public function test_guest_cannot_access_the_prediction_endpoint(): void
    {
        $this->getJson('/api/me/prediction')->assertStatus(401);
    }
}
