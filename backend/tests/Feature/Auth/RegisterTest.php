<?php

namespace Tests\Feature\Auth;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    public function test_student_can_register_with_a_pre_existing_matricula(): void
    {
        $student = Student::factory()->create(['matricula' => '218111111', 'user_id' => null]);

        $response = $this->withHeader('Referer', 'http://localhost:5173/')->postJson('/api/register', [
            'matricula' => '218111111',
            'email' => 'nuevo@test.com',
            'password' => 'secreto123',
        ]);

        $response->assertCreated()->assertJsonPath('data.role', 'student');

        $this->assertDatabaseHas('users', ['email' => 'nuevo@test.com', 'role' => 'student']);
        $student->refresh();
        $this->assertNotNull($student->user_id);
        $this->assertSame('nuevo@test.com', $student->user->email);

        // queda logueado de inmediato tras registrarse
        Auth::forgetGuards();
        $this->withHeader('Referer', 'http://localhost:5173/')->getJson('/api/me')->assertOk();
    }

    public function test_registration_is_rejected_when_the_matricula_does_not_exist(): void
    {
        $response = $this->postJson('/api/register', [
            'matricula' => '999999998',
            'email' => 'nadie@test.com',
            'password' => 'secreto123',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('users', ['email' => 'nadie@test.com']);
    }

    public function test_registration_is_rejected_when_the_matricula_already_has_an_account(): void
    {
        $existingUser = User::factory()->create();
        Student::factory()->create(['matricula' => '218222222', 'user_id' => $existingUser->id]);

        $response = $this->postJson('/api/register', [
            'matricula' => '218222222',
            'email' => 'otro@test.com',
            'password' => 'secreto123',
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseMissing('users', ['email' => 'otro@test.com']);
    }

    public function test_registration_is_rejected_when_the_email_is_already_taken(): void
    {
        User::factory()->create(['email' => 'repetido@test.com']);
        Student::factory()->create(['matricula' => '218333333', 'user_id' => null]);

        $response = $this->postJson('/api/register', [
            'matricula' => '218333333',
            'email' => 'repetido@test.com',
            'password' => 'secreto123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_registration_requires_a_password_of_at_least_eight_characters(): void
    {
        Student::factory()->create(['matricula' => '218444444', 'user_id' => null]);

        $response = $this->postJson('/api/register', [
            'matricula' => '218444444',
            'email' => 'corto@test.com',
            'password' => '123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_registered_student_can_log_in_afterwards_with_matricula_or_email(): void
    {
        Student::factory()->create(['matricula' => '218555555', 'user_id' => null]);
        $referer = ['Referer' => 'http://localhost:5173/'];

        $this->withHeaders($referer)->postJson('/api/register', [
            'matricula' => '218555555',
            'email' => 'flujo@test.com',
            'password' => 'secreto123',
        ])->assertCreated();

        Auth::forgetGuards();
        $this->withHeaders($referer)->postJson('/api/logout')->assertOk();

        Auth::forgetGuards();
        $this->withHeaders($referer)->postJson('/api/login', [
            'identifier' => '218555555',
            'password' => 'secreto123',
        ])->assertOk();
    }
}
