<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutineDay extends Model
{
    use HasFactory;

    protected $fillable = ['routine_id', 'name', 'focus', 'sort_order'];

    public function routine(): BelongsTo { return $this->belongsTo(Routine::class); }

    public function exercises(): HasMany
    {
        return $this->hasMany(RoutineExercise::class)->orderBy('sort_order');
    }
}
