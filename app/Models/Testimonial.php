<?php

namespace App\Models;

use App\Support\GymContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Testimonial extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id', 'member_id', 'author', 'role', 'content',
        'rating', 'photo_path', 'is_published', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'rating' => 'integer'];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->gym_id) && $gymId = GymContext::id()) {
                $model->gym_id = $gymId;
            }
        });
    }

    public function member(): BelongsTo { return $this->belongsTo(Member::class); }

    /**
     * En "Todas las sedes" (GymContext null) muestra todos los testimonios.
     * Con sede activa, muestra los de esa sede + los globales (gym_id null).
     */
    public function scopeDelGym(Builder $query): Builder
    {
        $gymId = GymContext::id();

        if (! $gymId) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('gym_id', $gymId)->orWhereNull('gym_id'));
    }

    public function scopePublicados(Builder $q): Builder
    {
        return $q->where('is_published', true)->orderBy('sort_order');
    }

    public function scopePendientes(Builder $q): Builder
    {
        return $q->where('is_published', false)->latest();
    }
}
