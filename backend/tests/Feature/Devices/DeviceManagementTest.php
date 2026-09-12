<?php

namespace Tests\Feature\Devices;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class DeviceManagementTest extends TestCase
{
    public function test_admin_can_create_a_device_and_receives_a_token_once(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/devices', [
            'nombre' => 'PC Laboratorio A',
        ]);

        $response->assertCreated();
        $this->assertNotEmpty($response->json('data.plain_text_token'));
        $this->assertDatabaseHas('devices', ['nombre' => 'PC Laboratorio A']);
    }

    public function test_device_list_never_exposes_the_token(): void
    {
        $admin = User::factory()->admin()->create();
        Device::factory()->create();

        $response = $this->actingAs($admin)->getJson('/api/devices');

        $response->assertOk();
        $this->assertArrayNotHasKey('plain_text_token', $response->json('data.0'));
    }

    public function test_student_cannot_manage_devices(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/devices')->assertStatus(403);
        $this->actingAs($user)->postJson('/api/devices', ['nombre' => 'x'])->assertStatus(403);
    }

    public function test_revoking_a_device_invalidates_its_token_immediately(): void
    {
        $device = Device::factory()->create();
        $token = $device->createToken('t', ['sync', 'attendance:write'])->plainTextToken;

        // El token funciona antes de revocar.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/sync/face-catalog')->assertOk();

        // Revoca directamente con la misma lógica de DeviceController::destroy() —
        // se evita pasar por HTTP con actingAs() aquí porque esa autenticación de
        // usuario persiste en las siguientes llamadas del test incluso mandando
        // un header Authorization distinto, lo que ensuciaría esta prueba
        // (dejaría de probar "token revocado" y probaría "usuario logueado").
        $device->tokens()->delete();
        $device->delete();

        // Illuminate\Auth\RequestGuard cachea el usuario resuelto en el guard
        // 'sanctum' desde la primera llamada de este mismo método de prueba;
        // sin esto, la segunda petición ni siquiera vuelve a consultar la BD.
        Auth::forgetGuards();

        // Y deja de funcionar de inmediato después.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/sync/face-catalog')
            ->assertStatus(401);
    }
}
