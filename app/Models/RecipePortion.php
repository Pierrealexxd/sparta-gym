<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Desglose por porción de mano de una receta (ver Recipe). */
class RecipePortion extends Model
{
    use HasFactory;

    protected $fillable = ['recipe_id', 'portion_type', 'count', 'food_name'];

    protected function casts(): array
    {
        return ['count' => 'integer'];
    }

    public function recipe(): BelongsTo { return $this->belongsTo(Recipe::class); }
}
