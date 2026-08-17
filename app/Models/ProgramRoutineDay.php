<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramRoutineDay extends Model
{
    use HasFactory;

    protected $fillable = ['program_routine_id', 'name', 'focus', 'sort_order'];

    public function routine(): BelongsTo { return $this->belongsTo(ProgramRoutine::class, 'program_routine_id'); }

    public function exercises(): HasMany
    {
        return $this->hasMany(ProgramRoutineExercise::class)->orderBy('sort_order');
    }
}
