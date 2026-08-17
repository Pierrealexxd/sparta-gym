<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockAlert;
use App\Models\StockMovement;
use App\Support\GymContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * CRUD de productos de mostrador. `stock` es un saldo que este controlador
 * nunca escribe directo (salvo al reflejar el resultado de un movimiento):
 * la verdad vive en `stock_movements` (ver AGENTS.md).
 */
class ProductController extends Controller
{
    public const CATEGORIAS = [
        'suplemento' => 'Suplemento',
        'proteina'   => 'Proteína',
        'accesorio'  => 'Accesorio',
        'bebida'     => 'Bebida',
        'ropa'       => 'Ropa',
        'otro'       => 'Otro',
    ];

    public function index(Request $request): View
    {
        $termino = $request->get('q');
        $estado  = $request->get('estado');
        if ($estado && ! in_array($estado, ['normal', 'bajo', 'critico', 'agotado'], true)) {
            $estado = null;
        }

        return view('admin.inventario.index', [
            // KPIs de estado de stock, siempre sobre el catálogo completo de
            // activos (independientes del término de búsqueda) — mismo patrón
            // que los indicadores de ventas.
            'conteos'   => $this->conteosPorEstado(),
            'estado'    => $estado,
            'productos' => Product::activos()
                ->when($termino, fn ($q) => $q->where(function ($sub) use ($termino) {
                    $t = '%' . trim($termino) . '%';
                    $sub->where('name', 'like', $t)
                        ->orWhere('sku', 'like', $t)
                        ->orWhere('category', 'like', $t);
                }))
                ->when($estado, fn ($q) => $q->enEstado($estado))
                ->orderBy('name')
                ->paginate(10)
                ->onEachSide(1)
                ->withQueryString(),
        ]);
    }

    /** Conteo de activos por cada estado de stock, para las tarjetas KPI. */
    private function conteosPorEstado(): array
    {
        $conteos = [];
        foreach (['normal', 'bajo', 'critico', 'agotado'] as $estado) {
            $conteos[$estado] = Product::activos()->enEstado($estado)->count();
        }

        return $conteos;
    }

    /**
     * Ficha del producto con su historial de movimientos (el libro mayor):
     * cada entrada/salida/ajuste registrado, porque `stock` es un saldo y
     * la verdad vive en `stock_movements` (ver AGENTS.md).
     */
    public function show(Product $producto): View
    {
        return view('admin.inventario.show', [
            'producto'   => $producto,
            'movimientos' => $producto->movements()
                ->with('user')
                ->latest('created_at')
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.inventario.form', ['producto' => new Product()]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Mismo freno que TrainerController: un producto exige gym_id y no
        // debe quedar sin sede porque el admin estaba viendo "Todas las sedes".
        if ($bloqueo = $this->exigirSedeEspecifica('un producto')) {
            return $bloqueo;
        }

        // Si el admin marcó "Generar SKU automáticamente", inyectamos el SKU
        // generado antes devalidar, de modo que la validación de unicidad pase
        // (el SKU es SP-XXXX con número máximo+1, garantizado único).
        if ($request->boolean('generar_sku_automatico')) {
            $request->merge(['sku' => Product::generarSku(GymContext::id())]);
        }

        $datos = $this->validarDatos($request);
        $stockInicial = (int) ($datos['stock_inicial'] ?? 0);
        unset($datos['stock_inicial']);

        if ($ruta = $this->guardarImagen($request)) {
            $datos['image_path'] = $ruta;
        }

        $producto = Product::create($datos + ['stock' => 0]);

        if ($stockInicial > 0) {
            $this->registrarEntrada($producto, $stockInicial, 'Stock inicial', $request->user()->id);
        }

        return redirect()->route('admin.inventario.index')->with('exito', 'Producto creado.');
    }

    public function edit(Product $producto): View
    {
        return view('admin.inventario.form', ['producto' => $producto]);
    }

    public function update(Request $request, Product $producto): RedirectResponse
    {
        // Si el admin marcó "Generar SKU automáticamente", inyectamos el SKU
        // generado antes de validar, de modo que la validación de unicidad pase
        // (el SKU es SP-XXXX con número máximo+1, garantizado único para el gym).
        if ($request->boolean('generar_sku_automatico')) {
            $request->merge(['sku' => Product::generarSku(GymContext::id())]);
        }

        $datos = $this->validarDatos($request, $producto);
        unset($datos['stock_inicial']);

        if ($ruta = $this->guardarImagen($request)) {
            if ($producto->image_path) {
                Storage::disk('public')->delete($producto->image_path);
            }
            $datos['image_path'] = $ruta;
        }

        $producto->update($datos);

        return redirect()->route('admin.inventario.index')->with('exito', 'Producto actualizado.');
    }

    public function destroy(Product $producto): RedirectResponse
    {
        $producto->update(['is_active' => false]);

        return back()->with('exito', 'Producto desactivado.');
    }

    /** Desactiva en lote los productos seleccionados — misma lógica que destroy(). */
    public function destroyMasivo(Request $request): RedirectResponse
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));

