<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\FaceEmbeddingComputationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\UploadFacePhotoRequest;
use App\Http\Resources\FaceProfileResource;
use App\Models\FaceEmbedding;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\FaceEmbeddingComputer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Enrolamiento facial vía subida de foto — docs/02-diseno.md §1.
 * La foto NUNCA se persiste: se guarda de forma transitoria en el disco
 * privado `local` (storage/app/private) solo mientras se calcula el
 * embedding, y se borra siempre (éxito o error) antes de responder.
 */
class StudentFacePhotoController extends Controller
{
    public function __construct(
        private readonly FaceEmbeddingComputer $embeddingComputer,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function show(Student $student): FaceProfileResource|JsonResponse
    {
        $this->authorize('view', $student);

        $embedding = $student->faceEmbedding;

        if (! $embedding) {
            return response()->json(['existe' => false]);
        }

        return new FaceProfileResource($embedding);
    }

    public function store(UploadFacePhotoRequest $request, Student $student): FaceProfileResource
    {
        if ($student->faceEmbedding()->exists()) {
            abort(409, 'Este estudiante ya tiene un enrolamiento facial. Solo un administrador puede reemplazarlo.');
        }

        $computed = $this->computeFromUpload($request, $student);

        $embedding = DB::transaction(fn () => FaceEmbedding::query()->create([
            'student_id' => $student->id,
            'vector' => $computed['embedding'],
            'modelo' => $computed['modelo'],
        ]));

        return new FaceProfileResource($embedding);
    }

    public function update(UploadFacePhotoRequest $request, Student $student): FaceProfileResource
    {
        $existing = $student->faceEmbedding;
        $computed = $this->computeFromUpload($request, $student);

        $embedding = DB::transaction(function () use ($existing, $computed, $student, $request) {
            $old = $existing ? ['modelo' => $existing->modelo, 'created_at' => (string) $existing->created_at] : null;

            $existing?->delete();

            $new = FaceEmbedding::query()->create([
                'student_id' => $student->id,
                'vector' => $computed['embedding'],
                'modelo' => $computed['modelo'],
            ]);

            $this->auditLogger->log(
                actor: $request->user(),
                auditable: $student,
                action: 'face_profile_replaced',
                oldValues: $old ?? [],
                newValues: ['modelo' => $new->modelo, 'created_at' => (string) $new->created_at],
            );

            return $new;
        });

        return new FaceProfileResource($embedding);
    }

    private function computeFromUpload(UploadFacePhotoRequest $request, Student $student): array
    {
        $disk = Storage::disk('local');
        $storedPath = $request->file('photo')->store('tmp-enrollment', 'local');

        try {
            return $this->embeddingComputer->compute($disk->path($storedPath));
        } catch (FaceEmbeddingComputationException $e) {
            abort(422, $e->getMessage());
        } finally {
            $disk->delete($storedPath);
        }
    }
}
