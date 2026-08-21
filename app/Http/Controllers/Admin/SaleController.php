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

        return view('admin.ventas.index', [
            'tipo'           => $tipo,
            'desde'          => $desde,
            'hasta'          => $hasta,
            'asistieronHoy'  => $asistieronHoy,
            'ventasHoy'      => Sale::completadas()->delDia()->sum('total'),
            'productos'      => Product::activos()->orderBy('name')->get(),
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
            'concepto'      => $venta->concept,
            'subtotal'      => (float) $venta->subtotal,
            'descuento'     => (float) $venta->discount,
            'total'         => (float) $venta->total,
            'estado'        => $venta->status,
            'notas'         => $venta->notes,
            'items'         => $venta->items->map(fn ($i) => [
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
     * Muestra el formulario de edición de una venta.
     */
    public function edit(Sale $venta): View
    {
        $this->authorize('manage', $venta);
        return view('admin.ventas.edit', compact('venta'));
    }

    /**
     * Actualiza los datos de una venta.
     */
    public function update(Request $request, Sale $venta): RedirectResponse
    {
        $this->authorize('manage', $venta);

        $datos = $request->validate([
            'concept'      => ['sometimes', 'string', 'max:120'],
            'method'       => ['sometimes', 'required', 'in:efectivo,transferencia,yape,plin,tarjeta,otro'],
            'status'       => ['sometimes', 'string'],
        ]);

        $venta->update($datos);

        // Notificar al admin (aunque él mismo se está editando, por si hay
        // otro admin que necesite enterarse)
        app(NotificationService::class)->dispararA(
            app(User::where('role_id', Role::where('slug', 'admin')->value('id'))->first()),
            'venta.editada',
            'Venta editada',
            "Un admin editó la venta {$venta->number}",
            'ventas',
            'media',
            $venta->id,
            route('admin.ventas.show', $venta),
        );

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
