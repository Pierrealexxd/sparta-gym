<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Mis platos habituales" (Fase 4 de PLAN_NUTRICION_PROGRESO.md): una
 * plantilla de porciones que el socio guarda para volver a registrarla con
 * un tap, sin volver a escribir los cuatro números.
 *
 * Sin BelongsToGym a propósito: hereda el aislamiento de sede a través de
 * member_id, igual que MealLog.
 */
class SavedMeal extends Model
{
    use HasFactory;

    protected $fillable = ['member_id', 'meal_type', 'name'];

    public function member(): BelongsTo { return $this->belongsTo(Member::class); }
    public function items(): HasMany    { return $this->hasMany(SavedMealItem::class); }

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

    /** ['palma' => int, 'puno' => int, 'cuenco' => int, 'pulgar' => int], con ceros donde no se guardó nada. */
    public function getConteoAttribute(): array
    {
        $base = ['palma' => 0, 'puno' => 0, 'cuenco' => 0, 'pulgar' => 0];

        foreach ($this->items as $item) {
            $base[$item->portion_type] = $item->count;
        }

        return $base;
    }
}
