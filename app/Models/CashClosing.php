<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashClosing extends Model
{
    use HasFactory, BelongsToGym;

    protected $fillable = [
        'gym_id', 'closed_by', 'business_date',
        'expected_total', 'expected_cash', 'counted_cash', 'difference',
        'breakdown', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'business_date'  => 'date',
            'expected_total' => 'decimal:2',
            'expected_cash'  => 'decimal:2',
            'counted_cash'   => 'decimal:2',
            'difference'     => 'decimal:2',
            'breakdown'      => 'array',
        ];
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
