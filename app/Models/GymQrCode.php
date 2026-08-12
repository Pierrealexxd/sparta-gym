<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Credencial QR de asistencia laboral de una sede. El token es un UUID v4
 * que viaja impreso; al escanearlo la sede se resuelve desde aquí, nunca
 * desde la sesión ni desde el contexto.
 *
 * A propósito NO usa BelongsToGym: el QR identifica a su gimnasio (igual
 * que `Gym` es la raíz del aislamiento), así que la búsqueda por token es
 * global y el `gym_id` resultante es quien rellena la marcación.
 */
class GymQrCode extends Model
{
    protected $fillable = [
        'gym_id', 'token', 'label', 'is_active', 'created_by', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GymQrCode $qr) {
            if (empty($qr->token)) {
                $qr->token = (string) Str::uuid();
            }
        });
    }

    public function gym(): BelongsTo { return $this->belongsTo(Gym::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    /** QR vivos (activos y sin revocar). */
    public function scopeVigente(Builder $q): Builder
    {
        return $q->where('is_active', true)->whereNull('revoked_at');
    }
}
