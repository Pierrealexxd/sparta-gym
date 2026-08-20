<?php

namespace App\Http\Controllers\Entrenador;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Versión reducida de Admin\SaleController para el entrenador: la misma
 * lógica de venta de mostrador (descuenta stock vía StockMovement, congela
 * precio en sale_items — nunca escribe products.stock directo), sin la
 * gestión de inventario (crear producto, ajustar stock a mano) que sigue
 * siendo solo de admin. El entrenador vende lo que hay, no lo repone.
 *
 * Dos pestañas: Productos y Registros (matrículas/inscripciones), lo que
 * el entrenador da de alta — comparten el mismo filtro de fecha, por
 * defecto hoy, pero se puede mirar hacia atrás sin salir de la pantalla.
 */
class VentaController extends Controller
{
    public function index(Request $request): View
    {
        // 1. FORZAR tipo: definición explícita y orden prioritario.
        //    - Si viene ?tipo=inscripcion -> muestra inscripciones
        //    - En TODO lo otro (incluido null, 'producto, o vacío) -> productos
        //    Esto evita que la URL o el navegador dejen el filtro "atrapado".
        $tipoSolicitado = $request->get('tipo');
        $tipo = $tipoSolicitado === 'inscripcion' ? 'inscripcion' : 'producto';

        // 2. Asegurar que $tipo nunca sea null (protección adicional)
        $tipo = $tipo ?? 'producto';

        $desde = $request->get('desde', now()->toDateString());
        $hasta = $request->get('hasta', now()->toDateString());

        $rango = [
            \Illuminate\Support\Carbon::parse($desde)->startOfDay(),
            \Illuminate\Support\Carbon::parse($hasta)->endOfDay(),
        ];

        $datos = [
            'tipo'      => $tipo,
            'desde'     => $desde,
            'hasta'     => $hasta,
            // Siempre disponible: el botón "Registrar venta" (pestaña
            // Productos) vive en @section('acciones'), fuera del @if de
            // cada pestaña, así que su modal necesita esto sin importar
            // cuál esté activa.
            'productos' => Product::activos()->orderBy('name')->get(),
        ];

        if ($tipo === 'inscripcion') {
            $inscripciones = Membership::with('member')
                ->where('created_by', $request->user()->id)
                ->whereBetween('created_at', $rango)
                ->latest('created_at')
                ->paginate(10)->onEachSide(1)->withQueryString();

            $datos['inscripciones'] = $inscripciones;

            // Indicador del módulo (antes vivía en el "Resumen" aparte, que
            // se dio de baja): del rango filtrado, no fijo al mes — es el
            // mismo filtro que ya usa esta pantalla. Se agrega aparte: el
            // paginador ya no trae la colección completa.
            $datos['kpis'] = [
                'cantidad' => $inscripciones->total(),
            ];
        } else {
            $ventas = Sale::with('items')
                ->where('sold_by', $request->user()->id)
                ->where('sale_type', 'producto')
                ->completadas()
                ->whereBetween('sold_at', $rango)
                ->latest('sold_at')
                ->paginate(10)->onEachSide(1)->withQueryString();

            $datos['ventas'] = $ventas;
            $datos['kpis'] = [
                'cantidad' => $ventas->total(),
                'total'    => (float) Sale::where('sold_by', $request->user()->id)
                    ->where('sale_type', 'producto')
                    ->completadas()
                    ->whereBetween('sold_at', $rango)
                    ->sum('total'),
            ];

            // "¿Cómo van mis ventas?" — dentro del MISMO rango que ya filtra
            // esta pantalla (no uno fijo): si el usuario mira "este mes", la
            // gráfica muestra este mes. Solo lo que él vendió (sold_by).
            $datos['graficoVentas'] = $this->ventasPorDia($request->user()->id, $rango[0], $rango[1]);
        }

        return view('entrenador.ventas.index', $datos);
    }

