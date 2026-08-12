<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuántas porciones de un tipo (palma/puño/cuenco/pulgar) hubo en una
 * comida. `food_name` queda reservado para cuando la Fase 3 permita
 * registrar a partir de una receta — hoy el formulario no lo pide.
 */
class MealLogItem extends Model
{
    use HasFactory;

    protected $fillable = ['meal_log_id', 'portion_type', 'count', 'food_name'];

    protected function casts(): array
    {
        return ['count' => 'integer'];
    }

    public function mealLog(): BelongsTo { return $this->belongsTo(MealLog::class); }
}
