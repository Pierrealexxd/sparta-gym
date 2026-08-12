<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes, BelongsToGym;

    protected $fillable = [
        'gym_id', 'name', 'slug', 'tagline', 'description', 'price',
        'duration_days', 'features', 'accent_color', 'is_featured',
        'is_public', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features'    => 'array',
            'price'       => 'decimal:2',
            'is_featured' => 'boolean',
            'is_public'   => 'boolean',
            'is_active'   => 'boolean',
        ];
    }

    public function memberships(): HasMany { return $this->hasMany(Membership::class); }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order');
    }

    /** Planes que se muestran en la web pública. */
    public function scopePublicos(Builder $q): Builder
    {
        return $q->activos()->where('is_public', true);
    }

    /** Precio por mes: es lo que permite comparar planes de distinta duración. */
    public function getPrecioMensualAttribute(): float
    {
        return round((float) $this->price / max(1, $this->duration_days / 30), 2);
    }

    public function getDuracionLegibleAttribute(): string
    {
        return match (true) {
            $this->duration_days === 1       => 'Pase diario',
            $this->duration_days % 365 === 0 => intdiv($this->duration_days, 365) . ' año',
            $this->duration_days % 30 === 0  => intdiv($this->duration_days, 30) . ' mes'
                                                . (intdiv($this->duration_days, 30) > 1 ? 'es' : ''),
            default                          => $this->duration_days . ' días',
        };
    }
}
