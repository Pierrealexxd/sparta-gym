<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'gym_id', 'role_id', 'name', 'email', 'password',
        'phone', 'avatar_path', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function gym(): BelongsTo  { return $this->belongsTo(Gym::class); }
    public function role(): BelongsTo { return $this->belongsTo(Role::class); }
    public function member(): HasOne  { return $this->hasOne(Member::class); }
    public function trainer(): HasOne { return $this->hasOne(Trainer::class); }

    /**
     * Todas las sedes a las que puede entrar: quien tiene 'sedes.ver-todas'
     * (admin siempre, por el atajo de tienePermiso()) ve TODAS las sedes
     * reales; el resto solo la suya de origen (`gym_id`). Asignar un
     * trabajador a varias sedes sin el permiso global es trabajo futuro
     * vía un pivot real — ver docs/multi-sede.md.
     */
    public function sedesDisponibles(): Collection
    {
        if ($this->tienePermiso('sedes.ver-todas')) {
            return Gym::orderBy('name')->get();
        }

        return $this->gym ? collect([$this->gym]) : collect();
    }

    /* ---------------------------------------------------------- */
    /* Autorización                                               */
    /* ---------------------------------------------------------- */

    public function tieneRol(string ...$slugs): bool
    {
        return in_array($this->role?->slug, $slugs, true);
    }

    public function esAdmin(): bool      { return $this->tieneRol('admin'); }
    public function esEntrenador(): bool { return $this->tieneRol('entrenador'); }
    public function esCliente(): bool    { return $this->tieneRol('cliente'); }

    /**
     * El administrador no se enumera: puede todo por definición. Para el resto
     * se consulta la lista del rol, que va cacheada (ver Role::permisosCacheados).
     */
    public function tienePermiso(string $slug): bool
    {
        if (! $this->role) {
            return false;
        }

        return $this->role->slug === 'admin'
            || in_array($slug, $this->role->permisosCacheados(), true);
    }

    /** Ruta a la que se envía al usuario tras autenticarse. */
    public function rutaDeInicio(): string
    {
        return config('sparta.inicio_por_rol.' . $this->role?->slug, 'landing');
    }

    public function getInicialesAttribute(): string
    {
        return collect(explode(' ', $this->name))
            ->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->implode('');
    }
}
