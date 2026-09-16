<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * El estudiante ya debe existir en `students` (lo da de alta el admin,
     * docs/02-diseno.md §1) — este endpoint solo crea la cuenta que lo
     * vincula, usando su matrícula como "código de invitación".
     */
    public function register(RegisterRequest $request): UserResource
    {
        $student = Student::query()->where('matricula', $request->validated('matricula'))->first();

        if ($student === null) {
            abort(404, 'No existe ningún estudiante con esa matrícula. Pide al administrador que te dé de alta primero.');
        }

        if ($student->user_id !== null) {
            abort(409, 'Esta matrícula ya tiene una cuenta asociada. Si es tuya, inicia sesión en vez de registrarte.');
        }

        $user = DB::transaction(function () use ($request, $student) {
            $user = User::create([
                'name' => $student->nombre,
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
                'role' => 'student',
            ]);

            $student->update(['user_id' => $user->id]);

            return $user;
        });

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return new UserResource($user->load('student'));
    }

    public function login(LoginRequest $request): UserResource
    {
        $email = $this->resolveEmail($request->validated('identifier'));

        if (
            $email === null
            || ! Auth::guard('web')->attempt(['email' => $email, 'password' => $request->validated('password')])
        ) {
            abort(422, 'Credenciales inválidas.');
        }

        $request->session()->regenerate();

        return new UserResource(Auth::user()->load('student'));
    }

    /**
     * El login acepta el correo directamente, o la matrícula del
     * estudiante (se resuelve a su correo antes de intentar autenticar).
     * No distingue en la respuesta cuál de los dos casos falló — mismo
     * principio que ya regía solo para email (docs/03-seguridad.md).
     */
    private function resolveEmail(string $identifier): ?string
    {
        if (str_contains($identifier, '@')) {
            return $identifier;
        }

        return Student::query()->where('matricula', $identifier)->first()?->user?->email;
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('student'));
    }
}
