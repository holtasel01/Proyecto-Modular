<?php

namespace Tests\Feature\Incidents;

use App\Models\AttendanceEvent;
use App\Models\AttendanceSession;
use App\Models\Incident;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class IncidentTest extends TestCase
{
    public function test_close_stale_sessions_flags_sessions_open_too_long(): void
    {
        $student = Student::factory()->create();
        $entry = AttendanceEvent::create([
            'student_id' => $student->id, 'type' => 'entry', 'confidence' => 0.9,
            'occurred_at' => Carbon::now()->subHours(20), 'source' => 'face_recognition',
        ]);
        $session = AttendanceSession::create([
            'student_id' => $student->id, 'entry_event_id' => $entry->id,
            'started_at' => $entry->occurred_at, 'status' => 'open',
        ]);

        $this->artisan('attendance:close-stale-sessions')
            ->expectsOutputToContain('Sesiones marcadas como inconsistentes: 1')
            ->assertExitCode(0);

        $this->assertSame('inconsistent', $session->fresh()->status);
        $this->assertDatabaseHas('incidents', [
            'type' => 'entrada_sin_salida',
            'attendance_session_id' => $session->id,
        ]);
    }

    public function test_close_stale_sessions_does_not_touch_recent_open_sessions(): void
    {
        $student = Student::factory()->create();
        $entry = AttendanceEvent::create([
            'student_id' => $student->id, 'type' => 'entry', 'confidence' => 0.9,
            'occurred_at' => Carbon::now()->subHours(2), 'source' => 'face_recognition',
        ]);
        $session = AttendanceSession::create([
            'student_id' => $student->id, 'entry_event_id' => $entry->id,
            'started_at' => $entry->occurred_at, 'status' => 'open',
        ]);

        $this->artisan('attendance:close-stale-sessions')->assertExitCode(0);

        $this->assertSame('open', $session->fresh()->status);
        $this->assertSame(0, Incident::query()->count());
    }

    public function test_admin_can_list_and_filter_incidents(): void
    {
        $admin = User::factory()->admin()->create();
        Incident::query()->create(['type' => 'baja_confianza', 'status' => 'open']);
        Incident::query()->create(['type' => 'duplicado', 'status' => 'resolved']);

        $response = $this->actingAs($admin)->getJson('/api/incidents?status=open');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_can_resolve_an_incident(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = Incident::query()->create(['type' => 'baja_confianza', 'status' => 'open']);

        $response = $this->actingAs($admin)->patchJson("/api/incidents/{$incident->id}", [
            'resolution_note' => 'Se verificó manualmente, era correcto.',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'resolved');
        $this->assertSame($admin->id, $incident->fresh()->resolved_by);
        $this->assertNotNull($incident->fresh()->resolved_at);
    }

    public function test_resolving_requires_a_note(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = Incident::query()->create(['type' => 'baja_confianza', 'status' => 'open']);

        $this->actingAs($admin)->patchJson("/api/incidents/{$incident->id}", [])
            ->assertStatus(422);
    }

    public function test_student_cannot_view_or_resolve_incidents(): void
    {
        $user = User::factory()->create();
        $incident = Incident::query()->create(['type' => 'baja_confianza', 'status' => 'open']);

        $this->actingAs($user)->getJson('/api/incidents')->assertStatus(403);
        $this->actingAs($user)->patchJson("/api/incidents/{$incident->id}", [
            'resolution_note' => 'intento no autorizado',
        ])->assertStatus(403);
    }
}
