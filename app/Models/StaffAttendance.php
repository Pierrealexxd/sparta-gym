<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Asistencia LABORAL: cuándo un miembro del staff (hoy solo entrenadores)
 * marcó su entrada/salida de trabajo. No confundir con `Attendance`, que
 * es el torno de entrada de socios — son dos cosas distintas a propósito.
 */
class StaffAttendance extends Model
{
    use BelongsToGym;

    protected $fillable = ['gym_id', 'user_id', 'clocked_in_at', 'clocked_out_at', 'turno', 'method'];

    protected function casts(): array
    {
        return [
            'clocked_in_at'  => 'datetime',
            'clocked_out_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function editRequests(): HasMany { return $this->hasMany(AttendanceEditRequest::class); }

    public function scopeDentro(Builder $q): Builder
    {
        return $q->whereNull('clocked_out_at');
    }

    public function getTurnoLegibleAttribute(): string
    {
        return match ($this->turno) {
            'manana' => 'Mañana',
            'tarde'  => 'Tarde',
            'doble'  => 'Doble turno',
            default  => ucfirst($this->turno),
        };
    }

    public function getMethodLegibleAttribute(): string
    {
        return match ($this->method) {
            'qr'     => 'QR',
            default  => 'Manual',
        };
    }
}