    /** Serie diaria de ventas propias dentro de un rango, con los días sin venta en cero. */
    private function ventasPorDia(int $userId, \Illuminate\Support\Carbon $desde, \Illuminate\Support\Carbon $hasta): array
    {
        $filas = Sale::where('sold_by', $userId)
            ->where('sale_type', 'producto')
            ->completadas()
            ->whereBetween('sold_at', [$desde, $hasta])
            ->selectRaw('DATE(sold_at) as dia, SUM(total) as total')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $etiquetas = $datos = [];
        $cursor = $desde->copy()->startOfDay();
        $fin = $hasta->copy()->startOfDay();

        while ($cursor->lte($fin)) {
            $etiquetas[] = $cursor->translatedFormat('d M');
            $datos[] = (float) ($filas[$cursor->toDateString()] ?? 0);
            $cursor->addDay();
        }

        return [
            'labels' => $etiquetas,
            'datasets' => [['label' => 'Ventas (S/)', 'data' => $datos, 'token' => '--sangre']],
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'member_id'          => ['nullable', 'exists:members,id'],
            'method'             => ['required', 'in:efectivo,transferencia,yape,plin,tarjeta,otro'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
        ]);

        try {
            $venta = DB::transaction(function () use ($datos, $request) {
                $subtotal = 0;
                $lineas = [];

                foreach ($datos['items'] as $item) {
                    $producto = Product::where('id', $item['product_id'])->lockForUpdate()->firstOrFail();

                    if ($producto->stock < $item['quantity']) {
                        throw ValidationException::withMessages([
                            'items' => "Stock insuficiente de \"{$producto->name}\": quedan {$producto->stock}.",
                        ]);
                    }

                    $total = round((float) $producto->sale_price * $item['quantity'], 2);
                    $subtotal += $total;

                    $lineas[] = [
                        'producto' => $producto,
                        'cantidad' => $item['quantity'],
                        'total'    => $total,
                    ];
                }

                $venta = Sale::create([
                    'member_id' => $datos['member_id'] ?? null,
                    'sale_type' => 'producto',
                    'sold_by'   => $request->user()->id,
                    'number'    => Sale::siguienteNumero(),
                    'subtotal'  => $subtotal,
                    'discount'  => 0,
                    'total'     => $subtotal,
                    'method'    => $datos['method'],
                    'status'    => 'completada',
                    'sold_at'   => now(),
                ]);

                foreach ($lineas as $linea) {
                    $venta->items()->create([
                        'product_id'   => $linea['producto']->id,
                        'product_name' => $linea['producto']->name,
                        'quantity'     => $linea['cantidad'],
                        'unit_price'   => $linea['producto']->sale_price,
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

        return redirect()->route('entrenador.ventas.index')->with('exito', "Venta {$venta->number} registrada.");
    }

    public function edit(Sale $venta): View
    {
        $this->authorize('ventas.editar', $venta);
        return view('entrenador.ventas.edit', compact('venta'));
    }

    public function update(Request $request, Sale $venta): RedirectResponse
    {
        $this->authorize('ventas.editar', $venta);

        $datos = $request->validate([
            'concept'      => ['sometimes', 'string', 'max:120'],
            'method'       => ['sometimes', 'required', 'in:efectivo,transferencia,yape,plin,tarjeta,otro'],
            'status'       => ['sometimes', 'string'],
        ]);

        $venta->update($datos);

        // Notificar al admin
        app(NotificationService::class)->dispararA(
            app(User::where('role_id', Role::where('slug', 'admin')->value('id'))->first()),
            'venta.editada',
            'Venta editada',
            "El entrenador {$request->user()->name} editó la venta {$venta->number}",
            'ventas',
            'media',
            $venta->id,
            route('admin.ventas.show', $venta),
        );

        return redirect()
            ->route('entrenador.ventas.index', ['tipo' => 'producto'])
            ->with('exito', "Venta {$venta->number} actualizada.");
    }

    /**
     * Anula una venta propia del entrenador. Repone el stock de cada línea
     * (mismo patrón que Admin\SaleController::anular) y notifica al admin.
     */
    public function anular(Sale $venta): RedirectResponse
    {
        if ($venta->sold_by !== auth()->id()) {
            abort(403);
        }

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

        app(NotificationService::class)->dispararA(
            User::where('role_id', Role::where('slug', 'admin')->value('id'))->first(),
            'venta.anulada',
            'Venta anulada',
            "El entrenador " . auth()->user()->name . " anuló la venta {$venta->number}",
            'ventas',
            'media',
            $venta->id,
            route('admin.ventas.index'),
        );

        return redirect()->route('entrenador.ventas.index', ['tipo' => 'producto'])
            ->with('exito', "Venta {$venta->number} anulada.");
    }
}
