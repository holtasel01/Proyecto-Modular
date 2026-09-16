<?php

namespace Tests\Feature\Students;

use App\Models\Student;
use App\Models\User;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    public function test_admin_can_create_a_student(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/students', [
            'matricula' => '100001',
            'nombre' => 'Estudiante de Prueba',
            'carrera' => 'ISC',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.matricula', '100001')
            ->assertJsonPath('data.estado', 'activo'); // default de la BD, ver StudentController@store

        $this->assertDatabaseHas('students', ['matricula' => '100001']);
    }

    public function test_student_cannot_create_a_student(): void
    {
        $studentUser = User::factory()->create();

        $this->actingAs($studentUser)->postJson('/api/students', [
            'matricula' => '100002',
            'nombre' => 'No debería poder',
        ])->assertStatus(403);
    }

    public function test_admin_can_list_and_search_students(): void
    {
        $admin = User::factory()->admin()->create();
        Student::factory()->create(['matricula' => '100011', 'nombre' => 'Ana López']);
        Student::factory()->create(['matricula' => '100012', 'nombre' => 'Beto Ruiz']);

        $response = $this->actingAs($admin)->getJson('/api/students?search=Ana');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('100011', $response->json('data.0.matricula'));
    }

    public function test_student_can_view_own_record_but_not_another_students(): void
    {
        $own = Student::factory()->create();
        $other = Student::factory()->create();
        $user = User::factory()->create();
        $own->update(['user_id' => $user->id]);

        $this->actingAs($user)->getJson("/api/students/{$own->id}")->assertOk();
        $this->actingAs($user)->getJson("/api/students/{$other->id}")->assertStatus(403);
    }

    public function test_admin_can_update_a_student(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create(['estado' => 'activo']);

        $response = $this->actingAs($admin)->patchJson("/api/students/{$student->id}", [
            'estado' => 'inactivo',
        ]);

        $response->assertOk()->assertJsonPath('data.estado', 'inactivo');
    }

    public function test_update_rejects_an_invalid_estado_value(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();

        $this->actingAs($admin)->patchJson("/api/students/{$student->id}", [
            'estado' => 'valor-invalido',
        ])->assertStatus(422);
    }

    public function test_matricula_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();
        Student::factory()->create(['matricula' => '100099']);

        $this->actingAs($admin)->postJson('/api/students', [
            'matricula' => '100099',
            'nombre' => 'Otro Estudiante',
        ])->assertStatus(422);
    }

    public function test_matricula_with_letters_is_rejected(): void
    {
        // Las matrículas de la UDG son solo numéricas.
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/students', [
            'matricula' => 'A100099',
            'nombre' => 'Con Letras',
        ])->assertStatus(422)->assertJsonValidationErrors('matricula');
    }
}
