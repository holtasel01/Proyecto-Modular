<?php

namespace Tests\Feature\Analytics;

use App\Models\AttendanceEvent;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class StudentClusterTest extends TestCase
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

    public function test_student_cannot_access_the_clustering_endpoint(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->getJson('/api/analytics/student-clusters')->assertStatus(403);
    }

    public function test_fails_clearly_when_fewer_than_three_students_have_sessions(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();
        $this->closedSessionAt($student, '2026-01-05 08:00:00', 60);

        // solo 1 estudiante con sesiones — hacen falta al menos 3
        $response = $this->actingAs($admin)->getJson('/api/analytics/student-clusters');

        $response->assertStatus(422);
        $this->assertStringContainsString('al menos 3', $response->json('message'));
    }

    public function test_students_without_any_closed_session_are_excluded_from_the_payload(): void
    {
        $admin = User::factory()->admin()->create();

        $withSessions1 = Student::factory()->create();
        $withSessions2 = Student::factory()->create();
        $withSessions3 = Student::factory()->create();
        foreach ([$withSessions1, $withSessions2, $withSessions3] as $s) {
            $this->closedSessionAt($s, '2026-01-05 08:00:00', 60);
        }

        // este estudiante activo nunca ha tenido una sesión — no debe entrar al cálculo
        Student::factory()->create();
        // este tiene sesión pero está inactivo — tampoco debe entrar
        $inactive = Student::factory()->inactivo()->create();
        $this->closedSessionAt($inactive, '2026-01-05 08:00:00', 60);

        $sentStudentIds = null;
        Process::fake(function (PendingProcess $process) use (&$sentStudentIds) {
            $sent = json_decode($process->input, true);
            $sentStudentIds = array_column($sent['students'], 'student_id');

            return Process::result(output: json_encode(['clusters' => [], 'centroids' => []]));
        });

        $this->actingAs($admin)->getJson('/api/analytics/student-clusters')->assertOk();

        $this->assertCount(3, $sentStudentIds);
        $this->assertEqualsCanonicalizing(
            [$withSessions1->id, $withSessions2->id, $withSessions3->id],
            $sentStudentIds,
        );
    }

    public function test_computes_features_correctly_for_a_perfectly_consistent_student(): void
    {
        $admin = User::factory()->admin()->create();

        // 4 sesiones de 2 horas, una por semana durante 4 semanas exactas —
        // horas/semana y sesiones/semana deben salir constantes, variabilidad = 0.
        $consistent = Student::factory()->create();
        $this->closedSessionAt($consistent, '2026-01-05 08:00:00', 120);
        $this->closedSessionAt($consistent, '2026-01-12 08:00:00', 120);
        $this->closedSessionAt($consistent, '2026-01-19 08:00:00', 120);
        $this->closedSessionAt($consistent, '2026-01-26 08:00:00', 120);

        // dos estudiantes más, solo para superar el mínimo de 3 con datos
        $other1 = Student::factory()->create();
        $this->closedSessionAt($other1, '2026-01-05 08:00:00', 60);
        $other2 = Student::factory()->create();
        $this->closedSessionAt($other2, '2026-01-05 08:00:00', 60);

        $sentFeatures = null;
        Process::fake(function (PendingProcess $process) use (&$sentFeatures, $consistent) {
            $sent = json_decode($process->input, true);
            foreach ($sent['students'] as $row) {
                if ($row['student_id'] === $consistent->id) {
                    $sentFeatures = $row['features'];
                }
            }

            return Process::result(output: json_encode(['clusters' => [], 'centroids' => []]));
        });

        $this->actingAs($admin)->getJson('/api/analytics/student-clusters')->assertOk();

        // Los floats sin parte fraccionaria pierden el .0 al pasar por
        // json_encode/json_decode (docs/04-pruebas.md §4) — se compara con
        // assertEquals contra números sin forzar el tipo float.
        $this->assertNotNull($sentFeatures);
        $this->assertEquals(8, $sentFeatures['horas_acumuladas']); // 4 × 2h
        $this->assertEquals(4, $sentFeatures['numero_sesiones']);
        $this->assertEquals(120, $sentFeatures['duracion_promedio_sesion_min']);
        $this->assertEquals(2, $sentFeatures['horas_promedio_semana']); // 8h / 4 semanas
        $this->assertEquals(1, $sentFeatures['sesiones_promedio_semana']); // 4 sesiones / 4 semanas
        $this->assertEquals(0, $sentFeatures['variabilidad_semanal']); // exactamente igual cada semana
    }

    public function test_returns_the_python_scripts_output_as_is_on_success(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (range(1, 3) as $i) {
            $s = Student::factory()->create();
            $this->closedSessionAt($s, '2026-01-05 08:00:00', 60);
        }

        $fakeResult = [
            'clusters' => [['student_id' => 1, 'cluster' => 0, 'cluster_label' => 'baja actividad']],
            'centroids' => [['cluster' => 0, 'cluster_label' => 'baja actividad']],
        ];
        Process::fake(['*' => Process::result(output: json_encode($fakeResult))]);

        $response = $this->actingAs($admin)->getJson('/api/analytics/student-clusters');

        $response->assertOk()->assertJson($fakeResult);
    }

    public function test_a_python_error_is_surfaced_as_a_clean_422(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (range(1, 3) as $i) {
            $s = Student::factory()->create();
            $this->closedSessionAt($s, '2026-01-05 08:00:00', 60);
        }

        Process::fake(['*' => Process::result(output: json_encode(['error' => 'algo salió mal en el script']))]);

        $response = $this->actingAs($admin)->getJson('/api/analytics/student-clusters');

        $response->assertStatus(422)->assertJsonPath('message', 'algo salió mal en el script');
    }
}
