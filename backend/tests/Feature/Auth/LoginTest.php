<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_valid_credentials_log_the_user_in(): void
    {
        $user = User::factory()->create(['email' => 'estudiante@test.com', 'password' => bcrypt('secreto123')]);

        // Un login exitoso regenera la sesión — necesita el Referer para que
        // Sanctum la arranque, igual que en test_login_then_logout_ends_the_session.
        $response = $this->withHeader('Referer', 'http://localhost:5173/')->postJson('/api/login', [
            'email' => 'estudiante@test.com',
            'password' => 'secreto123',
        ]);

        $response->assertOk()->assertJsonPath('data.id', $user->id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'estudiante2@test.com', 'password' => bcrypt('secreto123')]);

        $response = $this->postJson('/api/login', [
            'email' => 'estudiante2@test.com',
            'password' => 'incorrecta',
        ]);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_unknown_email_is_rejected_without_revealing_it_does_not_exist(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nadie@test.com',
            'password' => 'lo-que-sea',
        ]);

        // Mismo mensaje/código que una contraseña incorrecta (arriba) —
        // no debe distinguirse si el email existe o no (docs/03-seguridad.md).
        $response->assertStatus(422);
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        RateLimiter::clear('estudiante3@test.com|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'estudiante3@test.com', 'password' => 'x'])
                ->assertStatus(422);
        }

        $this->postJson('/api/login', ['email' => 'estudiante3@test.com', 'password' => 'x'])
            ->assertStatus(429);
    }

    public function test_login_then_logout_ends_the_session(): void
    {
        // Flujo real (no actingAs()): login() y logout() dependen de la
        // sesión, y Sanctum solo la arranca si la request "parece" venir del
        // frontend (Referer/Origin en SANCTUM_STATEFUL_DOMAINS) — actingAs()
        // se salta ese mecanismo por completo, así que aquí conviene probar
        // el camino real end-to-end en vez de mezclarlo con el atajo de test.
        $user = User::factory()->create(['password' => bcrypt('secreto123')]);
        $referer = ['Referer' => 'http://localhost:5173/'];

        $this->withHeaders($referer)
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'secreto123'])
            ->assertOk();

        // Illuminate\Auth\RequestGuard cachea el usuario resuelto la primera
        // vez dentro de este mismo método de prueba — sin esto, las llamadas
        // siguientes ni vuelven a comprobar la sesión real.
        Auth::forgetGuards();
        $this->withHeaders($referer)->getJson('/api/me')->assertOk();

        Auth::forgetGuards();
        $this->withHeaders($referer)->postJson('/api/logout')->assertOk();

        Auth::forgetGuards();
        $this->withHeaders($referer)->getJson('/api/me')->assertStatus(401);
    }

    public function test_me_returns_the_authenticated_user_with_role(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/me');

        $response->assertOk()->assertJsonPath('data.role', 'admin');
    }

    public function test_guest_cannot_access_protected_routes(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
        $this->getJson('/api/students')->assertStatus(401);
    }
}
