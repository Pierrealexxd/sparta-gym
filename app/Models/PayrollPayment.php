<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pago de planilla: sueldo, comisión o bono entregado a un trabajador.
 * Mismo patrón que Payment pero para dinero que sale en vez de entrar.
 */
class PayrollPayment extends Model
{
    use HasFactory, SoftDeletes, BelongsToGym;

    protected $fillable = [
        'gym_id', 'user_id', 'paid_by', 'concept', 'amount', 'method',
        'period_start', 'period_end', 'paid_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'period_start' => 'date',
            'period_end'   => 'date',
            'paid_at'      => 'datetime',
        ];
    }

    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function paidBy(): BelongsTo { return $this->belongsTo(User::class, 'paid_by'); }

    public function scopeEntreFechas(Builder $q, $desde, $hasta): Builder
    {
        return $q->whereBetween('paid_at', [
            \Illuminate\Support\Carbon::parse($desde)->startOfDay(),
            \Illuminate\Support\Carbon::parse($hasta)->endOfDay(),
        ]);
    }

    public function scopeDelMes(Builder $q, ?int $anio = null, ?int $mes = null): Builder
    {
        $ref = now()->setDate($anio ?? now()->year, $mes ?? now()->month, 1);

        return $q->entreFechas($ref->copy()->startOfMonth(), $ref->copy()->endOfMonth());
    }

    public function getMetodoLegibleAttribute(): string
    {
        return config("sparta.metodos_pago.{$this->method}", ucfirst($this->method));
    }
}
