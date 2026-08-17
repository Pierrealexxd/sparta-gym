<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alerta de stock pendiente, una por producto. `type` es 'bajo' (dentro de
 * la banda de stock bajo o por debajo del umbral) o 'agotado' (stock en
 * cero). Se borra sola cuando el stock vuelve por encima de la banda — la
 * tabla solo contiene lo que pide atención (ver StockAlertService).
 */
class StockAlert extends Model
{
    use HasFactory, BelongsToGym;

    protected $fillable = [
        'gym_id', 'product_id', 'type',
    ];

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}