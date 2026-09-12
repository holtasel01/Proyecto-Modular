<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contraparte de EnsureUserPrincipal: las rutas exclusivas del device
 * (`/sync/face-catalog`, `/attendance/events`) filtran por `abilities:*`,
 * pero Sanctum le da a cualquier sesión SPA autenticada (User) un
 * "TransientToken" que responde que sí a *cualquier* ability — así que sin
 * este middleware, un usuario logueado por la web (admin o estudiante) podía
 * llamar estas rutas como si fuera el device del laboratorio y falsificar
 * eventos de asistencia. Encontrado en la Etapa 9 al escribir las pruebas;
 * corregido de inmediato — ver docs/03-seguridad.md.
 */
class EnsureDevicePrincipal
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() instanceof Device, 403, 'Esta ruta es exclusiva del dispositivo del laboratorio.');

        return $next($request);
    }
}
