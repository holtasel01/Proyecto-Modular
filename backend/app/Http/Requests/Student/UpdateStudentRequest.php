<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('student'));
    }

    public function rules(): array
    {
        $student = $this->route('student');

        return [
            // Solo números — así son las matrículas de la UDG, no se aceptan letras.
            'matricula' => ['sometimes', 'string', 'max:20', 'regex:/^\d+$/', Rule::unique('students', 'matricula')->ignore($student)],
            'nombre' => ['sometimes', 'string', 'max:150'],
            'carrera' => ['sometimes', 'nullable', 'string', 'max:100'],
            'horas_meta' => ['sometimes', 'integer', 'min:1'],
            'estado' => ['sometimes', Rule::in(['activo', 'inactivo'])],
        ];
    }
}
