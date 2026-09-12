<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La llama el device autenticado (auth:sanctum + ability attendance:write en
 * la ruta) — no un usuario con rol, por eso no hay chequeo de Policy aquí.
 */
class RecognitionEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'confidence' => ['required', 'numeric', 'between:0,1'],
        ];
    }
}
