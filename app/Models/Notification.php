<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notificación unificada del panel (campanita + toasts) — ver
 * docs/plan-notificaciones-toast.md. Una fila por destinatario y por evento;
 * el dedupe por (usuario, tipo, sujeto) vive en NotificationService.
 *
 * Vigencia máxima de 24 h: el comando `notificaciones:limpiar` borra lo
 * viejo y toda consulta pasa por el scope `vigentes()`, así el cajón nunca
 * muestra algo vencido aunque el cron no haya corrido.
 */
class Notification extends Model
{
    use HasFactory, BelongsToGym;

    protected $fillable = [
        'gym_id', 'user_id', 'type', 'subject_id', 'title', 'body',
        'icon', 'priority', 'action_url', 'data', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'data'    => 'array',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Solo las que siguen dentro de la vigencia (24 h por defecto). */
    public function scopeVigentes(Builder $q): Builder
    {
        return $q->where('created_at', '>=', now()->subHours((int) config('sparta.notificaciones.vigencia_horas', 24)));
    }

    public function scopeNoLeidas(Builder $q): Builder
    {
        return $q->whereNull('read_at');
    }
}
