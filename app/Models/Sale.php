<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use App\Support\GymContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Sale extends Model
{
    use HasFactory, BelongsToGym;

    protected $fillable = [
        'gym_id', 'member_id', 'sale_type', 'membership_id', 'sold_by', 'number',
        'subtotal', 'discount', 'total', 'concept', 'reference', 'notes',
        'method', 'status', 'sold_at',
    ];

    protected function casts(): array
    {
        return [
            'sold_at'  => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total'    => 'decimal:2',
        ];
    }

    public function member(): BelongsTo     { return $this->belongsTo(Member::class); }
    public function soldBy(): BelongsTo     { return $this->belongsTo(User::class, 'sold_by'); }
    public function items(): HasMany        { return $this->hasMany(SaleItem::class); }
    public function membership(): BelongsTo { return $this->belongsTo(Membership::class); }

    public function scopeCompletadas(Builder $q): Builder
    {
        return $q->where('status', 'completada');
    }

    public function scopeDelDia(Builder $q, $fecha = null): Builder
    {
        return $q->whereDate('sold_at', $fecha ?? now()->toDateString());
    }

    public function scopeEntreFechas(Builder $q, $desde, $hasta): Builder
    {
        return $q->whereBetween('sold_at', [
            \Illuminate\Support\Carbon::parse($desde)->startOfDay(),
            \Illuminate\Support\Carbon::parse($hasta)->endOfDay(),
        ]);
    }

    /** Filtra por sold_at, no por created_at: la caja cuadra por fecha real. */
    public function scopeDelMes(Builder $q, ?int $anio = null, ?int $mes = null): Builder
    {
        $ref = now()->setDate($anio ?? now()->year, $mes ?? now()->month, 1);

        return $q->entreFechas($ref->copy()->startOfMonth(), $ref->copy()->endOfMonth());
    }

    public function getMetodoLegibleAttribute(): string
    {
        return config("sparta.metodos_pago.{$this->method}", ucfirst($this->method));
    }

    /**
     * Correlativo por sede: V-000001, V-000002… Bloquea la última fila del
     * gym con lockForUpdate() para que dos ventas simultáneas no calculen
     * el mismo número; el índice único (gym_id, number) es el respaldo
     * para el caso borde de la primera venta de una sede (nada que
     * bloquear todavía) — si choca, el caller debe reintentar.
     */
    public static function siguienteNumero(): string
    {
        $gymId = GymContext::id();

        // Sin sede activa, el when() se saltaba el filtro y tomaba el último
        // número de TODAS las sedes: dos sedes trabajando a la vez podían
        // recibir el mismo correlativo. Es preferible fallar ruidosamente
        // que emitir un número duplicado — quien llame sin contexto de
        // gimnasio tiene un problema antes de llegar hasta aquí.
        if (! $gymId) {
            throw new \RuntimeException('No hay gimnasio activo para generar número de venta.');
        }

        $ultimo = DB::transaction(function () use ($gymId) {
            return static::query()
                ->where('gym_id', $gymId)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('number');
        });

        $siguiente = $ultimo ? ((int) substr($ultimo, 2)) + 1 : 1;

        return 'V-' . str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
    }
}
