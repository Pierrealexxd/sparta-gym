<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Libro mayor del inventario. `products.stock` es el saldo; esto es el
 * histórico que permite reconstruirlo si algo se descuadra.
 */
class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'user_id', 'type', 'quantity',
        'stock_after', 'reason', 'reference_type', 'reference_id',
    ];

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function reference(): MorphTo { return $this->morphTo(); }
}
