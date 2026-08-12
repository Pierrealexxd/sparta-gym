<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Copia única de `payments` → `sales`. Idempotente: cada fila queda
 * marcada en `notes` con "backfill:payment:{id}"; si la migración se
 * vuelve a correr, salta las que ya tienen esa marca.
 *
 * `payments` NO se toca ni se dropea — queda archivada en BD, decisión 1
 * del plan. De aquí en adelante ningún código nuevo la lee.
 */
return new class extends Migration
{
    public function up(): void
    {
        $yaMigrados = DB::table('sales')
            ->where('notes', 'like', 'backfill:payment:%')
            ->pluck('notes')
            ->map(fn ($n) => (int) str_replace('backfill:payment:', '', $n))
            ->flip();

        $pagos = DB::table('payments')->orderBy('gym_id')->orderBy('paid_at')->get();

        $siguienteNumero = [];
        foreach (DB::table('sales')->select('gym_id', 'number')->get() as $venta) {
            $n = (int) str_replace('V-', '', $venta->number);
            $siguienteNumero[$venta->gym_id] = max($siguienteNumero[$venta->gym_id] ?? 0, $n);
        }

        $filas = [];
        $ahora = now();

        foreach ($pagos as $pago) {
            if (isset($yaMigrados[$pago->id])) {
                continue;
            }

            $siguienteNumero[$pago->gym_id] = ($siguienteNumero[$pago->gym_id] ?? 0) + 1;

            $filas[] = [
                'gym_id'        => $pago->gym_id,
                'member_id'     => $pago->member_id,
                'sale_type'     => $pago->membership_id ? 'membresia' : 'otro',
                'membership_id' => $pago->membership_id,
                'sold_by'       => $pago->registered_by,
                'number'        => 'V-' . str_pad((string) $siguienteNumero[$pago->gym_id], 6, '0', STR_PAD_LEFT),
                'subtotal'      => $pago->amount,
                'discount'      => 0,
                'total'         => $pago->amount,
                'concept'       => $pago->concept,
                'reference'     => $pago->reference,
                'notes'         => 'backfill:payment:' . $pago->id,
                'method'        => $pago->method,
                'status'        => $pago->status === 'anulado' ? 'anulada' : 'completada',
                'sold_at'       => $pago->paid_at,
                'created_at'    => $ahora,
                'updated_at'    => $ahora,
            ];
        }

        foreach (array_chunk($filas, 500) as $lote) {
            DB::table('sales')->insert($lote);
        }
    }

    public function down(): void
    {
        DB::table('sales')->where('notes', 'like', 'backfill:payment:%')->delete();
    }
};
