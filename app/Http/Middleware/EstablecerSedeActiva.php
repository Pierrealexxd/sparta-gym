<?php

namespace App\Http\Middleware;

use App\Support\GymContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija el gimnasio activo de la petición según lo que el usuario tenga
 * guardado en sesión, validando que de verdad tenga acceso a esa sede.
 * Corre antes de 'rol' para que BelongsToGym ya filtre correctamente en
 * cualquier consulta que el controlador haga.
 *
 * "sede_activa_id" en sesión puede ser: un id numérico de una sede propia,
 * el string "todas" (sólo válido con el permiso sedes.ver-todas), o no
 * existir todavía (primera visita: cae a la sede de origen del usuario).
 */
class EstablecerSedeActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $seleccion = $request->session()->get('sede_activa_id');

        // "todas" sólo es válido si el usuario tiene el permiso — si no,
        // se ignora silenciosamente y cae a su sede de origen. No abortar
        // con error: una sesión vieja o manipulada no debería romper la
        // pantalla, sólo perder el privilegio que no le corresponde.
        if ($seleccion === 'todas' && $user->tienePermiso('sedes.ver-todas')) {
            GymContext::set(null);

            return $next($request);
        }

        $sede = $user->sedesDisponibles()->firstWhere('id', $seleccion) ?? $user->gym;

        GymContext::set($sede);

        return $next($request);
    }
}
