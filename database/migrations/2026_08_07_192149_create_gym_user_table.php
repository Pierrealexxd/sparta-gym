<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Puente entre usuarios y sedes. `users.gym_id` sigue siendo la sede de
 * origen de cada trabajador de planta (recepción, entrenador, cliente);
 * esta tabla es sólo para las cuentas que necesitan alternar entre varias
 * sedes (el dueño, y quien él decida) — ver docs/multi-sede.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'gym_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_user');
    }
};
