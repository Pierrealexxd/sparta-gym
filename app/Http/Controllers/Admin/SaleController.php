<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Ventas" es el punto único para todo lo que entra por dinero: productos
 * de mostrador y membresías (matrícula y renovación) — antes vivían en
 * pantallas separadas que mostraban en el fondo la misma pregunta
 * ("¿qué se vendió?"). Se navega por pestañas (?tipo=) en vez de rutas
 * separadas.
 *
 * Las membresías se dan de alta desde MatriculaController/MembershipController
 * (que ya escriben en `sales`) — esta pantalla solo lista. Para venta de
 * mostrador también registra.
 *
 * Venta de mostrador: elige producto(s) y cantidad, descuenta stock vía un
 * StockMovement de salida (nunca escribe `products.stock` directo) y
 * congela nombre y precio en `sale_items`, igual que memberships congela
 * el precio del plan al vender (ver AGENTS.md). Al anular, el stock se
 * repone con un StockMovement de entrada — nunca se toca products.stock
 * a mano.
 */
class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $tipo  = $request->get('tipo') === 'membresia' ? 'membresia' : 'producto';
        $desde = $request->get('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->get('hasta', now()->toDateString());

        $ventas = Sale::with(['member', 'soldBy', 'items'])
            ->where('sale_type', $tipo)
            ->whereBetween(DB::raw('DATE(sold_at)'), [$desde, $hasta])
            ->latest('sold_at')->paginate(10)->onEachSide(1)->withQueryString();

        $totalRango = Sale::completadas()->where('sale_type', $tipo)
            ->whereBetween(DB::raw('DATE(sold_at)'), [$desde, $hasta])->sum('total');
        $conteoRango = Sale::completadas()->where('sale_type', $tipo)
            ->whereBetween(DB::raw('DATE(sold_at)'), [$desde, $hasta])->count();

        return view('admin.ventas.index', [
            'tipo'           => $tipo,
            'desde'          => $desde,
            'hasta'          => $hasta,
            'ventasHoy'      => Sale::completadas()->delDia()->sum('total'),
            'productos'      => Product::activos()->orderBy('name')->get(),
            'ventas'         => $ventas,
            'totalRango'     => $totalRango,
            'conteoRango'    => $conteoRango,
            'ticketPromedio' => $conteoRango > 0 ? round((float) $totalRango / $conteoRango, 2) : 0,
        ]);
    }

    /** Selector de cliente para las ventas: opcional, hasta 8 resultados. */
    public function buscarCliente(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q'));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $socios = Member::buscar($q)->take(8)->get();

        return response()->json($socios->map(fn (Member $m) => [
            'id'        => $m->id,
            'full_name' => $m->full_name,
            'code'      => $m->code,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'member_id'          => ['nullable', 'exists:members,id'],
            'method'             => ['required', 'in:efectivo,transferencia,yape,plin,tarjeta,otro'],
            'discount'           => ['nullable', 'numeric', 'min:0'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
        ]);

        try {
            $venta = DB::transaction(function () use ($datos, $request) {
                $subtotal = 0;
                $lineas = [];

                foreach ($datos['items'] as $item) {
                    // where('id', ...) en vez de find(): el global scope de
                    // BelongsToGym ya impide comprar un producto de otra sede.
                    $producto = Product::where('id', $item['product_id'])->lockForUpdate()->firstOrFail();

                    if ($producto->stock < $item['quantity']) {
                        throw ValidationException::withMessages([
                            'items' => "Stock insuficiente de \"{$producto->name}\": quedan {$producto->stock}.",
                        ]);
                    }

                    $total = round((float) $producto->sale_price * $item['quantity'], 2);
                    $subtotal += $total;

                    $lineas[] = [
                        'producto'   => $producto,
                        'cantidad'   => $item['quantity'],
                        'unit_price' => $producto->sale_price,
                        'total'      => $total,
                    ];
                }

                $descuento = $datos['discount'] ?? 0;

                $venta = Sale::create([
                    'member_id' => $datos['member_id'] ?? null,
                    'sale_type' => 'producto',
                    'sold_by'   => $request->user()->id,
                    'number'    => Sale::siguienteNumero(),
                    'subtotal'  => $subtotal,
                    'discount'  => $descuento,
                    'total'     => max($subtotal - $descuento, 0),
                    'method'    => $datos['method'],
                    'status'    => 'completada',
                    'sold_at'   => now(),
                ]);

                foreach ($lineas as $linea) {
                    $venta->items()->create([
                        'product_id'   => $linea['producto']->id,
                        'product_name' => $linea['producto']->name,
                        'quantity'     => $linea['cantidad'],
                        'unit_price'   => $linea['unit_price'],
                        'total'        => $linea['total'],
                    ]);

                    $nuevoStock = $linea['producto']->stock - $linea['cantidad'];

                    StockMovement::create([
                        'product_id'     => $linea['producto']->id,
                        'user_id'        => $request->user()->id,
                        'type'           => 'salida',
                        'quantity'       => $linea['cantidad'],
                        'stock_after'    => $nuevoStock,
                        'reason'         => 'Venta ' . $venta->number,
                        'reference_type' => Sale::class,
                        'reference_id'   => $venta->id,
                    ]);

                    $linea['producto']->update(['stock' => $nuevoStock]);
                }

                return $venta;
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.ventas.index')->with('exito', "Venta {$venta->number} registrada.");
    }

    /**
     * Anula una venta ya registrada. Si era de mostrador, repone el stock
     * de cada línea con un StockMovement de entrada — products.stock nunca
     * se toca directo (ver AGENTS.md).
     */
    public function anular(Sale $venta): RedirectResponse
    {
        if ($venta->status === 'anulada') {
            return back()->with('error', 'Esta venta ya estaba anulada.');
        }

        DB::transaction(function () use ($venta) {
            foreach ($venta->items as $linea) {
                $producto = Product::where('id', $linea->product_id)->lockForUpdate()->first();

                if (! $producto) {
                    continue;
                }

                $nuevoStock = $producto->stock + $linea->quantity;

                StockMovement::create([
                    'product_id'     => $producto->id,
                    'user_id'        => auth()->id(),
                    'type'           => 'entrada',
                    'quantity'       => $linea->quantity,
                    'stock_after'    => $nuevoStock,
                    'reason'         => 'Anulación venta ' . $venta->number,
                    'reference_type' => Sale::class,
                    'reference_id'   => $venta->id,
                ]);

                $producto->update(['stock' => $nuevoStock]);
            }

            $venta->update(['status' => 'anulada']);
        });

        return back()->with('exito', "Venta {$venta->number} anulada.");
    }
}
