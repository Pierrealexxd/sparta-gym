<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Services\AsistenciaService;
use App\Support\GymContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * El único módulo "Asistencia" del admin. `calendario()` es la vista
 * principal: el mes con las asistencias de clientes, filtrable por
 * entrenador (quién registró) y por cliente. `entrar()` acepta código,
 * número de documento o el token del QR indistintamente — en el mostrador
 * nadie debería tener que saber por adelantado cuál de los tres va a usar
 * el socio; vive ahora como modal de la propia vista de calendario, no
 * como pantalla aparte.
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

        $inicio = Carbon::create($anio, $mes, 1)->startOfMonth();
        $fin    = $inicio->copy()->endOfMonth();
        $modoTodas = GymContext::id() === null;

        $entrenador = $request->integer('entrenador') ?: null;
        $cliente    = $request->string('cliente')->trim()->toString() ?: null;

        $porDia = Attendance::with(['member', 'registeredBy', 'gym'])
            ->when(GymContext::id(), fn ($q, $gymId) => $q->where('gym_id', $gymId))
            ->whereBetween('checked_in_at', [$inicio, $fin])
            ->when($entrenador, fn ($q, $id) => $q->where('registered_by', $id))
            ->when($cliente, fn ($q, $t) => $q->whereHas('member', fn ($m) => $m->buscar($t)))
            ->get()
            ->groupBy(fn (Attendance $a) => $a->checked_in_at->toDateString());

        // Quiénes registraron en el mes, para el filtro por entrenador. El
        // subquery hereda el scope de sede de Attendance; solo admins con
        // 'todas' ven consolidado (el selector de la cabecera lo controla).
        $entrenadores = User::whereIn('id', Attendance::select('registered_by')
            ->whereBetween('checked_in_at', [$inicio, $fin])
            ->whereNotNull('registered_by')
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
            'cliente'      => $cliente,
            'filtros'      => array_filter(
                ['entrenador' => $entrenador, 'cliente' => $cliente],
                fn ($v) => ! blank($v)
            ),
            'dentro'       => Attendance::deHoy()->dentro()->count(),
        ]);
    }

    public function entrar(Request $request): RedirectResponse
    {
        $request->validate(['busqueda' => ['required', 'string', 'max:60']]);

        $texto = trim($request->string('busqueda'));

        $socio = Member::activos()
            ->where('code', $texto)
            ->orWhere('qr_token', $texto)
            ->orWhere('document', $texto)
            ->first();

        if (! $socio) {
            throw ValidationException::withMessages(['busqueda' => 'No se encontró ningún socio activo con ese dato.']);
        }

        if (! $socio->is_up_to_date) {
            throw ValidationException::withMessages(['busqueda' => "{$socio->full_name} no tiene una membresía vigente."]);
        }

        // Evita el doble marcaje si alguien vuelve a pasar el QR sin haber
        // marcado salida.
        $abierta = $socio->attendances()->dentro()->first();
        if ($abierta) {
            $this->asistencias->marcarSalida($abierta);

            return back()->with('exito', "Salida registrada para {$socio->full_name}.");
        }

        try {
            $this->asistencias->registrarEntrada($socio, $request->user(), strlen($texto) === 36 ? 'qr' : 'codigo');
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['busqueda' => $e->errors()['asistencia'][0] ?? 'No se pudo registrar la entrada.']);
        }

        return back()->with('exito', "Entrada registrada para {$socio->full_name}.");
    }

    public function salir(Attendance $attendance): RedirectResponse
    {
        $this->asistencias->marcarSalida($attendance);

        return back()->with('exito', 'Salida registrada.');
    }

    /**
     * Pestaña "Personal": calendario de las marcaciones LABORALES del staff
     * (staff_attendances) — la contracara del calendario de clientes de
     * calendario(). Es aquí donde el admin ve las marcaciones por QR, con su
     * método (Manual/QR) y la sede en modo "todas".
     */
    public function personal(Request $request): View
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

        return view('admin.asistencia.personal', [
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
