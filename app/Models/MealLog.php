<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una comida registrada por porciones de mano (Fase 2 de
 * PLAN_NUTRICION_PROGRESO.md) — no calorías, no gramos.
 *
 * Sin BelongsToGym a propósito: hereda el aislamiento de sede a través de
 * member_id, igual que MemberMeasurement y MemberGoal (ver la migración).
 */
class MealLog extends Model
{
    use HasFactory;

    protected $fillable = ['member_id', 'meal_type', 'logged_on', 'notes'];

    protected function casts(): array
    {
        return ['logged_on' => 'date'];
    }

    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
    public function items(): HasMany    { return $this->hasMany(MealLogItem::class); }

    public function scopeDelDia(Builder $q, ?string $fecha = null): Builder
    {
        return $q->whereDate('logged_on', $fecha ?? now()->toDateString());
    }

    /** Nombre legible para el formulario y el listado. */
    public function getMealTypeLegibleAttribute(): string
    {
        return match ($this->meal_type) {
            'desayuno' => 'Desayuno',
            'almuerzo' => 'Almuerzo',
            'cena'     => 'Cena',
            'merienda' => 'Merienda',
            default    => ucfirst($this->meal_type),
        };
    }

    /** ['palma' => int, 'puno' => int, 'cuenco' => int, 'pulgar' => int], con ceros donde no se registró nada. */
    public function getConteoAttribute(): array
    {
        $base = ['palma' => 0, 'puno' => 0, 'cuenco' => 0, 'pulgar' => 0];

        foreach ($this->items as $item) {
            $base[$item->portion_type] = $item->count;
        }

        return $base;
    }
}
