<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceEvent;
use App\Models\AttendanceSession;
use App\Models\Device;
use App\Models\Incident;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Prueba AttendanceService a través de la API real (POST /attendance/events),
 * tal como lo llamaría recognition-app — docs/02-diseno.md §4.
 */
class AttendanceServiceTest extends TestCase
{
    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->device = Device::factory()->create();
        Sanctum::actingAs($this->device, ['sync', 'attendance:write']);
    }

    public function test_first_recognition_creates_an_entry_and_an_open_session(): void
    {
        $student = Student::factory()->create();

        $response = $this->postJson('/api/attendance/events', [
            'student_id' => $student->id,
            'confidence' => 0.9,
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('event.type', 'entry')
            ->assertJsonPath('session.status', 'open');

        $this->assertDatabaseHas('attendance_sessions', ['student_id' => $student->id, 'status' => 'open']);
    }

    public function test_second_recognition_after_the_duplicate_window_closes_the_session_with_duration(): void
    {
        $student = Student::factory()->create();

        Carbon::setTestNow('2026-01-01 08:00:00');
        $this->postJson('/api/attendance/events', ['student_id' => $student->id, 'confidence' => 0.9])
            ->assertCreated();

        Carbon::setTestNow('2026-01-01 10:30:00'); // +150 min, fuera de cualquier ventana anti-duplicado
        $response = $this->postJson('/api/attendance/events', ['student_id' => $student->id, 'confidence' => 0.9]);

        $response->assertCreated()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('event.type', 'exit')
            ->assertJsonPath('session.status', 'closed')
            ->assertJsonPath('session.duration_minutes', 150);

        Carbon::setTestNow();
    }

    public function test_immediate_repeat_is_ignored_as_a_duplicate(): void
    {
        $student = Student::factory()->create();

        $this->postJson('/api/attendance/events', ['student_id' => $student->id, 'confidence' => 0.9])
            ->assertCreated();

        $response = $this->postJson('/api/attendance/events', ['student_id' => $student->id, 'confidence' => 0.9]);

        $response->assertOk()->assertJsonPath('status', 'duplicate_ignored');
        $this->assertSame(1, AttendanceEvent::query()->count());
    }

    public function test_confidence_below_reject_threshold_is_rejected(): void
    {
        $student = Student::factory()->create();

        $response = $this->postJson('/api/attendance/events', [
            'student_id' => $student->id,
            'confidence' => 0.2, // < min_confidence_reject (0.50 por defecto)
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, AttendanceEvent::query()->count());
    }

    public function test_confidence_between_reject_and_trust_creates_a_low_confidence_incident(): void
    {
        $student = Student::factory()->create();

        // entre min_confidence_reject (0.50) y min_confidence_trust (0.75)
        $this->postJson('/api/attendance/events', ['student_id' => $student->id, 'confidence' => 0.6])
            ->assertCreated();

        $this->assertDatabaseHas('incidents', ['type' => 'baja_confianza']);
    }

    public function test_confidence_above_trust_threshold_creates_no_incident(): void
    {
        $student = Student::factory()->create();

        $this->postJson('/api/attendance/events', ['student_id' => $student->id, 'confidence' => 0.95])
            ->assertCreated();

        $this->assertSame(0, Incident::query()->count());
    }

    public function test_repeated_duplicates_eventually_create_an_incident(): void
    {
        $student = Student::factory()->create();

        $this->postJson('/api/attendance/events', ['student_id' => $student->id, 'confidence' => 0.9])
            ->assertCreated();

        // 3 duplicados seguidos disparan la incidencia (AttendanceService::DUPLICATE_STREAK_THRESHOLD)
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/attendance/events', ['student_id' => $student->id, 'confidence' => 0.9])
                ->assertJsonPath('status', 'duplicate_ignored');
        }

        $this->assertDatabaseHas('incidents', ['type' => 'duplicado']);
    }

    public function test_unknown_student_id_is_rejected(): void
    {
        $this->postJson('/api/attendance/events', [
            'student_id' => 999999,
            'confidence' => 0.9,
        ])->assertStatus(422);
    }

    public function test_confidence_out_of_range_is_rejected(): void
    {
        $student = Student::factory()->create();

        $this->postJson('/api/attendance/events', [
            'student_id' => $student->id,
            'confidence' => 1.5,
        ])->assertStatus(422);
    }

    public function test_device_last_seen_at_is_updated_on_a_successful_event(): void
    {
        $student = Student::factory()->create();
        $this->assertNull($this->device->fresh()->last_seen_at);

        $this->postJson('/api/attendance/events', ['student_id' => $student->id, 'confidence' => 0.9]);

        $this->assertNotNull($this->device->fresh()->last_seen_at);
    }
}
