<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Services\AsistenciaService;
use App\Support\GymContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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
        $f = $this->resolverFiltros($request);
        ['mes' => $mes, 'anio' => $anio, 'inicio' => $inicio, 'fin' => $fin,
            'modoTodas' => $modoTodas, 'entrenador' => $entrenador, 'metodo' => $metodo] = $f;

        $porDia = $this->consultaBase($f)
            ->get()
            ->groupBy(fn (StaffAttendance $s) => $s->clocked_in_at->toDateString());

        $offset = $inicio->dayOfWeek === 0 ? 6 : $inicio->dayOfWeek - 1;

        // <x-alterna-vista> no navega entre pestañas: renderiza los dos
        // slots (Lista y Calendario) en la MISMA respuesta y Alpine solo
        // muestra/oculta con x-show en el cliente — por eso esta vista
        // necesita tanto $porDia (slot Calendario) como $marcaciones
        // paginado (slot Lista, vía @include('admin.asistencia._lista')).
        // Un query extra por carga, pero cero recarga al alternar vistas.
        $marcaciones = $this->consultaBase($f)
            ->latest('clocked_in_at')
            ->paginate(20)
            ->withQueryString();

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
            'marcaciones'  => $marcaciones,
            'modoTodas'    => $modoTodas,
            'entrenadores' => $this->entrenadoresDelMes($inicio, $fin),
            'entrenador'   => $entrenador,
            'metodo'       => $metodo,
            'filtros'      => array_filter(
                ['entrenador' => $entrenador, 'metodo' => $metodo],
                fn ($v) => ! blank($v)
            ),
        ]);
    }

    /**
     * Vista Lista como ruta directa (p. ej. para compartir un enlace ya
     * filtrado): mismos filtros y misma consulta que usa calendario() para
     * su slot Lista, solo que acá es la única vista de la respuesta.
     */
    public function lista(Request $request): View
    {
        $f = $this->resolverFiltros($request);
        ['mes' => $mes, 'anio' => $anio, 'inicio' => $inicio, 'fin' => $fin,
            'modoTodas' => $modoTodas, 'entrenador' => $entrenador, 'metodo' => $metodo] = $f;

        $marcaciones = $this->consultaBase($f)
            ->latest('clocked_in_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.asistencia.lista', [
            'marcaciones'  => $marcaciones,
            'mes'          => $mes,
            'anio'         => $anio,
            'modoTodas'    => $modoTodas,
            'entrenadores' => $this->entrenadoresDelMes($inicio, $fin),
            'entrenador'   => $entrenador,
            'metodo'       => $metodo,
        ]);
    }

    /**
     * Filtros compartidos por calendario() y lista(): mes/año validados,
     * rango del mes y los filtros de entrenador/método ya saneados.
     */
    private function resolverFiltros(Request $request): array
    {
        $mes  = (int) $request->integer('mes', now()->month);
        $anio = (int) $request->integer('anio', now()->year);

        if ($mes < 1 || $mes > 12) {
            $mes = now()->month;
        }
        if ($anio < 2000 || $anio > now()->year + 1) {
            $anio = now()->year;
        }

        $inicio = Carbon::create($anio, $mes, 1)->startOfMonth();
        $fin    = $inicio->copy()->endOfMonth();

        return [
            'mes'        => $mes,
            'anio'       => $anio,
            'inicio'     => $inicio,
            'fin'        => $fin,
            'modoTodas'  => GymContext::id() === null,
            'entrenador' => $request->integer('entrenador') ?: null,
            'metodo'     => $request->string('metodo')->trim()->toString() ?: null,
        ];
    }

    /**
     * Consulta base de marcaciones del mes con los mismos filtros, para que
     * el calendario (agrupado) y la lista (paginada) nunca diverjan en qué
     * cuentan como "las marcaciones del mes".
     */
    private function consultaBase(array $filtros)
    {
        return StaffAttendance::with(['user', 'gym'])
            ->when(GymContext::id(), fn ($q, $gymId) => $q->where('gym_id', $gymId))
            ->whereBetween('clocked_in_at', [$filtros['inicio'], $filtros['fin']])
            ->when($filtros['entrenador'], fn ($q, $id) => $q->where('user_id', $id))
            ->when(
                in_array($filtros['metodo'], ['manual', 'qr'], true),
                fn ($q) => $q->where('method', $filtros['metodo'])
            );
    }

    /** Quiénes marcaron en el mes, para el filtro por entrenador. */
    private function entrenadoresDelMes(Carbon $inicio, Carbon $fin)
    {
        return User::whereIn('id', StaffAttendance::select('user_id')
            ->whereBetween('clocked_in_at', [$inicio, $fin])
            ->distinct())
            ->orderBy('name')
            ->get();
    }

    /**
     * Detalle de una marcación individual, para el modal que consume la
     * vista Lista (fetch → JSON, no navegación de página completa).
     */
    public function detalle(StaffAttendance $marcacion): JsonResponse
    {
        $marcacion->load(['user', 'gym']);

        // location_lat/lng son decimal() en BD y StaffAttendance no las
        // castea (ver modelo, línea 25-31: solo castea las fechas) — sin
        // (float) acá llegan como string al JSON y datos.lat.toFixed(8)
        // en el modal revienta con "toFixed is not a function".
        $lat = $marcacion->location_lat !== null ? (float) $marcacion->location_lat : null;
        $lng = $marcacion->location_lng !== null ? (float) $marcacion->location_lng : null;

        return response()->json([
            'id'              => $marcacion->id,
            'entrenador'      => $marcacion->user?->name ?? '—',
            'dni'             => $marcacion->user?->dni,
            'sede'            => $marcacion->gym?->name ?? '—',
            'turno'           => $marcacion->turno_legible,
            'metodo'          => $marcacion->method_legible,
            'entrada'         => $marcacion->clocked_in_at->format('d/m/Y H:i'),
            'salida'          => $marcacion->clocked_out_at?->format('d/m/Y H:i'),
            'duracion'        => $marcacion->clocked_out_at
                ? $marcacion->clocked_in_at->diffInMinutes($marcacion->clocked_out_at) . ' min'
                : null,
            'lat'             => $lat,
            'lng'             => $lng,
            'tiene_ubicacion' => $lat !== null && $lng !== null,
        ]);
    }
}
