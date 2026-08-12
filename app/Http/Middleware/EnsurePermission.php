<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autorización fina dentro de un panel ya abierto por EnsureRole.
 * Uso: ->middleware('permiso:pagos.registrar')
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permiso): Response
    {
        abort_unless(
            $request->user()?->tienePermiso($permiso),
            403,
            'No tienes permiso para hacer esto.'
        );

        return $next($request);
    }
}
