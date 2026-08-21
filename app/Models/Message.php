<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory, BelongsToGym;

    protected $fillable = [
        'gym_id', 'conversation_id', 'sender_id', 'body', 'read_at',
        'attachment_path', 'attachment_name', 'attachment_type',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function sender(): BelongsTo       { return $this->belongsTo(User::class, 'sender_id'); }

    /** Mensajes que el usuario aún no ha abierto: de otros y sin leer. */
    public function scopeNoLeidas(Builder $q, int $userId): Builder
    {
        return $q->where('sender_id', '!=', $userId)->whereNull('read_at');
    }

    public function tieneAdjunto(): bool
    {
        return $this->attachment_path !== null;
    }

    public function urlAdjunto(): string
    {
        return asset('storage/' . $this->attachment_path);
    }

    /** Lo que muestra la previsualización de la lista cuando no hay texto. */
    public function etiquetaAdjunto(): string
    {
        return $this->attachment_type === 'imagen' ? 'Imagen' : 'Archivo';
    }
}