        if ($ids === []) {
            return back()->with('error', 'Selecciona al menos un producto.');
        }

        $desactivados = Product::whereIn('id', $ids)->update(['is_active' => false]);

        // El update masivo no dispara eventos de modelo (ver observer en
        // AppServiceProvider): sin esta limpieza, las alertas de esos
        // productos quedarían huérfanas pidiendo reposición de un artículo
        // que ya no se vende.
        StockAlert::whereIn('product_id', $ids)->delete();

        return redirect()->route('admin.inventario.index')->with('exito', "{$desactivados} productos desactivados.");
    }

    /** Entrada de mercancía o ajuste manual — nunca se toca `stock` directo. */
    public function registrarMovimiento(Request $request, Product $producto): RedirectResponse
    {
        $datos = $request->validate([
            'type'     => ['required', 'in:entrada,ajuste'],
            'quantity' => ['required', 'integer'],
            'reason'   => ['nullable', 'string', 'max:160'],
        ]);

        if ($datos['type'] === 'entrada' && $datos['quantity'] < 1) {
            return back()->withErrors(['quantity' => 'La cantidad de entrada debe ser mayor a cero.']);
        }

        if ($datos['type'] === 'ajuste' && $datos['quantity'] === 0) {
            return back()->withErrors(['quantity' => 'El ajuste no puede ser cero.']);
        }

        $nuevoStock = $producto->stock + $datos['quantity'];
        if ($nuevoStock < 0) {
            return back()->withErrors(['quantity' => 'El ajuste dejaría el stock en negativo.']);
        }

        StockMovement::create([
            'product_id'  => $producto->id,
            'user_id'     => $request->user()->id,
            'type'        => $datos['type'],
            'quantity'    => $datos['quantity'],
            'stock_after' => $nuevoStock,
            'reason'      => $datos['reason'] ?? ($datos['type'] === 'entrada' ? 'Ingreso de mercancía' : 'Ajuste manual'),
        ]);

        $producto->update(['stock' => $nuevoStock]);

        return back()->with('exito', 'Movimiento registrado.');
    }

    private function registrarEntrada(Product $producto, int $cantidad, string $motivo, int $userId): void
    {
        $nuevoStock = $producto->stock + $cantidad;

        StockMovement::create([
            'product_id'  => $producto->id,
            'user_id'     => $userId,
            'type'        => 'entrada',
            'quantity'    => $cantidad,
            'stock_after' => $nuevoStock,
            'reason'      => $motivo,
        ]);

        $producto->update(['stock' => $nuevoStock]);
    }

    private function validarDatos(Request $request, ?Product $producto = null): array
    {
        $datos = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
'sku' => [
                'nullable', 'string', 'max:40',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value && ! preg_match('/^SP-\d{3,}$/', $value)) {
                        $fail('El SKU debe tener el formato SP-XXX (ej. SP-001).');
                    }
                },
                Rule::unique('products', 'sku')
                    ->where('gym_id', GymContext::id())
                    ->ignore($producto?->id),
            ],
            'category'      => ['required', 'in:' . implode(',', array_keys(self::CATEGORIAS))],
            'description'   => ['nullable', 'string', 'max:1000'],
            'cost_price'    => ['required', 'numeric', 'min:0'],
            'sale_price'    => ['required', 'numeric', 'min:0'],
            'min_stock'     => ['nullable', 'integer', 'min:0'],
            'stock_inicial' => ['nullable', 'integer', 'min:0'],
            'imagen'        => ['nullable', 'image', 'max:3072'],
        ]);

        unset($datos['imagen']);

        $datos['min_stock'] = (int) ($datos['min_stock'] ?? 0);
        // boolean() sin default: si el checkbox va desmarcado no llega la
        // clave y el producto debe quedar inactivo — un default `true` hacía
        // imposible desactivarlo desde el formulario.
        $datos['is_active'] = $request->boolean('is_active');

        return $datos;
    }

    private function guardarImagen(Request $request): ?string
    {
        if (! $request->hasFile('imagen')) {
            return null;
        }

        return $request->file('imagen')->store('productos', 'public');
    }
}
