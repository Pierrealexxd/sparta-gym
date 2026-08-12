<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id', 'type', 'title', 'description',
        'target_value', 'unit', 'target_date', 'status',
    ];

    protected function casts(): array
    {
        return ['target_date' => 'date', 'target_value' => 'decimal:2'];
    }

    public function member(): BelongsTo { return $this->belongsTo(Member::class); }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('status', 'activo');
    }
}
