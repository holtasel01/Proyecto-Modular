<?php

namespace App\Http\Requests\Student;

use App\Models\Setting;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Student::class);
    }

    public function rules(): array
    {
        return [
            // Solo números — así son las matrículas de la UDG, no se aceptan letras.
            'matricula' => ['required', 'string', 'max:20', 'regex:/^\d+$/', 'unique:students,matricula'],
            'nombre' => ['required', 'string', 'max:150'],
            'carrera' => ['nullable', 'string', 'max:100'],
            'horas_meta' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'horas_meta' => $this->input('horas_meta', (int) Setting::get('default_horas_meta', 480)),
        ]);
    }
}
