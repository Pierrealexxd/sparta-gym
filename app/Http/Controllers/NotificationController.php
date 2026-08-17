<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints de la campanita unificada. Compartidos por los tres paneles
 * (sin middleware de rol): cualquier usuario autenticado recibe las suyas.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificaciones) {}

    public function total(Request $request): JsonResponse
    {
        return response()->json(['total' => $this->notificaciones->noLeidas($request->user())]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['items' => $this->notificaciones->lista($request->user())]);
    }

    public function nuevas(Request $request): JsonResponse
    {
        $desde = max(0, (int) $request->query('desde', 0));

        [$toasts, $ultimoId] = $this->notificaciones->nuevas($request->user(), $desde);

        return response()->json(['ultimo_id' => $ultimoId, 'toasts' => $toasts]);
    }

    public function marcarLeida(Request $request, int $id): JsonResponse
    {
        // Sin binding de modelo a propósito: el scope de sede filtraría las
        // de otra sucursal para el admin. La frontera real es el destinatario,
        // y esa la valida el servicio.
        $notificacion = Notification::sinFiltroDeGimnasio()->findOrFail($id);

        $this->notificaciones->marcarLeida($notificacion, $request->user());

        return response()->json(['ok' => true]);
    }

    public function marcarTodas(Request $request): JsonResponse
    {
        $total = $this->notificaciones->marcarTodasLeidas($request->user());

        return response()->json(['ok' => true, 'total' => $total]);
    }
}
