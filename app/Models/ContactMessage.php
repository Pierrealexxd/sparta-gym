<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use HasFactory, BelongsToGym;

    protected $fillable = [
        'gym_id', 'name', 'email', 'phone', 'subject',
        'message', 'interested_in', 'status', 'ip',
    ];

    public function scopeNuevos(Builder $q): Builder
    {
        return $q->where('status', 'nuevo');
    }
}
