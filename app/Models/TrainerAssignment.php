<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['trainer_id', 'member_id', 'assigned_at', 'ended_at', 'notes'];

    protected function casts(): array
    {
        return ['assigned_at' => 'date', 'ended_at' => 'date'];
    }

    public function trainer(): BelongsTo { return $this->belongsTo(Trainer::class); }
    public function member(): BelongsTo  { return $this->belongsTo(Member::class); }

    public function scopeVigentes(Builder $q): Builder
    {
        return $q->whereNull('ended_at');
    }
}
