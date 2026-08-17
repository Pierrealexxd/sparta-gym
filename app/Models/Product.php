<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

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

    /**
     * Filtra por estado de stock, con la misma fórmula que el accessor
     * estado_stock (ver StockAlertService para la semántica de cada estado).
     * La banda "bajo" se traduce a SQL con GREATEST porque MySQL no tiene max()
     * escalar en un where — y la fórmula debe quedar idéntica a la del acceso
     * para que KPI y lista no se desincronicen.
     */
    public function scopeEnEstado(Builder $q, string $estado): Builder
    {
        $banda = 'GREATEST(min_stock * ' . (int) config('sparta.stock_umbral_bajo', 2) . ', 1)';

        return match ($estado) {
            'agotado' => $q->where('stock', '<=', 0),
            'critico' => $q->where('stock', '>', 0)->whereColumn('stock', '<=', 'min_stock'),
            'bajo'    => $q->whereColumn('stock', '>', 'min_stock')->whereColumn('stock', '<=', DB::raw($banda)),
            'normal'  => $q->whereColumn('stock', '>', DB::raw($banda)),
            default   => $q,
        };
    }

    public function getNecesitaReposicionAttribute(): bool
    {
        return $this->stock <= $this->min_stock;
    }

    public function getMargenAttribute(): float
    {
        return round((float) $this->sale_price - (float) $this->cost_price, 2);
    }

    /** Banda de "stock bajo": min_stock × multiplicador configurable (mínimo 1). */
    public function umbralBajoStock(): int
    {
        return max($this->min_stock * (int) config('sparta.stock_umbral_bajo', 2), 1);
    }

    /** Estado de stock: normal | bajo | critico (por agotarse) | agotado. */
    public function getEstadoStockAttribute(): string
    {
        if ($this->stock <= 0) {
            return 'agotado';
        }

        if ($this->stock <= $this->min_stock) {
            return 'critico';
        }

        if ($this->stock <= $this->umbralBajoStock()) {
            return 'bajo';
        }

        return 'normal';
    }

    /**
     * Genera un SKU único en formato SP-XXX para el gimnasio indicado.
     * Busca el número más alto existente y suma uno, o comienza en SP-001.
     */
    public static function generarSku(int $gymId): string
    {
        $numeros = Product::where('gym_id', $gymId)
            ->whereNotNull('sku')
            ->where('sku', 'like', 'SP-%')
            ->pluck('sku')
            ->map(function ($sku) {
                return (int) substr($sku, 3);
            })
            ->filter()
            ->max() ?? 0;

        return 'SP-' . str_pad((string) ($numeros + 1), 3, '0', STR_PAD_LEFT);
    }
}
