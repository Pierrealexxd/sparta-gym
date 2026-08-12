<?php

namespace App\Support;

use App\Models\Gym;

/**
 * Gimnasio activo en la petición en curso.
 *
 * Hoy sólo hay uno (Sparta Gym) y se resuelve por configuración. El día que
 * la plataforma sea multi-inquilino, este es el único punto que cambia: se
 * resolverá por subdominio, por el usuario autenticado o por cabecera, y
 * todo lo que use BelongsToGym seguirá funcionando sin tocarse.
 */
class GymContext
{
    protected static ?Gym $gym = null;
    protected static bool $resuelto = false;

    public static function set(?Gym $gym): void
    {
        static::$gym = $gym;
        static::$resuelto = true;
    }

    public static function current(): ?Gym
    {
        if (! static::$resuelto) {
            // Sin scope global: al resolver el propio gimnasio no puede
            // aplicarse el filtro que depende de él.
            static::$gym = Gym::query()
                ->where('slug', config('sparta.gym_slug'))
                ->first();
            static::$resuelto = true;
        }

        return static::$gym;
    }

    public static function id(): ?int
    {
        return static::current()?->id;
    }

    /** Reinicia el contexto. Necesario entre tests y en colas de trabajos. */
    public static function forget(): void
    {
        static::$gym = null;
        static::$resuelto = false;
    }
}
