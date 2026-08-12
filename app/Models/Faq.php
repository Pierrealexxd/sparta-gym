<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory, BelongsToGym;

    protected $fillable = [
        'gym_id', 'question', 'answer', 'category', 'is_published', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function scopePublicados(Builder $q): Builder
    {
        return $q->where('is_published', true)->orderBy('sort_order');
    }
}
