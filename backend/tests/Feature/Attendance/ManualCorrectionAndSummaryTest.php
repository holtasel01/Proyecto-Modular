<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceEvent;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ManualCorrectionAndSummaryTest extends TestCase
{
    private function closedSessionFor(Student $student, int $minutes): AttendanceSession
    {
        $start = Carbon::parse('2026-01-01 08:00:00');
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

    public function test_admin_can_correct_a_session_and_it_gets_audited_and_flagged(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();
        $session = $this->closedSessionFor($student, 60);

        $response = $this->actingAs($admin)->patchJson("/api/attendance/sessions/{$session->id}", [
            'started_at' => '2026-01-01T08:00:00Z',
            'ended_at' => '2026-01-01T09:30:00Z',
            'reason' => 'El estudiante salió más tarde de lo registrado.',
        ]);

        $response->assertOk()->assertJsonPath('data.duration_minutes', 90);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'AttendanceSession',
            'auditable_id' => $session->id,
            'action' => 'manual_correction',
        ]);
        $this->assertDatabaseHas('incidents', [
            'type' => 'corregido_manualmente',
            'attendance_session_id' => $session->id,
        ]);
    }

    public function test_correction_requires_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();
        $session = $this->closedSessionFor($student, 60);

        $this->actingAs($admin)->patchJson("/api/attendance/sessions/{$session->id}", [
            'status' => 'inconsistent',
        ])->assertStatus(422);
    }

    public function test_student_cannot_correct_a_session(): void
    {
        $user = User::factory()->create();
        $student = Student::factory()->create(['user_id' => $user->id]);
        $session = $this->closedSessionFor($student, 60);

        $this->actingAs($user)->patchJson("/api/attendance/sessions/{$session->id}", [
            'reason' => 'intento no autorizado',
        ])->assertStatus(403);
    }

    public function test_summary_calculates_accumulated_hours_and_progress(): void
    {
        $user = User::factory()->create();
        $student = Student::factory()->create(['user_id' => $user->id, 'horas_meta' => 10]);
        $this->closedSessionFor($student, 5 * 60); // 5 horas

        $response = $this->actingAs($user)->getJson('/api/me/summary');

        // json_encode no conserva ".0" en floats sin parte fraccionaria
        // (PHP los serializa como enteros), así que se compara contra el
        // valor tal como realmente viaja por la API, no contra el float de PHP.
        $response->assertOk()
            ->assertJsonPath('horas_acumuladas', 5)
            ->assertJsonPath('horas_meta', 10)
            ->assertJsonPath('progreso_porcentaje', 50);
    }

    public function test_summary_progress_never_exceeds_100_percent(): void
    {
        $user = User::factory()->create();
        $student = Student::factory()->create(['user_id' => $user->id, 'horas_meta' => 10]);
        $this->closedSessionFor($student, 20 * 60); // 20 horas, el doble de la meta

        $this->actingAs($user)->getJson('/api/me/summary')
            ->assertJsonPath('progreso_porcentaje', 100);
    }

    public function test_student_sees_only_their_own_attendance_history(): void
    {
        $user = User::factory()->create();
        $student = Student::factory()->create(['user_id' => $user->id]);
        $other = Student::factory()->create();
        $this->closedSessionFor($student, 60);
        $this->closedSessionFor($other, 60);

        $response = $this->actingAs($user)->getJson('/api/me/attendance');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_lab_status_lists_only_open_sessions(): void
    {
        $admin = User::factory()->admin()->create();
        $inLab = Student::factory()->create(['nombre' => 'Presente Ahora']);
        $notInLab = Student::factory()->create();

        AttendanceSession::create([
            'student_id' => $inLab->id,
            'entry_event_id' => AttendanceEvent::create([
                'student_id' => $inLab->id, 'type' => 'entry', 'confidence' => 0.9,
                'occurred_at' => now(), 'source' => 'face_recognition',
            ])->id,
            'started_at' => now(), 'status' => 'open',
        ]);
        $this->closedSessionFor($notInLab, 30);

        $response = $this->actingAs($admin)->getJson('/api/lab/status');

        $response->assertOk();
        $names = array_column($response->json('estudiantes_dentro'), 'nombre');
        $this->assertSame(['Presente Ahora'], $names);
    }
}
