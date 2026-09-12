<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;

/**
 * La llama recognition-app (auth:sanctum + abilities:sync) para refrescar su
 * caché local de embeddings — docs/02-diseno.md §5 y §6.2. Nunca la consume
 * la SPA web.
 */
class SyncController extends Controller
{
    public function faceCatalog(): JsonResponse
    {
        $students = Student::query()
            ->where('estado', 'activo')
            ->whereHas('faceEmbedding')
            ->with('faceEmbedding:id,student_id,vector,modelo')
            ->get(['id', 'matricula']);

        return response()->json([
            'catalog' => $students->map(fn (Student $student) => [
                'student_id' => $student->id,
                'matricula' => $student->matricula,
                'embedding' => $student->faceEmbedding->vector,
                'modelo' => $student->faceEmbedding->modelo,
            ]),
        ]);
    }
}
