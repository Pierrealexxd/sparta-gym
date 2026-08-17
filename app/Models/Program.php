<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Programa público de la landing ("Ganar masa muscular", "Perder grasa
 * corporal"...). Mismo criterio que Exercise/Recipe: gym_id nulo = biblioteca
 * compartida, visible desde todos los gimnasios — sin BelongsToGym.
 */
class Program extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'gym_id', 'slug', 'name', 'tagline', 'objective',
        'description', 'highlights', 'icon', 'accent_color',
        'duration_weeks', 'difficulty', 'sort_order',
        'is_active', 'is_public',
        // FASE 3 de PLAN-GUIAS-EJERCICIO.md: recomendaciones visibles en
        // "Mi Rutina" — nullable, un programa sin ellas no muestra la sección.
        'nutrition_tips', 'recovery_tips', 'hydration_tips', 'supplements_tips',
    ];

    protected function casts(): array
    {
        return [
            'highlights'        => 'array',
            'nutrition_tips'    => 'array',
            'recovery_tips'     => 'array',
            'hydration_tips'    => 'array',
            'supplements_tips'  => 'array',
            'is_active'         => 'boolean',
            'is_public'         => 'boolean',
        ];
    }

    public function gym(): BelongsTo { return $this->belongsTo(Gym::class); }

    /** Plantillas de rutina base (Parte 2 de PLAN-PROGRAMAS.md). */
    public function routineTemplates(): HasMany
    {
        return $this->hasMany(ProgramRoutine::class)->orderBy('sort_order');
    }

    /**
     * Rutinas reales ya asignadas a socios desde este programa (clonadas de
     * las plantillas de arriba). Inversa de Routine::program().
     */
    public function routines(): HasMany
    {
        return $this->hasMany(Routine::class);
    }

    /** Los que se muestran en la landing, en su orden editorial. */
    public function scopePublicos(Builder $q): Builder
    {
        return $q->where('is_active', true)->where('is_public', true)->orderBy('sort_order');
    }
}
