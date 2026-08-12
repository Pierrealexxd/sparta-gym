<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la puerta de un panel a quien no tiene el rol adecuado.
 *
 * Uso: ->middleware('rol:admin,recepcion') — separa los slugs con coma
 * cuando más de un rol comparte la misma puerta (recepción entra al mismo
 * panel que administración, con menos permisos dentro).
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(
            $request->user()?->tieneRol(...$roles),
            403,
            'No tienes acceso a esta sección.'
        );

        return $next($request);
    }
}
