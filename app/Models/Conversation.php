<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Hilo de mensajería entre dos usuarios del gimnasio.
 *
 * El chat es 1:1 por ahora: los participantes son dos cuentas y el hilo se
 * reutiliza entre ellas (no se duplica al mandar el primer mensaje). El día
 * que se quieran grupos, basta con permitir más de dos filas en
 * conversation_participants; el resto del modelo no cambia.
 */
class Conversation extends Model
{
    use HasFactory, BelongsToGym;

    protected $fillable = ['gym_id'];

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** Último mensaje del hilo, para la vista previa de la lista. */
    public function ultimoMensaje(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function esParticipante(int $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    /**
     * Total de mensajes sin leer del usuario en todos sus hilos.
     *
     * $global salta el scope de sede (BelongsToGym): el admin gestiona
     * varias sedes a la vez y no debería "perderse" un mensaje solo por
     * tener otra sede activa en el selector — ver campanita en panel.blade.php.
     */
    public static function noLeidasTotales(int $userId, bool $global = false): int
    {
        return static::query()
            ->when($global, fn ($q) => $q->sinFiltroDeGimnasio())
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->withCount(['messages as no_leidas' => fn ($q) => $q->noLeidas($userId)])
            ->get()
            ->sum('no_leidas');
    }
}
