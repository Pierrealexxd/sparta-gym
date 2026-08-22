<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SaleExport;
use App\Http\Controllers\Controller;
use App\Imports\SaleImport;
use App\Imports\SaleRecordImport;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Support\GymContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        // "Asistieron hoy" = registros del día actual (sold_at), no check-in
        // físico: el filtro manda sobre el rango y lo colapsa a hoy.
        $asistieronHoy = $request->get('asistencia') === 'hoy';

        if ($asistieronHoy) {
            $desde = $hasta = now()->toDateString();
        }

        // En la pestaña Registros el filtro además aísla las ventas del pase
        // diario: transacciones de hoy cuyo concepto es el plan de un día de
        // la sede (duration_days = 1; el nombre no importa, cada sede pone
        // el suyo). En Productos no hay plan que vender: botón oculto.
        $soloPlanDiario = fn ($q) => $q->whereHas(
            'membership.plan',
            fn ($p) => $p->where('duration_days', 1),
        );
        $aplicaPlanDiario = $asistieronHoy && $tipo === 'membresia';

        // Pestaña Productos: ventas CON líneas de producto — mostrador puro
        // o matrícula mixta (la matrícula con agua/suplementos viaja en la
        // misma venta). El filtro es "tiene items", no sale_type: así la
        // misma venta aparece con su total completo en Registros y con solo
        // sus productos acá. Membresías sin consumo no tienen items y no
        // aparecen.
        if ($tipo === 'producto') {
            $ventas = Sale::with(['member', 'soldBy', 'items'])
                ->withSum('items as bruto_items', 'total')
                ->whereHas('items')
                ->whereBetween(DB::raw('DATE(sold_at)'), [$desde, $hasta])
                ->latest('sold_at')->paginate(10)->onEachSide(1)->withQueryString();

            // Bruto de líneas: lo que sumó el producto vendido, al margen del
            // descuento global del ticket (que puede pertenecer a la
            // membresía de una venta mixta).
            $conItems = Sale::completadas()->whereHas('items')
                ->whereBetween(DB::raw('DATE(sold_at)'), [$desde, $hasta]);

            $totalRango = (clone $conItems)
                ->join('sale_items', 'sale_items.sale_id', '=', 'sales.id')
                ->sum('sale_items.total');
            $conteoRango = $conItems->count();
        } else {
            $ventas = Sale::with(['member', 'soldBy', 'items'])
                ->whereIn('sale_type', $this->tiposDePestana($tipo))
                ->whereBetween(DB::raw('DATE(sold_at)'), [$desde, $hasta])
                ->when($aplicaPlanDiario, $soloPlanDiario)
                ->latest('sold_at')->paginate(10)->onEachSide(1)->withQueryString();

            $totalRango = Sale::completadas()->whereIn('sale_type', $this->tiposDePestana($tipo))
                ->whereBetween(DB::raw('DATE(sold_at)'), [$desde, $hasta])
                ->when($aplicaPlanDiario, $soloPlanDiario)
                ->sum('total');
            $conteoRango = Sale::completadas()->whereIn('sale_type', $this->tiposDePestana($tipo))
                ->whereBetween(DB::raw('DATE(sold_at)'), [$desde, $hasta])
                ->when($aplicaPlanDiario, $soloPlanDiario)
                ->count();
        }

        return view('admin.ventas.index', [
            'tipo'           => $tipo,
            'desde'          => $desde,
            'hasta'          => $hasta,
            'asistieronHoy'  => $asistieronHoy,
            'ventasHoy'      => Sale::completadas()->delDia()->sum('total'),
            'productos'      => Product::activos()->orderBy('name')->get(),
            // Para el modal de matrícula en la pestaña Registros: mismo
            // criterio que MemberController::index (activos, por precio).
            // Solo se cargan si el usuario puede matricular — en la otra
            // pestaña o sin permiso no hay modal que alimentar.
            'planes'         => auth()->user()->tienePermiso('clientes.crear')
                ? Plan::activos()->orderBy('price')->get()
                : collect(),
            'ventas'         => $ventas,
            'totalRango'     => $totalRango,
            'conteoRango'    => $conteoRango,
            'ticketPromedio' => $conteoRango > 0 ? round((float) $totalRango / $conteoRango, 2) : 0,
            // Nombre real del pase diario de la sede, para el tooltip del
            // botón (null si la sede no configuró ninguno).
            'planDiario'     => Plan::where('duration_days', 1)->first(),
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

    /**
     * Detalle de una venta individual para el modal (fetch → JSON).
     */
    public function detalle(Sale $venta): JsonResponse
    {
        $venta->load(['member', 'soldBy', 'items', 'membership.plan']);

        return response()->json([
            'id'            => $venta->id,
            'number'        => $venta->number,
            'tipo'          => $venta->sale_type,
            'fecha'         => $venta->sold_at->format('d/m/Y H:i'),
            'cliente'       => $venta->member ? [
                'id'        => $venta->member->id,
                'full_name' => $venta->member->full_name,
                'code'      => $venta->member->code,
            ] : null,
            'vendido_por'   => $venta->soldBy?->name ?? '—',
            'metodo'        => $venta->metodo_legible,
            // Valor crudo para el select del editor de venta del día.
            'method'        => $venta->method,
            'concepto'      => $venta->concept,
            'subtotal'      => (float) $venta->subtotal,
            'descuento'     => (float) $venta->discount,
            'total'         => (float) $venta->total,
            'estado'        => $venta->status,
            'editable'      => $venta->status === 'completada' && $venta->sold_at->isToday(),
            'notas'         => $venta->notes,
            'items'         => $venta->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'producto'   => $i->product_name,
                'cantidad'   => $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'total'      => (float) $i->total,
            ]),
            'membresia'     => $venta->membership ? [
                'plan'      => $venta->membership->plan?->name ?? $venta->membership->plan_name,
                'inicio'    => $venta->membership->starts_at?->format('d/m/Y'),
                'fin'       => $venta->membership->ends_at?->format('d/m/Y'),
                'dias'      => $venta->membership->dias_restantes,
            ] : null,
        ]);
    }

    /**
     * Exporta el listado ya filtrado (mismo $tipo/$desde/$hasta que la
     * pantalla) a Excel o PDF. No usamos GymContext aquí a mano: Sale trae
     * BelongsToGym, así que el global scope ya filtra por sede igual que
     * en index().
     */
    public function exportar(Request $request): BinaryFileResponse|StreamedResponse|Response
    {
        $tipo  = $request->get('tipo') === 'membresia' ? 'membresia' : 'producto';
        $desde = $request->get('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->get('hasta', now()->toDateString());
        $formato = $request->get('formato') === 'pdf' ? 'pdf' : 'excel';

        // Mismo criterio que index(): el filtro de hoy colapsa el rango.
        if ($request->get('asistencia') === 'hoy') {
            $desde = $hasta = now()->toDateString();
        }

        // En Registros, el filtro también aísla las ventas del pase diario,
        // igual que en pantalla (ver index()).
        $ventas = Sale::with(['items', 'member', 'soldBy'])
            ->whereIn('sale_type', $this->tiposDePestana($tipo))
            ->whereBetween(DB::raw('DATE(sold_at)'), [$desde, $hasta])
            ->when(
                $request->get('asistencia') === 'hoy' && $tipo === 'membresia',
                fn ($q) => $q->whereHas('membership.plan', fn ($p) => $p->where('duration_days', 1)),
            )
            ->orderByDesc('sold_at')
            ->get();

        if ($formato === 'pdf') {
            $pdf = Pdf::loadView('admin.ventas.pdf', [
                'ventas' => $ventas,
                'tipo'   => $tipo,
                'desde'  => $desde,
                'hasta'  => $hasta,
                'gym'    => GymContext::current(),
            ])->setPaper('a4', 'landscape');

            return $pdf->download("ventas-{$tipo}-{$desde}-{$hasta}.pdf");
        }

        return Excel::download(new SaleExport($ventas, $tipo), "ventas-{$tipo}-{$desde}-{$hasta}.xlsx");
    }

    /**
     * Importa desde un Excel/CSV, y el contexto decide qué se crea:
     *
     * - Desde la pestaña "Productos" (tipo=producto) → SaleImport: ventas
     *   de mostrador (sale_type='producto') que descuentan stock. Solo
     *   producto a propósito — una membresía siempre nace de
     *   MatriculaService (matrícula o renovación), nunca de un archivo.
     * - Desde la pestaña "Registros" (tipo=membresia) → SaleRecordImport:
     *   registros genéricos (sale_type='servicio'/'otro') que NUNCA tocan
     *   productos ni stock. Un archivo de registros subido aquí no puede
     *   terminar mezclado con Productos.
     */
    public function importar(Request $request): RedirectResponse
    {
        $tipo = $request->get('tipo') === 'membresia' ? 'membresia' : 'producto';

        // Se valida ANTES de leer una sola fila: tipo, tamaño y que el
        // archivo exista. WithValidation en el import valida cada fila
        // después de esto.
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        try {
            Excel::import(
                $tipo === 'producto'
                    ? new SaleImport(GymContext::id(), $request->user()->id)
                    : new SaleRecordImport(GymContext::id(), $request->user()->id),
                $request->file('archivo')
            );
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $mensajes = collect($e->failures())
                ->map(fn ($f) => "Fila {$f->row()}: " . implode(' ', $f->errors()))
                ->take(5)
                ->join(' | ');

            return back()->with('error', "No se importó nada. Revisa el archivo: {$mensajes}");
        }

        $exito = $tipo === 'producto'
            ? 'Ventas importadas correctamente.'
            : 'Registros importados correctamente.';

        return back()->with('exito', $exito);
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

    /**
     * Edita una venta del MISMO DÍA: agrega o quita productos, cambia
     * cantidades y ajusta método/descuento. La caja cuadra por fecha real
     * de cobro (AGENTS.md), así que tocar una venta de otro día
     * reescribiría el histórico ya reportado — fuera de hoy solo queda
     * anular y volver a registrar.
     *
     * El stock se mueve por DELTA contra lo que la venta ya descontó:
     * subir 1→3 Gatorades emite una salida por 2; bajar 3→1 una entrada
     * por 2. Nunca products.stock directo (ver AGENTS.md).
     */
    public function update(Request $request, Sale $venta): RedirectResponse|JsonResponse
    {
        if ($venta->status !== 'completada') {
            return back()->with('error', "La venta {$venta->number} no está completada: anúlala en vez de editarla.");
        }

        if (! $venta->sold_at->isToday()) {
            return back()->with('error', "La venta {$venta->number} es de otro día. Solo se editan ventas de hoy; para el pasado, anula y vuelve a registrar.");
        }

        $datos = $request->validate([
            'method'             => ['sometimes', 'required', 'in:efectivo,transferencia,yape,plin,tarjeta,otro'],
            'discount'           => ['nullable', 'numeric', 'min:0'],
            // quantity 0 = quitar la línea del ticket.
            'items'              => ['nullable', 'array'],
            'items.*.product_id' => ['required_with:items', 'integer'],
            'items.*.quantity'   => ['required_with:items', 'integer', 'min:0'],
        ]);

        try {
            DB::transaction(function () use ($datos, $venta, $request) {
                // Releer con candado: entre el clic y acá otra caja pudo
                // tocar el stock de los mismos productos.
                $venta = Sale::lockForUpdate()->findOrFail($venta->id);

                // Porción fija del ticket: el precio congelado del plan en
                // una matrícula mixta (subtotal original − líneas). En
                // mostrador puro da 0. Se lee ANTES de tocar las líneas.
                $porcionFija = round((float) $venta->subtotal - (float) $venta->items()->sum('total'), 2);

                $actuales = $venta->items->keyBy('product_id');

                if (array_key_exists('items', $datos)) {
                    foreach ($datos['items'] as $entrada) {
                        $productoId = (int) $entrada['product_id'];
                        $deseada    = (int) $entrada['quantity'];
                        $actual     = $actuales->get($productoId)?->quantity ?? 0;

                        if ($deseada === $actual) {
                            continue;
                        }

                        // El scope de BelongsToGym aísla por sede: un id de
                        // otra sede no aparece y cae como no disponible.
                        $producto = Product::where('id', $productoId)->lockForUpdate()->first();

                        if (! $producto) {
                            throw ValidationException::withMessages([
                                'items' => 'Un producto de la edición ya no está disponible.',
                            ]);
                        }

                        if ($deseada > $actual) {
                            $delta = $deseada - $actual;

                            if ($producto->stock < $delta) {
                                throw ValidationException::withMessages([
                                    'items' => "Stock insuficiente de \"{$producto->name}\": quedan {$producto->stock}.",
                                ]);
                            }

                            $nuevoStock = $producto->stock - $delta;

                            StockMovement::create([
                                'product_id'     => $producto->id,
                                'user_id'        => $request->user()->id,
                                'type'           => 'salida',
                                'quantity'       => $delta,
                                'stock_after'    => $nuevoStock,
                                'reason'         => 'Edición venta ' . $venta->number,
                                'reference_type' => Sale::class,
                                'reference_id'   => $venta->id,
                            ]);

                            $producto->update(['stock' => $nuevoStock]);

                            if ($actual === 0) {
                                // Línea nueva: nombre y precio se congelan AHORA,
                                // igual que en el registro original.
                                $venta->items()->create([
                                    'product_id'   => $producto->id,
                                    'product_name' => $producto->name,
                                    'quantity'     => $deseada,
                                    'unit_price'   => $producto->sale_price,
                                    'total'        => round((float) $producto->sale_price * $deseada, 2),
                                ]);
                            } else {
                                $actuales[$productoId]->update([
                                    'quantity' => $deseada,
                                    'total'    => round((float) $actuales[$productoId]->unit_price * $deseada, 2),
                                ]);
                            }
                        } else {
                            $delta = $actual - $deseada;
                            $nuevoStock = $producto->stock + $delta;

                            StockMovement::create([
                                'product_id'     => $producto->id,
                                'user_id'        => $request->user()->id,
                                'type'           => 'entrada',
                                'quantity'       => $delta,
                                'stock_after'    => $nuevoStock,
                                'reason'         => 'Edición venta ' . $venta->number,
                                'reference_type' => Sale::class,
                                'reference_id'   => $venta->id,
                            ]);

                            $producto->update(['stock' => $nuevoStock]);

                            if ($deseada === 0) {
                                $actuales[$productoId]->delete();
                            } else {
                                $actuales[$productoId]->update([
                                    'quantity' => $deseada,
                                    'total'    => round((float) $actuales[$productoId]->unit_price * $deseada, 2),
                                ]);
                            }
                        }
                    }
                }

                // Una venta de mostrador sin líneas ya no es una venta.
                if ($venta->sale_type === 'producto' && $venta->items()->doesntExist()) {
                    throw ValidationException::withMessages([
                        'items' => 'La venta quedaría vacía: deja al menos un producto o anúlala.',
                    ]);
                }

                $brutoLineas = round((float) $venta->fresh('items')->items->sum('total'), 2);
                $descuento   = array_key_exists('discount', $datos) ? (float) $datos['discount'] : (float) $venta->discount;
                $subtotal    = round($porcionFija + $brutoLineas, 2);

                $venta->update([
                    'method'    => $datos['method'] ?? $venta->method,
                    'subtotal'  => $subtotal,
                    'discount'  => $descuento,
                    'total'     => max($subtotal - $descuento, 0),
                ]);
            });
        } catch (ValidationException $e) {
            // El editor manda fetch con Accept: application/json: que el
            // framework devuelva 422 con los mensajes, no un redirect.
            if ($request->expectsJson()) {
                throw $e;
            }

            return back()->withErrors($e->errors());
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'numero' => $venta->number]);
        }

        return back()->with('exito', "Venta {$venta->number} actualizada.");
    }

    /**
     * Los tipos de venta que muestra cada pestaña de /admin/ventas.
     * "Productos" solo ventas de mostrador; "Registros" es el cajón de todo
     * lo que no es producto (membresías, servicios, otros). Mantener la
     * lista aquí evita que index() y exportar() se desincronicen.
     */
    private function tiposDePestana(string $tipo): array
    {
        return $tipo === 'producto' ? ['producto'] : ['membresia', 'servicio', 'otro'];
    }
}
