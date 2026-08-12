<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, BelongsToGym;

    protected $fillable = [
        'gym_id', 'name', 'sku', 'category', 'description', 'image_path',
        'cost_price', 'sale_price', 'stock', 'min_stock', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'stock'      => 'integer',
            'is_active'  => 'boolean',
        ];
    }

    public function movements(): HasMany { return $this->hasMany(StockMovement::class); }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Lo que hay que reponer: en o por debajo del umbral. */
    public function scopeBajoStock(Builder $q): Builder
    {
        return $q->whereColumn('stock', '<=', 'min_stock');
    }

    public function getNecesitaReposicionAttribute(): bool
    {
        return $this->stock <= $this->min_stock;
    }

    public function getMargenAttribute(): float
    {
        return round((float) $this->sale_price - (float) $this->cost_price, 2);
    }
}
