<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Attendance extends Model
{
    use HasFactory, BelongsToGym;

    protected $fillable = [
        'gym_id', 'member_id', 'registered_by',
        'checked_in_at', 'checked_out_at', 'method',
    ];

    // attended_on queda fuera de $fillable a propósito: la calcula MySQL como
    // columna generada y escribirla desde PHP la desincronizaría de checked_in_at.

    protected function casts(): array
    {
        return [
            'checked_in_at'  => 'datetime',
            'checked_out_at' => 'datetime',
            'attended_on'    => 'date',
        ];
    }

    public function member(): BelongsTo       { return $this->belongsTo(Member::class); }
    public function registeredBy(): BelongsTo { return $this->belongsTo(User::class, 'registered_by'); }

    public function scopeDeHoy(Builder $q): Builder
    {
        return $q->where('attended_on', now()->toDateString());
    }

    /** Quien sigue dentro del gimnasio: entró y no ha marcado salida. */
    public function scopeDentro(Builder $q): Builder
    {
        return $q->whereNull('checked_out_at');
    }

    public function scopeRegistradasPor(Builder $q, int $userId): Builder
    {
        return $q->where('registered_by', $userId);
    }

    public function scopeDeCliente(Builder $q, int $memberId): Builder
    {
        return $q->where('member_id', $memberId);
    }

    public function scopeEntreFechas(Builder $q, Carbon $desde, Carbon $hasta): Builder
    {
        return $q->whereBetween('checked_in_at', [$desde, $hasta]);
    }

    public function getDuracionMinutosAttribute(): ?int
    {
        return $this->checked_out_at
            ? (int) $this->checked_in_at->diffInMinutes($this->checked_out_at)
            : null;
    }
}
