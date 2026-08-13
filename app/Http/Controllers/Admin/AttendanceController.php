<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Services\AsistenciaService;
use App\Support\GymContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * El único módulo "Asistencia" del admin: el calendario de marcaciones
 * LABORALES del staff (staff_attendances) — antes vivía en la pestaña
 * aparte "Personal". El calendario de entradas de clientes y el registro
 * por torno (código/QR/documento del socio) se dieron de baja: se decidió
 * que el admin solo necesita ver la asistencia laboral del entrenador acá
 * (ver decisión del 13-08-2026); el alta de clientes sigue viviendo en el
 * panel del entrenador (Entrenador\AttendanceController::registrarEntrada).
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly AsistenciaService $asistencias)
    {
    }

    public function calendario(Request $request): View
    {
        $mes  = (int) $request->integer('mes', now()->month);
        $anio = (int) $request->integer('anio', now()->year);

        if ($mes < 1 || $mes > 12) {
            $mes = now()->month;
        }
        if ($anio < 2000 || $anio > now()->year + 1) {
            $anio = now()->year;
        }

        $inicio     = Carbon::create($anio, $mes, 1)->startOfMonth();
        $fin        = $inicio->copy()->endOfMonth();
        $modoTodas  = GymContext::id() === null;
        $entrenador = $request->integer('entrenador') ?: null;
        $metodo     = $request->string('metodo')->trim()->toString() ?: null;

        $porDia = StaffAttendance::with(['user', 'gym'])
            ->when(GymContext::id(), fn ($q, $gymId) => $q->where('gym_id', $gymId))
            ->whereBetween('clocked_in_at', [$inicio, $fin])
            ->when($entrenador, fn ($q, $id) => $q->where('user_id', $id))
            ->when(in_array($metodo, ['manual', 'qr'], true), fn ($q, $m) => $q->where('method', $m))
            ->get()
            ->groupBy(fn (StaffAttendance $s) => $s->clocked_in_at->toDateString());

        // Quiénes marcaron en el mes, para el filtro por entrenador.
        $entrenadores = User::whereIn('id', StaffAttendance::select('user_id')
            ->whereBetween('clocked_in_at', [$inicio, $fin])
            ->distinct())
            ->orderBy('name')
            ->get();

        $offset = $inicio->dayOfWeek === 0 ? 6 : $inicio->dayOfWeek - 1;

        return view('admin.asistencia.calendario', [
            'mes'          => $mes,
            'anio'         => $anio,
            'nombreMes'    => $inicio->translatedFormat('F Y'),
            'diasDelMes'   => $inicio->daysInMonth,
            'offset'       => $offset,
            'anterior'     => $inicio->copy()->subMonth(),
            'siguiente'    => $inicio->copy()->addMonth(),
            'porDia'       => $porDia,
            'celdas'       => $porDia->map->count(),
            'modoTodas'    => $modoTodas,
            'entrenadores' => $entrenadores,
            'entrenador'   => $entrenador,
            'metodo'       => $metodo,
            'filtros'      => array_filter(
                ['entrenador' => $entrenador, 'metodo' => $metodo],
                fn ($v) => ! blank($v)
            ),
        ]);
    }
}
