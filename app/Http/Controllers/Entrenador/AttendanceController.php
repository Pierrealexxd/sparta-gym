<?php

namespace App\Http\Controllers\Entrenador;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\GymQrCode;
use App\Models\Member;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Services\AsistenciaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Panel de asistencia del entrenador, con dos caras:
 *
 *  - "Mi marcación": su asistencia LABORAL (StaffAttendance), como hasta
 *    ahora — elegir turno, marcar entrada/salida, pedir corrección.
 *    Ni editar ni borrar un registro ya guardado se aplican directo — las
 *    dos quedan pendientes hasta que el admin las aprueba o rechaza.
 *
 *  - "Mis clientes": el calendario de las asistencias de clientes que él
 *    mismo registró (Attendance con registered_by = él). Registrar aquí una
 *    entrada de cliente NO es el torno de recepción: lo hace desde el
 *    buscador de la vista, con method=manual, y solo sobre socios que tiene
 *    asignados.
 *
 * Regla del panel: solo puede tocar lo suyo — sus marcaciones y los
 * registros que él registró.
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

        $porDia = Attendance::with('member')
            ->registradasPor($request->user()->id)
            ->whereBetween('checked_in_at', [$inicio, $fin])
            ->get()
            ->groupBy(fn (Attendance $a) => $a->checked_in_at->toDateString());

        $offset = $inicio->dayOfWeek === 0 ? 6 : $inicio->dayOfWeek - 1;

        return view('entrenador.asistencia.calendario', [
            'mes'        => $mes,
            'anio'       => $anio,
            'nombreMes'  => $inicio->translatedFormat('F Y'),
            'diasDelMes' => $inicio->daysInMonth,
            'offset'     => $offset,
            'anterior'   => $inicio->copy()->subMonth(),
            'siguiente'  => $inicio->copy()->addMonth(),
            'porDia'     => $porDia,
            'celdas'     => $porDia->map->count(),
        ]);
    }

    public function miMarcacion(Request $request): View
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
        $turno  = $request->get('turno');

        $marcaciones = StaffAttendance::with(['editRequests' => fn ($q) => $q->pendientes()])
            ->where('user_id', $request->user()->id)
            ->whereBetween('clocked_in_at', [$inicio, $fin])
            ->when($turno, fn ($q, $t) => $q->where('turno', $t))
            ->latest('clocked_in_at')
            ->get();

        // La vista de lista pagina aparte: el calendario y los KPIs siguen
        // usando $marcaciones completo del mes.
        $marcacionesPag = StaffAttendance::with(['editRequests' => fn ($q) => $q->pendientes()])
            ->where('user_id', $request->user()->id)
            ->whereBetween('clocked_in_at', [$inicio, $fin])
            ->when($turno, fn ($q, $t) => $q->where('turno', $t))
            ->latest('clocked_in_at')
            ->paginate(10)
            ->withQueryString();

        $porDia = $marcaciones->groupBy(fn (StaffAttendance $s) => $s->clocked_in_at->toDateString());
        $offset = $inicio->dayOfWeek === 0 ? 6 : $inicio->dayOfWeek - 1;

        // Indicadores del módulo, del mes que se está mirando (antes vivían
        // en el "Resumen" aparte, que se dio de baja) — a partir de la misma
        // colección de arriba, sin consultas extra.
        $kpis = [
            'diasTrabajados'   => $porDia->count(),
            'horasTrabajadas'  => round(
                $marcaciones->sum(fn (StaffAttendance $s) => $s->clocked_out_at
                    ? $s->clocked_in_at->diffInMinutes($s->clocked_out_at) / 60
                    : 0),
                1
            ),
        ];

        // "En turno ahora" no depende del mes que se esté mirando — se busca
        // aparte para que cambiar de mes no le esconda el botón de salida.
        // Y solo cuenta la abierta de HOY: una abierta de otro día se trata
        // como "no estoy en turno" (la regla del servicio, ver marcarStaff).
        $abierta = StaffAttendance::where('user_id', $request->user()->id)
            ->dentro()
            ->whereBetween('clocked_in_at', [now()->startOfDay(), now()->endOfDay()])
            ->first();

        return view('entrenador.asistencia.mi-marcacion', [
            'marcaciones'    => $marcaciones,
            'marcacionesPag' => $marcacionesPag,
            'abierta'        => $abierta,
            'mes'         => $mes,
            'anio'        => $anio,
            'nombreMes'   => $inicio->translatedFormat('F Y'),
            'diasDelMes'  => $inicio->daysInMonth,
            'offset'      => $offset,
            'anterior'    => $inicio->copy()->subMonth(),
            'siguiente'   => $inicio->copy()->addMonth(),
            'porDia'      => $porDia,
            'celdas'      => $porDia->map->count(),
            'turno'       => $turno,
            'filtros'     => array_filter(['turno' => $turno]),
            'kpis'        => $kpis,
            'graficoMarcaciones' => $this->marcacionesPorSemana($request->user()->id, 12),
        ]);
    }

    /**
     * "¿Cuán constante soy yo?" — sus propias marcaciones (user_id = él),
     * por semana, fijo a las últimas 12 sin importar el mes que se esté
     * mirando arriba. Una sola consulta agrupada por semana ISO.
     */
    private function marcacionesPorSemana(int $userId, int $semanas): array
    {
        $desde = now()->startOfWeek()->subWeeks($semanas - 1);

        $filas = StaffAttendance::where('user_id', $userId)
            ->where('clocked_in_at', '>=', $desde)
            ->selectRaw('YEARWEEK(clocked_in_at, 3) as semana, COUNT(*) as total')
            ->groupBy('semana')
            ->pluck('total', 'semana');

        $etiquetas = $datos = [];

        for ($i = $semanas - 1; $i >= 0; $i--) {
            $inicioSemana = now()->startOfWeek()->subWeeks($i);
            $clave = (int) $inicioSemana->format('oW');
            $etiquetas[] = $inicioSemana->translatedFormat('d M');
            $datos[] = (int) ($filas[$clave] ?? 0);
        }

        return [
            'labels' => $etiquetas,
            'datasets' => [['label' => 'Marcaciones', 'data' => $datos, 'token' => '--brasa']],
        ];
    }

    public function marcar(Request $request): RedirectResponse
    {
        try {
            $resultado = $this->asistencias->marcarStaff(
                $request->user(),
                $request->input('turno', 'manana'),
            );
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([
                'turno' => $e->errors()['asistencia'][0] ?? 'No se pudo marcar la asistencia.',
            ]);
        }

        $mensaje = $resultado['tipo'] === 'salida'
            ? 'Salida marcada. Buen trabajo.'
            : 'Entrada marcada. Buen trabajo.';

        return back()->with('exito', $mensaje);
    }

    /** Estado del fichaje de hoy, para que el modal de escaneo sepa qué pedir. */
    public function estado(Request $request): JsonResponse
    {
        $abierta = StaffAttendance::where('user_id', $request->user()->id)
            ->dentro()
            ->whereBetween('clocked_in_at', [now()->startOfDay(), now()->endOfDay()])
            ->first();

        return response()->json([
            'abierta'     => (bool) $abierta,
            'horaEntrada' => $abierta?->clocked_in_at->format('H:i'),
            'turno'       => $abierta?->turno,
        ]);
    }

    /**
     * Marcación por QR: el token es una capability que identifica la sede.
     * Nunca viaja el gym_id; la sucursal se resuelve aquí y se fuerza en el
     * registro, y los errores no distinguen entre token inexistente, revocado
     * o sede apagada para no filtrar qué sedes existen.
     */
    public function marcarPorQr(Request $request): JsonResponse
    {
        $token = $request->string('token')->trim()->toString();

        if (! Str::isUuid($token)) {
            throw ValidationException::withMessages(['qr' => 'Código QR no válido.']);
        }

        // lat/lng son obligatorios al marcar por QR: sin ubicación GPS la
        // marcación no se registra (ver escaneo-qr.js, obtenerUbicacion).
        $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        // Sin filtro de sede a propósito: el QR define la sucursal, la sesión no.
        $qr = GymQrCode::query()
            ->where('token', $token)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->first();

        if (! $qr) {
            throw ValidationException::withMessages(['qr' => 'Código QR no válido.']);
        }

        if (! $qr->gym->is_active) {
            throw ValidationException::withMessages(['qr' => 'La sucursal está desactivada.']);
        }

        try {
            $resultado = $this->asistencias->marcarStaff(
                $request->user(),
                $request->input('turno', 'manana'),
                $qr->gym_id,
                true,
                (float) $request->input('lat'),
                (float) $request->input('lng'),
            );
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([
                'qr' => $e->errors()['asistencia'][0] ?? 'No se pudo marcar la asistencia.',
            ]);
        }

        $marcacion = $resultado['marcacion'];

        return response()->json([
            'ok'     => true,
            'tipo'   => $resultado['tipo'],
            'hora'   => $marcacion->clocked_out_at?->format('H:i') ?? $marcacion->clocked_in_at->format('H:i'),
            'sede'   => $qr->gym->name,
            'turno'  => $marcacion->turno,
            'nombre' => $request->user()->name,
            'dni'    => $request->user()->dni,
        ]);
    }

    /**
     * Eliminar una marcación propia ya no se aplica directo: queda pendiente
     * de aprobación del admin, igual que editar (antes se borraba al toque
     * porque "es obvio que pasó, el registro ya no está" — se decidió que
     * igual conviene avisarle al admin).
     */
    public function borrar(Request $request, StaffAttendance $marcacion): RedirectResponse
    {
        abort_unless($marcacion->user_id === $request->user()->id, 403);

        try {
            $this->asistencias->solicitarEliminacion($marcacion, $request->user());
        } catch (ValidationException $e) {
            return back()->with('error', $e->errors()['asistencia'][0] ?? 'No se pudo enviar la solicitud.');
        }

        return back()->with('exito', 'Solicitud de eliminación enviada. Queda pendiente de aprobación.');
    }

    /** Editar una marcación propia: queda pendiente de aprobación. */
    public function solicitarEdicion(Request $request, StaffAttendance $marcacion): RedirectResponse
    {
        abort_unless($marcacion->user_id === $request->user()->id, 403);

        $this->asistencias->solicitarCorreccion($marcacion, $request->user(), $request->all());

        return back()->with('exito', 'Corrección enviada. Queda pendiente de aprobación.');
    }

    /**
     * Detalle de una marcación propia, para el modal que consume la vista
     * "Mi marcación" (fetch → JSON, no navegación de página completa).
     * Solo puede ver las suyas: aborta 403 si el registro no le pertenece.
     */
    public function detalle(StaffAttendance $marcacion): JsonResponse
    {
        abort_unless($marcacion->user_id === request()->user()->id, 403);

        $marcacion->load('gym');

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

    /** Registrar la entrada de un cliente que tengo asignado (method=manual). */
    public function registrarEntrada(Request $request): RedirectResponse
    {
        $request->validate(['member_id' => ['required', 'integer']]);

        $socio = Member::find($request->integer('member_id'));

        abort_unless($socio && $this->tieneAsignado($request->user(), $socio), 403, 'Ese socio no está asignado a tu cargo.');

        try {
            $this->asistencias->registrarEntrada($socio, $request->user(), 'manual');
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([
                'member_id' => $e->errors()['asistencia'][0] ?? 'No se pudo registrar la entrada.',
            ]);
        }

        return back()->with('exito', "Entrada de {$socio->full_name} registrada.");
    }

    public function marcarSalida(Request $request, Attendance $attendance): RedirectResponse
    {
        abort_unless($attendance->registered_by === $request->user()->id, 403, 'Solo puedes cerrar asistencias que registraste.');

        $this->asistencias->marcarSalida($attendance);

        return back()->with('exito', 'Salida registrada.');
    }

    public function solicitarEdicionCliente(Request $request, Attendance $attendance): RedirectResponse
    {
        abort_unless($attendance->registered_by === $request->user()->id, 403);

        $this->asistencias->solicitarCorreccion($attendance, $request->user(), $request->all());

        return back()->with('exito', 'Corrección enviada. Queda pendiente de aprobación.');
    }

    /** Buscador AJAX del formulario "Registrar asistencia". */
    public function buscarClientes(Request $request): JsonResponse
    {
        $termino = trim($request->string('q'));

        $socios = $request->user()->trainer?->activeMembers()
            ->when($termino !== '', fn ($q) => $q->buscar($termino))
            ->limit(8)
            ->get(['id', 'first_name', 'last_name', 'code', 'photo_path']) ?? collect();

        // Mismo contrato que los otros buscadores de cliente (id/full_name/
        // code) para poder usar el componente x-buscador-cliente acá.
        return response()->json($socios->map(fn (Member $m) => [
            'id'        => $m->id,
            'full_name' => $m->full_name,
            'code'      => $m->code,
            'foto'      => $m->photo_path ? asset('storage/' . $m->photo_path) : null,
        ]));
    }

    private function tieneAsignado(User $user, Member $member): bool
    {
        return $user->trainer?->activeMembers()->whereKey($member->id)->exists() ?? false;
    }
}
