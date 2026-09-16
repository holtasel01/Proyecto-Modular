<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // matrícula del estudiante o correo — AuthController::resolveEmail() decide cuál es
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }
}
