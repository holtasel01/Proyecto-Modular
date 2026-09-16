<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Límite general de la API (Etapa 8 — el skeleton de Laravel 11 ya no
        // lo trae registrado por defecto). Por usuario/device autenticado, o
        // por IP si la request no está autenticada todavía.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Límite específico y más estricto para /login y /register, por
        // identificador+IP — evita fuerza bruta sin bloquear a otros usuarios
        // que comparten la IP (ej. la misma red del laboratorio).
        // docs/02-diseno.md §14 (RNF2). Compartido entre ambas rutas: /login
        // manda 'identifier' (matrícula o correo), /register manda 'email'.
        RateLimiter::for('login', function (Request $request) {
            $key = (string) ($request->input('identifier') ?? $request->input('email'));

            return Limit::perMinute(5)->by($key.'|'.$request->ip());
        });
    }
}
