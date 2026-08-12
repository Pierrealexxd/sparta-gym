<?php

namespace Database\Seeders;

use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Arranque de producción: solo la cuenta de administrador, nada de datos
 * ficticios (sin socios, sin asistencias, sin ventas). El dueño carga todo
 * lo demás desde el panel.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $gym = Gym::where('slug', config('sparta.gym_slug'))->firstOrFail();

        User::updateOrCreate(
            ['email' => 'admin@spartagym.pe'],
            [
                'gym_id'    => $gym->id,
                'role_id'   => Role::where('slug', 'admin')->value('id'),
                'name'      => 'Administrador Sparta',
                'password'  => 'sparta2026',   // el cast 'hashed' lo cifra
                'is_active' => true,
            ]
        );
    }
}
