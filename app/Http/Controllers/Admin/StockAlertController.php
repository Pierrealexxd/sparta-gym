<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Datos de alertas de stock para la campanita del panel y el contador del
 * sidebar. El admin las ve de todas sus sedes (mismo criterio que mensajería
 * y solicitudes de asistencia); recepción solo las de la sede activa.
 */
class StockAlertController extends Controller
{
    public function pendientesJson(Request $request): JsonResponse
    {
        $global = $request->user()->esAdmin();

        $alertas = StockAlert::with('product')
            ->when($global, fn ($q) => $q->sinFiltroDeGimnasio())
            ->latest('created_at')
            ->get();

        return response()->json([
            'total' => $alertas->count(),
            'items' => $alertas->map(fn (StockAlert $alerta) => [
                'id'        => $alerta->product_id,
                'nombre'    => 'Inventario',
                'ultimo'    => $alerta->type === 'agotado'
                    ? "{$alerta->product->name}: agotado"
                    : "{$alerta->product->name}: por agotarse (quedan {$alerta->product->stock} · min {$alerta->product->min_stock})",
                'no_leidas' => 1,
                'iniciales' => 'S',
                'avatar'    => null,
                'sede'      => $global && $alerta->gym ? $alerta->gym->name : null,
            ]),
        ]);
    }
}