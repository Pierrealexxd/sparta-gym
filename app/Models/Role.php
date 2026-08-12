<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Role extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'level'];

    public function users(): HasMany { return $this->hasMany(User::class); }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * Lista plana de slugs de permiso. Se consulta en cada comprobación de
     * autorización y sólo cambia cuando un administrador edita los permisos,
     * así que se cachea por rol y se invalida en `olvidarCache()`.
     */
    public function permisosCacheados(): array
    {
        return Cache::remember(
            "rol.{$this->id}.permisos",
            now()->addHour(),
            fn () => $this->permissions()->pluck('slug')->all()
        );
    }

    public function olvidarCache(): void
    {
        Cache::forget("rol.{$this->id}.permisos");
    }

    protected static function booted(): void
    {
        // Cambiar el rol invalida su caché sin que nadie tenga que acordarse.
        static::saved(fn (Role $rol) => $rol->olvidarCache());
        static::deleted(fn (Role $rol) => $rol->olvidarCache());
    }
}
