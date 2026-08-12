<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Instalaciones y máquinas. Comparten forma y sólo se distinguen por `type`,
 * así que una sola tabla evita duplicar el CRUD entero.
 */
class Facility extends Model
{
    use HasFactory, BelongsToGym;

    protected $fillable = [
        'gym_id', 'type', 'name', 'tagline', 'description',
        'image_path', 'icon', 'specs', 'is_published', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['specs' => 'array', 'is_published' => 'boolean'];
    }

    public function scopePublicados(Builder $q): Builder
    {
        return $q->where('is_published', true)->orderBy('sort_order');
    }

    public function scopeInstalaciones(Builder $q): Builder
    {
        return $q->where('type', 'instalacion');
    }

    public function scopeMaquinas(Builder $q): Builder
    {
        return $q->where('type', 'maquina');
    }
}
