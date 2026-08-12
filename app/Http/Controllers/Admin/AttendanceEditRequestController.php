<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEditRequest;
use App\Services\AsistenciaService;
use App\Support\GymContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cola de aprobación para las correcciones de asistencia — tanto de la
 * marcación LABORAL del staff como de una asistencia de cliente registrada
 * por un entrenador. Aprobar aplica el cambio al registro real; rechazar
 * solo archiva la solicitud. El registro nunca se edita fuera de este
 * flujo (ver AsistenciaService y el propio modelo AttendanceEditRequest).
 */
class AttendanceEditRequestController extends Controller
{
    public function __construct(private readonly AsistenciaService $asistencias)
    {
    }

    public function index(Request $request): View
    {
        $estado = $request->get('estado') === 'historial' ? 'historial' : 'pendientes';

        $solicitudes = AttendanceEditRequest::with(['attendance.member', 'staffAttendance.user', 'requestedBy', 'gym'])
            ->when(GymContext::id(), fn ($q, $gymId) => $q->where('gym_id', $gymId))
            ->{$estado}()
            ->latest()
            ->get();

        return view('admin.asistencia.solicitudes', [
            'solicitudes' => $solicitudes,
            'estado'      => $estado,
            'modoTodas'   => GymContext::id() === null,
        ]);
    }

    /**
     * Para la campanita: cuántas y cuáles, sin abrir la página completa.
     *
     * El admin gestiona varias sedes a la vez, así que ve estas solicitudes
     * sin importar cuál tenga activa en el selector de arriba — mismo
     * criterio que la mensajería (ver MensajeController::listaConversaciones).
     * El `when(GymContext::id(), ...)` de antes era redundante: el scope
     * de sede de AttendanceEditRequest (BelongsToGym) ya lo aplicaba solo;
     * para saltárselo de verdad hace falta sinFiltroDeGimnasio().
     */
    public function pendientesJson(Request $request): JsonResponse
    {
        $global = $request->user()->esAdmin();

        $solicitudes = AttendanceEditRequest::with(['attendance.member', 'staffAttendance.user', 'requestedBy', 'gym'])
            ->when($global, fn ($q) => $q->sinFiltroDeGimnasio())
            ->pendientes()
            ->latest()
            ->get();

        return response()->json([
            'total' => $solicitudes->count(),
            'items' => $solicitudes->map(fn (AttendanceEditRequest $s) => [
                'id'     => $s->id,
                'nombre' => 'Corrección de asistencia',
                'detalle' => ($s->requestedBy?->name ?? 'Alguien del staff')
                    . ' pide corregir '
                    . ($s->tipo === 'cliente'
                        ? 'la entrada de ' . ($s->attendance?->member?->full_name ?? 'un cliente') . ' del '
                          . ($s->attendance?->checked_in_at?->translatedFormat('d M') ?? '—')
                        : 'su entrada del ' . ($s->staffAttendance?->clocked_in_at?->translatedFormat('d M') ?? '—'))
                    . ($global && $s->gym ? ' — ' . $s->gym->name : ''),
            ]),
        ]);
    }

    public function aprobar(AttendanceEditRequest $solicitud): RedirectResponse
    {
        $this->asistencias->aprobar($solicitud, auth()->user());

        return back()->with('exito', 'Corrección aplicada.');
    }

    public function rechazar(AttendanceEditRequest $solicitud): RedirectResponse
    {
        $this->asistencias->rechazar($solicitud, auth()->user());

        return back()->with('exito', 'Solicitud rechazada.');
    }
}
