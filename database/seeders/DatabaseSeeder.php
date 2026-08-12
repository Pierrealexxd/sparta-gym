<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * El orden importa: los roles existen antes que los usuarios que los usan,
     * y el gimnasio antes que cualquier dato que cuelgue de él.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SpartaGymSeeder::class,
            ExerciseSeeder::class,
            RecipeSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
