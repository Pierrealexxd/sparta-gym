<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Routine extends Model
{
    use HasFactory, SoftDeletes, BelongsToGym;

    protected $fillable = [
        'gym_id', 'member_id', 'trainer_id', 'name', 'objective',
        'notes', 'starts_at', 'ends_at', 'status',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date'];
    }

    public function member(): BelongsTo  { return $this->belongsTo(Member::class); }
    public function trainer(): BelongsTo { return $this->belongsTo(Trainer::class); }

    public function days(): HasMany
    {
        return $this->hasMany(RoutineDay::class)->orderBy('sort_order');
    }

    public function scopeActivas(Builder $q): Builder
    {
        return $q->where('status', 'activa');
    }

    /** Total de ejercicios de toda la rutina. */
    public function getTotalEjerciciosAttribute(): int
    {
        return $this->days->sum(fn (RoutineDay $d) => $d->exercises->count());
    }
}
