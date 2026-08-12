<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Membership extends Model
{
    use HasFactory, SoftDeletes, BelongsToGym;

    protected $fillable = [
        'gym_id', 'member_id', 'plan_id', 'created_by', 'renewed_from',
        'plan_name', 'price', 'discount', 'starts_at', 'ends_at', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at'   => 'date',
            'price'     => 'decimal:2',
            'discount'  => 'decimal:2',
        ];
    }

    public function member(): BelongsTo   { return $this->belongsTo(Member::class); }
    public function plan(): BelongsTo     { return $this->belongsTo(Plan::class); }
    public function createdBy(): BelongsTo{ return $this->belongsTo(User::class, 'created_by'); }
    public function renewedFrom(): BelongsTo { return $this->belongsTo(Membership::class, 'renewed_from'); }
    public function sales(): HasMany      { return $this->hasMany(Sale::class); }

    /* ---------------------------------------------------------- */

    /** Vigentes de verdad: activas y con fecha de fin en el futuro. */
    public function scopeVigentes(Builder $q): Builder
    {
        return $q->where('status', 'activa')->whereDate('ends_at', '>=', now());
    }

    public function scopeVencidas(Builder $q): Builder
    {
        return $q->where('status', 'activa')->whereDate('ends_at', '<', now());
    }

    public function scopeVencenEn(Builder $q, int $dias): Builder
    {
        return $q->where('status', 'activa')
                 ->whereBetween('ends_at', [now()->toDateString(), now()->addDays($dias)->toDateString()]);
    }

    /* ---------------------------------------------------------- */

    public function getTotalAttribute(): float
    {
        return (float) $this->price - (float) $this->discount;
    }

    public function getEstaVigenteAttribute(): bool
    {
        return $this->status === 'activa' && $this->ends_at->endOfDay()->isFuture();
    }

    public function getDiasRestantesAttribute(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->ends_at, false);
    }

    /** Lo pagado hasta ahora contra esta membresía. */
    public function getPagadoAttribute(): float
    {
        return (float) $this->sales()->where('status', 'completada')->sum('total');
    }

    public function getSaldoAttribute(): float
    {
        return round($this->total - $this->pagado, 2);
    }
}
