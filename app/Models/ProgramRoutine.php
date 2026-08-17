<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plantilla de rutina de un programa. Ver migración
 * 2026_08_16_130000_create_program_routines_table.php.
 */
class ProgramRoutine extends Model
{
    use HasFactory;

    protected $fillable = ['program_id', 'name', 'notes', 'sort_order'];

    public function program(): BelongsTo { return $this->belongsTo(Program::class); }

    public function days(): HasMany
    {
        return $this->hasMany(ProgramRoutineDay::class)->orderBy('sort_order');
    }
}
