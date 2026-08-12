<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Receta peruana con macros expresados en porciones de mano (Fase 3 de
 * PLAN_NUTRICION_PROGRESO.md) — mismo lenguaje que el diario de comidas
 * (MealLog): palma, puño, cuenco, pulgar. No calorías, no gramos.
 *
 * No usa BelongsToGym a propósito, igual que Exercise: gym_id nulo = receta
 * de la biblioteca compartida, visible desde todos los gimnasios. El scope
 * `disponibles` expresa esa regla.
 */
class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id', 'name', 'slug', 'description', 'ingredients', 'steps',
        'prep_minutes', 'servings', 'tags', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tags'          => 'array',
            'is_active'     => 'boolean',
            'prep_minutes'  => 'integer',
            'servings'      => 'integer',
        ];
    }

    public function gym(): BelongsTo      { return $this->belongsTo(Gym::class); }
    public function portions(): HasMany   { return $this->hasMany(RecipePortion::class); }

    /** Las globales más las propias del gimnasio indicado. */
    public function scopeDisponibles(Builder $q, ?int $gymId = null): Builder
    {
        $gymId ??= \App\Support\GymContext::id();

        return $q->where('is_active', true)
                 ->where(fn (Builder $s) => $s->whereNull('gym_id')->orWhere('gym_id', $gymId));
    }

    public function scopeBuscar(Builder $q, ?string $termino): Builder
    {
        if (blank($termino)) {
            return $q;
        }

        $t = '%' . trim($termino) . '%';

        return $q->where(function (Builder $sub) use ($t) {
            $sub->where('name', 'like', $t)->orWhere('ingredients', 'like', $t);
        });
    }

    /** ['palma' => int, 'puno' => int, 'cuenco' => int, 'pulgar' => int], con ceros donde no se definió nada. */
    public function getConteoAttribute(): array
    {
        $base = ['palma' => 0, 'puno' => 0, 'cuenco' => 0, 'pulgar' => 0];

        foreach ($this->portions as $porcion) {
            $base[$porcion->portion_type] = $porcion->count;
        }

        return $base;
    }
}
