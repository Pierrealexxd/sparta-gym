<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes, BelongsToGym;

    protected $fillable = [
        'gym_id', 'member_id', 'membership_id', 'registered_by',
        'concept', 'amount', 'currency', 'method', 'reference',
        'receipt_path', 'status', 'paid_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'amount'  => 'decimal:2',
        ];
    }

    public function member(): BelongsTo       { return $this->belongsTo(Member::class); }
    public function membership(): BelongsTo   { return $this->belongsTo(Membership::class); }
    public function registeredBy(): BelongsTo { return $this->belongsTo(User::class, 'registered_by'); }

    /* ---------------------------------------------------------- */
    /* Arqueo de caja                                             */
    /* ---------------------------------------------------------- */

    public function scopeCobrados(Builder $q): Builder
    {
        return $q->where('status', 'pagado');
    }

    public function scopePendientes(Builder $q): Builder
    {
        return $q->where('status', 'pendiente');
    }

    /** Se filtra por paid_at, no por created_at: la caja cuadra por fecha real. */
    public function scopeDelDia(Builder $q, $fecha = null): Builder
    {
        return $q->whereDate('paid_at', $fecha ?? now()->toDateString());
    }

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

    /* ---------------------------------------------------------- */
    /* Tipo de cobro: mensualidad (ligada a una membresía) vs      */
    /* pago suelto (venta, cuota atrasada, ajuste). Se distinguen  */
    /* por membership_id, no por una columna nueva: ese dato ya    */
    /* estaba ahí, sólo faltaba explotarlo en la UI.                */
    /* ---------------------------------------------------------- */

    public function scopeMensualidades(Builder $q): Builder
    {
        return $q->whereNotNull('membership_id');
    }

    public function scopeSueltos(Builder $q): Builder
    {
        return $q->whereNull('membership_id');
    }

    /** Atajo para filtros de UI: acepta 'mensualidad', 'suelto' o null (todos). */
    public function scopeDeTipo(Builder $q, ?string $tipo): Builder
    {
        return match ($tipo) {
            'mensualidad' => $q->mensualidades(),
            'suelto'      => $q->sueltos(),
            default       => $q,
        };
    }

    public function getTipoAttribute(): string
    {
        return $this->membership_id ? 'mensualidad' : 'suelto';
    }

    public function getMetodoLegibleAttribute(): string
    {
        return config("sparta.metodos_pago.{$this->method}", ucfirst($this->method));
    }
}
