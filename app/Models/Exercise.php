<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Biblioteca de ejercicios.
 *
 * No usa BelongsToGym: un ejercicio con gym_id nulo es de la biblioteca
 * compartida y debe verse desde todos los gimnasios. El scope `disponibles`
 * expresa esa regla, que un filtro automático no sabría hacer.
 */
class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id', 'name', 'slug', 'description', 'muscle_groups', 'level',
        'equipment', 'category', 'image_path', 'video_url',
        'common_mistakes', 'tips', 'is_active',
    ];

    protected function casts(): array
    {
        return ['muscle_groups' => 'array', 'is_active' => 'boolean'];
    }

    public function gym(): BelongsTo { return $this->belongsTo(Gym::class); }

    // Inversas de las tablas que apuntan a exercise_id: sirven para saber si
    // un ejercicio está en uso antes de desactivarlo o borrarlo.
    public function routineExercises(): HasMany
    {
        return $this->hasMany(RoutineExercise::class);
    }

    public function programRoutineExercises(): HasMany
    {
        return $this->hasMany(ProgramRoutineExercise::class);
    }

    /** Los globales más los propios del gimnasio indicado. */
    public function scopeDisponibles(Builder $q, ?int $gymId = null): Builder
    {
        $gymId ??= \App\Support\GymContext::id();

        return $q->where('is_active', true)
                 ->where(fn (Builder $s) => $s->whereNull('gym_id')->orWhere('gym_id', $gymId));
    }

    public function scopeDeGrupo(Builder $q, string $musculo): Builder
    {
        return $q->whereJsonContains('muscle_groups', $musculo);
    }

    /** ID de YouTube si video_url es un enlace válido (watch, youtu.be o embed). */
    public function getVideoIdAttribute(): ?string
    {
        if (! $this->video_url) {
            return null;
        }

        $query = parse_url($this->video_url, PHP_URL_QUERY);

        if ($query) {
            parse_str($query, $parametros);
            $id = $parametros['v'] ?? null;
        } else {
            $id = basename((string) parse_url($this->video_url, PHP_URL_PATH));
        }

        return is_string($id) && strlen($id) === 11 ? $id : null;
    }

    /** URL incrustable (sin cookies) para el reproductor de la landing. */
    public function getVideoEmbedAttribute(): ?string
    {
        return $this->video_id
            ? "https://www.youtube-nocookie.com/embed/{$this->video_id}"
            : null;
    }
}
