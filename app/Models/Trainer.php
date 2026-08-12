<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Perfil profesional del entrenador. La identidad de acceso vive en User;
 * aquí está lo que el gimnasio publica y usa para asignar socios.
 */
class Trainer extends Model
{
    use HasFactory, SoftDeletes, BelongsToGym;

    protected $fillable = [
        'gym_id', 'user_id', 'specialty', 'bio', 'photo_path',
        'certifications', 'schedule', 'socials', 'years_experience',
        'is_public', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'certifications' => 'array',
            'schedule'       => 'array',
            'socials'        => 'array',
            'is_public'      => 'boolean',
            'is_active'      => 'boolean',
        ];
    }

    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function assignments(): HasMany  { return $this->hasMany(TrainerAssignment::class); }
    public function routines(): HasMany     { return $this->hasMany(Routine::class); }

    /** Socios que tiene a cargo ahora mismo. */
    public function activeMembers()
    {
        return $this->hasManyThrough(
            Member::class, TrainerAssignment::class,
            'trainer_id', 'id', 'id', 'member_id'
        )->whereNull('trainer_assignments.ended_at');
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order');
    }

    /** Entrenadores que aparecen en la landing. */
    public function scopePublicos(Builder $q): Builder
    {
        return $q->activos()->where('is_public', true);
    }

    public function getNombreAttribute(): string
    {
        return $this->user?->name ?? 'Entrenador';
    }
}
