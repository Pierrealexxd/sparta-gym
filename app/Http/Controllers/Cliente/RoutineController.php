<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * "Mi rutina" — versión canónica y completa de la rutina del socio (ver
 * PROMPT-EJECUCION-MI-RUTINA.md, Parte 1). Antes estaba duplicada: cards
 * colapsables en el dashboard y una sección en /cliente/progreso. Ahora las
 * dos apuntan acá; esta pantalla es la única que muestra series, reps,
 * peso, descanso y notas completos, pensada para leerse de pie con el
 * celular en la mano entre series.
 *
 * El socio sale siempre de $request->user()->member() — nunca de un
 * member_id o routine_id del request, así que no hay forma de que un
 * cliente vea la rutina de otro.
 */
class RoutineController extends Controller
{
    public function __invoke(Request $request): View
    {
        $socio = $request->user()->member()->with([
            'routines' => fn ($q) => $q->activas()
                ->with(['days.exercises.exercise', 'program'])
                ->latest(),
        ])->firstOrFail();

        return view('cliente.rutina', [
            'rutinaActiva' => $socio->routines->first(),
        ]);
    }
}
