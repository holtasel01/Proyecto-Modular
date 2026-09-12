<?php

namespace Tests\Feature\Students;

use App\Exceptions\FaceEmbeddingComputationException;
use App\Models\Student;
use App\Models\User;
use App\Services\FaceEmbeddingComputer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * docs/02-diseno.md §1: el estudiante sube su propia foto una sola vez; solo
 * el admin puede reemplazarla después. FaceEmbeddingComputer (que invoca a
 * Python) se sustituye por un doble de prueba — estas pruebas verifican la
 * lógica de Laravel, no DeepFace (eso ya lo cubre recognition-app/tests/).
 */
class FacePhotoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function fakeSuccessfulComputer(): void
    {
        $this->mock(FaceEmbeddingComputer::class, function ($mock) {
            $mock->shouldReceive('compute')->andReturn([
                'embedding' => array_fill(0, 512, 0.1),
                'modelo' => 'Facenet512-fake',
            ]);
        });
    }

    public function test_student_can_upload_their_own_initial_photo(): void
    {
        $this->fakeSuccessfulComputer();

        $user = User::factory()->create();
        $student = Student::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/students/{$student->id}/face-photo", [
            'photo' => UploadedFile::fake()->image('yo.jpg'),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('face_embeddings', ['student_id' => $student->id, 'modelo' => 'Facenet512-fake']);
    }

    public function test_second_upload_attempt_is_rejected_with_conflict(): void
    {
        $this->fakeSuccessfulComputer();

        $user = User::factory()->create();
        $student = Student::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->postJson("/api/students/{$student->id}/face-photo", [
            'photo' => UploadedFile::fake()->image('yo.jpg'),
        ])->assertCreated();

        $this->actingAs($user)->postJson("/api/students/{$student->id}/face-photo", [
            'photo' => UploadedFile::fake()->image('otra.jpg'),
        ])->assertStatus(409);
    }

    public function test_student_cannot_replace_an_existing_enrollment(): void
    {
        $this->fakeSuccessfulComputer();

        $user = User::factory()->create();
        $student = Student::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->postJson("/api/students/{$student->id}/face-photo", [
            'photo' => UploadedFile::fake()->image('yo.jpg'),
        ]);

        $response = $this->actingAs($user)->putJson("/api/students/{$student->id}/face-photo", [
            'photo' => UploadedFile::fake()->image('otra.jpg'),
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_replace_an_existing_enrollment_and_it_gets_audited(): void
    {
        $this->fakeSuccessfulComputer();

        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();
        $this->actingAs($admin)->postJson("/api/students/{$student->id}/face-photo", [
            'photo' => UploadedFile::fake()->image('inicial.jpg'),
        ]);
        $originalEmbeddingId = $student->faceEmbedding()->first()->id;

        $response = $this->actingAs($admin)->putJson("/api/students/{$student->id}/face-photo", [
            'photo' => UploadedFile::fake()->image('nueva.jpg'),
        ]);

        // 201, no 200: Laravel marca automáticamente como "created" cualquier
        // Resource que envuelva un modelo recién insertado (wasRecentlyCreated) —
        // "reemplazar" internamente borra la fila vieja y crea una nueva.
        $response->assertCreated();
        $this->assertDatabaseMissing('face_embeddings', ['id' => $originalEmbeddingId]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'auditable_type' => 'Student',
            'auditable_id' => $student->id,
            'action' => 'face_profile_replaced',
        ]);
    }

    public function test_a_computation_error_is_returned_as_a_clean_422(): void
    {
        $this->mock(FaceEmbeddingComputer::class, function ($mock) {
            $mock->shouldReceive('compute')
                ->andThrow(new FaceEmbeddingComputationException('No se detectó ningún rostro en la imagen.'));
        });

        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/students/{$student->id}/face-photo", [
            'photo' => UploadedFile::fake()->image('borrosa.jpg'),
        ]);

        $response->assertStatus(422)->assertJsonPath('message', 'No se detectó ningún rostro en la imagen.');
        $this->assertDatabaseMissing('face_embeddings', ['student_id' => $student->id]);
    }

    public function test_upload_rejects_non_image_files(): void
    {
        $admin = User::factory()->admin()->create();
        $student = Student::factory()->create();

        $response = $this->actingAs($admin)
            ->postJson("/api/students/{$student->id}/face-photo", [
                'photo' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
            ]);

        $response->assertStatus(422);
    }

    public function test_other_student_cannot_upload_a_photo_for_someone_else(): void
    {
        $this->fakeSuccessfulComputer();

        $user = User::factory()->create();
        Student::factory()->create(['user_id' => $user->id]);
        $otherStudent = Student::factory()->create();

        $this->actingAs($user)->postJson("/api/students/{$otherStudent->id}/face-photo", [
            'photo' => UploadedFile::fake()->image('yo.jpg'),
        ])->assertStatus(403);
    }
}
