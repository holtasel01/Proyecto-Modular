<?php

namespace Tests\Feature\Auth;

use App\Models\Device;
use App\Models\Student;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regresión de la vulnerabilidad encontrada en la Etapa 9 (docs/03-seguridad.md §0):
 * un `User` autenticado por la web podía llamar a las rutas exclusivas del
 * device gracias al TransientToken de Sanctum, que aprueba cualquier ability.
 */
class DevicePrincipalSeparationTest extends TestCase
{
    public function test_admin_session_cannot_report_attendance_events(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/attendance/events', [
            'student_id' => $student->id,
            'confidence' => 0.9,
        ]);

        $response->assertStatus(403);
    }

    public function test_student_session_cannot_report_attendance_events(): void
    {
        $student = Student::factory()->create();
        $studentUser = User::factory()->create();
        $student->update(['user_id' => $studentUser->id]);

        $response = $this->actingAs($studentUser)->postJson('/api/attendance/events', [
            'student_id' => $student->id,
            'confidence' => 0.9,
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_session_cannot_fetch_the_face_catalog(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/sync/face-catalog')->assertStatus(403);
    }

    public function test_device_token_cannot_access_user_only_routes(): void
    {
        $device = Device::factory()->create();
        Sanctum::actingAs($device, ['sync', 'attendance:write']);

        $this->getJson('/api/students')->assertStatus(403);
        $this->getJson('/api/me')->assertStatus(403);
        $this->getJson('/api/incidents')->assertStatus(403);
    }

    public function test_device_with_correct_ability_can_use_its_own_routes(): void
    {
        $device = Device::factory()->create();
        Sanctum::actingAs($device, ['sync', 'attendance:write']);

        $this->getJson('/api/sync/face-catalog')->assertOk();
    }

    public function test_device_without_the_required_ability_is_rejected(): void
    {
        $device = Device::factory()->create();
        Sanctum::actingAs($device, ['sync']); // sin 'attendance:write'

        $student = Student::factory()->create();

        $this->postJson('/api/attendance/events', [
            'student_id' => $student->id,
            'confidence' => 0.9,
        ])->assertStatus(403);
    }
}
