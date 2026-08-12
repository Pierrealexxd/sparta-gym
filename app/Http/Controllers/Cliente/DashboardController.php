<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $socio = $request->user()->member()->with([
            'currentMembership.plan',
            'currentAssignment.trainer.user',
            'goals' => fn ($q) => $q->activos(),
            'routines' => fn ($q) => $q->activas()->with('days.exercises.exercise'),
            'sales' => fn ($q) => $q->completadas()->latest('sold_at')->take(8),
            'attendances' => fn ($q) => $q->latest('checked_in_at')->take(10),
            'testimonial',
        ])->firstOrFail();

        $kpis = [
            'diasRestantes'      => $socio->days_left,
            'asistenciasMes'     => $socio->attendances()->whereMonth('checked_in_at', now()->month)->whereYear('checked_in_at', now()->year)->count(),
            'rutinasActivas'     => $socio->routines->count(),
            'objetivosPendientes'=> $socio->goals->count(),
        ];

        return view('cliente.dashboard', ['socio' => $socio, 'kpis' => $kpis]);
    }
}
