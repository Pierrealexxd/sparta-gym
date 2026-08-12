<?php

namespace App\Http\Controllers\Entrenador;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Routine;
use App\Models\Sale;
use App\Models\Trainer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $entrenador = Trainer::where('user_id', $request->user()->id)->firstOrFail();

        // Indicadores del mes: lo mínimo para que el entrenador vea de un
        // vistazo cuánto ha ido moviendo, sin duplicar el detalle que ya
        // está en Ventas (pestañas Productos/Inscripciones/Rutinas).
        $kpis = [
            'clientesActivos'  => $entrenador->activeMembers()->count(),
            'rutinasMes'       => Routine::where('trainer_id', $entrenador->id)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'inscripcionesMes' => Membership::where('created_by', $request->user()->id)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'ventasMes'        => (float) Sale::where('sold_by', $request->user()->id)->completadas()->whereMonth('sold_at', now()->month)->whereYear('sold_at', now()->year)->sum('total'),
        ];

        return view('entrenador.dashboard', [
            'entrenador' => $entrenador,
            'kpis'       => $kpis,
        ]);
    }
}
