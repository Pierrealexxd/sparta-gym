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

    protected $fillable = ['gym_id', 'conversation_id', 'sender_id', 'body', 'read_at'];

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
}
