<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `auth:sanctum` por sí solo acepta cualquier token válido, sea de un User o
 * de un Device — ambos comparten el mismo guard. Sin este middleware, un
 * token de dispositivo que llegue a una ruta pensada para usuarios (p. ej.
 * /students) haría fallar las Policies con un TypeError (500) en vez de un
 * 403 limpio, porque esperan un App\Models\User. Ver docs/02-diseno.md §7.
 */
class EnsureUserPrincipal
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() instanceof User, 403, 'Este token no puede usarse en esta ruta.');

        return $next($request);
    }
}
