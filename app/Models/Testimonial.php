<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Testimonial extends Model
{
    use HasFactory, BelongsToGym;

    protected $fillable = [
        'gym_id', 'member_id', 'author', 'role', 'content',
        'rating', 'photo_path', 'is_published', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'rating' => 'integer'];
    }

    public function member(): BelongsTo { return $this->belongsTo(Member::class); }

    public function scopePublicados(Builder $q): Builder
    {
        return $q->where('is_published', true)->orderBy('sort_order');
    }

    public function scopePendientes(Builder $q): Builder
    {
        return $q->where('is_published', false)->latest();
    }
}
