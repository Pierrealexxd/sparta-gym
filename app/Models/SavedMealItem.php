<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una porción dentro de un plato habitual (ver SavedMeal). */
class SavedMealItem extends Model
{
    use HasFactory;

    protected $fillable = ['saved_meal_id', 'portion_type', 'count'];

    protected function casts(): array
    {
        return ['count' => 'integer'];
    }

    public function savedMeal(): BelongsTo { return $this->belongsTo(SavedMeal::class); }
}
