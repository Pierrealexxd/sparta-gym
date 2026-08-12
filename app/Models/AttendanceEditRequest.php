<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEditRequest extends Model
{
    use BelongsToGym;

    protected $fillable = [
        'gym_id', 'attendance_id', 'staff_attendance_id', 'requested_by',
        'checked_in_at', 'checked_out_at', 'reason',
        'status', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at'  => 'datetime',
            'checked_out_at' => 'datetime',
            'reviewed_at'    => 'datetime',
        ];
    }

    public function attendance(): BelongsTo { return $this->belongsTo(Attendance::class); }
    public function staffAttendance(): BelongsTo { return $this->belongsTo(StaffAttendance::class); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewedBy(): BelongsTo  { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function scopePendientes(Builder $q): Builder
    {
        return $q->where('status', 'pendiente');
    }

    public function scopeHistorial(Builder $q): Builder
    {
        return $q->where('status', '!=', 'pendiente');
    }

    /**
     * El registro al que apunta la solicitud: una asistencia de cliente o
     * una marcación laboral, según cuál de los dos FKs esté presente.
     * Cargar ambos FKs (with(['attendance','staffAttendance'])) para que no
     * dispare consultas extra: la restricción CHECK garantiza uno solo.
     */
    public function getObjetivoAttribute(): Attendance|StaffAttendance|null
    {
        return $this->attendance ?? $this->staffAttendance;
    }

    public function getTipoAttribute(): string
    {
        return ($this->relationLoaded('attendance') && $this->attendance) ? 'cliente' : 'staff';
    }
}
