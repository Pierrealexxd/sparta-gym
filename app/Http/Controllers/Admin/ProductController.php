<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
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

        return view('admin.inventario.index', [
            'productos' => Product::activos()
                ->when($termino, fn ($q) => $q->where(function ($sub) use ($termino) {
                    $t = '%' . trim($termino) . '%';
                    $sub->where('name', 'like', $t)
                        ->orWhere('sku', 'like', $t)
                        ->orWhere('category', 'like', $t);
                }))
                ->orderBy('name')
                ->paginate(12)
                ->onEachSide(1)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.inventario.form', ['producto' => new Product()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validarDatos($request);
        $stockInicial = $datos['stock_inicial'] ?? 0;
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
            'sku'           => [
                'nullable', 'string', 'max:40',
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

        $datos['min_stock'] = $datos['min_stock'] ?? 0;
        $datos['is_active'] = $request->boolean('is_active', true);

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
