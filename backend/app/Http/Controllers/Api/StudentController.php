<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Student::class);

        $students = Student::query()
            ->withCount('faceEmbedding')
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(function ($q) use ($request) {
                $q->where('matricula', 'ilike', "%{$request->string('search')}%")
                    ->orWhere('nombre', 'ilike', "%{$request->string('search')}%");
            }))
            ->orderBy('nombre')
            ->paginate(20);

        return StudentResource::collection($students);
    }

    public function store(StoreStudentRequest $request): StudentResource
    {
        // refresh() porque `estado` se llena con su default a nivel de base de
        // datos (ver migración), no en el objeto recién creado en memoria.
        $student = Student::query()->create($request->validated())->refresh();

        return new StudentResource($student);
    }

    public function show(Student $student): StudentResource
    {
        $this->authorize('view', $student);

        return new StudentResource($student->loadCount('faceEmbedding'));
    }

    public function update(UpdateStudentRequest $request, Student $student): StudentResource
    {
        $student->update($request->validated());

        return new StudentResource($student->fresh()->loadCount('faceEmbedding'));
    }
}
