<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request): UserResource
    {
        if (! Auth::guard('web')->attempt($request->validated())) {
            abort(422, 'Credenciales inválidas.');
        }

        $request->session()->regenerate();

        return new UserResource(Auth::user()->load('student'));
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
