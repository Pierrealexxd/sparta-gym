<?php

namespace App\Imports;

use App\Models\Member;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Importa ventas de MOSTRADOR (sale_type = 'producto') desde un Excel/CSV.
 * Solo ventas de producto a propósito — ver el comentario en
 * SaleController::importar() sobre por qué las membresías quedan fuera.
 *
 * Seguridad: WithValidation valida cada fila (formato/tipo/rango) ANTES de
 * que collection() vea una sola fila — si algo no cumple `rules()`, Excel::
 * import() lanza ValidationException y el controlador corta ahí, sin tocar
 * la base de datos. Dentro de collection(), cliente y producto se resuelven
 * SIEMPRE con consultas parametrizadas (where(), nunca DB::raw ni
 * concatenar el valor del Excel en SQL) y una fila cuyo producto no exista
 * o no tenga stock suficiente simplemente se salta — nunca se crea una
 * venta con datos a medias.
 */
class SaleImport implements ToCollection, WithHeadingRow, WithValidation
{
    /** Filas saltadas (producto inexistente/inactivo o stock insuficiente), para el mensaje de resultado. */
    public int $saltadas = 0;

    /** Ventas creadas con éxito. */
    public int $creadas = 0;

    public function __construct(
        private readonly ?int $gymId,
        private readonly int $userId,
    ) {}

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $codigoCliente = trim((string) ($row['cliente_codigo'] ?? ''));
                $nombreProducto = trim((string) ($row['producto_nombre'] ?? ''));

                $memberId = null;
                if ($codigoCliente !== '') {
                    // where() parametrizado: el código del Excel nunca se
                    // concatena en la query. Si no existe, la venta se crea
                    // sin cliente (no falla la fila completa). gym_id
                    // explícito además del global scope de BelongsToGym:
                    // defensa en profundidad para un dato que viene de un
                    // archivo subido por el usuario.
                    $memberId = Member::where('code', $codigoCliente)
                        ->when($this->gymId, fn ($q) => $q->where('gym_id', $this->gymId))
                        ->value('id');
                }

                $producto = Product::where('name', $nombreProducto)
                    ->where('is_active', true)
                    ->when($this->gymId, fn ($q) => $q->where('gym_id', $this->gymId))
                    ->lockForUpdate()
                    ->first();

                if (! $producto) {
                    $this->saltadas++;

                    continue; // Producto inexistente o inactivo: se salta la fila, no se aborta el archivo.
                }

                $cantidad = (int) $row['cantidad'];

                if ($producto->stock < $cantidad) {
                    // El plan original no validaba esto y dejaba que
                    // decrement() metiera el stock en negativo. Se salta la
                    // fila en vez de vender lo que no hay, igual que
                    // SaleController::store rechaza la venta manual.
                    $this->saltadas++;

                    continue;
                }

                $precioUnitario = round((float) $row['precio_unitario'], 2);
                $descuento = round((float) ($row['descuento'] ?? 0), 2);
                $subtotal = round($precioUnitario * $cantidad, 2);
                $total = max($subtotal - $descuento, 0);

                $venta = Sale::create([
                    'member_id' => $memberId,
                    'sale_type' => 'producto',
                    'sold_by'   => $this->userId,
                    'number'    => Sale::siguienteNumero(),
                    'subtotal'  => $subtotal,
                    'discount'  => $descuento,
                    'total'     => $total,
                    'concept'   => "Importación: {$producto->name}",
                    'method'    => $row['metodo_pago'],
                    'status'    => 'completada',
                    'notes'     => trim((string) ($row['notas'] ?? '')) ?: null,
                    'sold_at'   => Carbon::createFromFormat('d/m/Y H:i', trim((string) $row['fecha'])),
                ]);

                $venta->items()->create([
                    'product_id'   => $producto->id,
                    'product_name' => $producto->name,
                    'quantity'     => $cantidad,
                    'unit_price'   => $precioUnitario,
                    'total'        => $subtotal,
                ]);

                $nuevoStock = $producto->stock - $cantidad;

                StockMovement::create([
                    'product_id'     => $producto->id,
                    'user_id'        => $this->userId,
                    'type'           => 'salida',
                    'quantity'       => $cantidad,
                    'stock_after'    => $nuevoStock,
                    'reason'         => "Importación venta {$venta->number}",
                    'reference_type' => Sale::class,
                    'reference_id'   => $venta->id,
                ]);

                // Igual que SaleController::store: update() explícito, no
                // decrement() a ciegas — stock_after de arriba ya calculó
                // el número correcto, así que este debe coincidir siempre.
                $producto->update(['stock' => $nuevoStock]);

                $this->creadas++;
            }
        });
    }

    public function rules(): array
    {
        return [
            'fecha'           => ['required', 'date_format:d/m/Y H:i'],
            'cliente_codigo'  => ['nullable', 'string', 'max:30'],
            'producto_nombre' => ['required', 'string', 'max:150'],
            'cantidad'        => ['required', 'integer', 'min:1'],
            'precio_unitario' => ['required', 'numeric', 'min:0'],
            'metodo_pago'     => ['required', 'in:' . implode(',', array_keys(config('sparta.metodos_pago')))],
            'descuento'       => ['nullable', 'numeric', 'min:0'],
            'notas'           => ['nullable', 'string', 'max:500'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'fecha.date_format' => 'La columna fecha debe tener el formato d/m/Y H:i (ej: 15/08/2026 14:30).',
            'metodo_pago.in'    => 'El método de pago no es válido.',
        ];
    }
}
