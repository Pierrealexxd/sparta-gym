<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 del plan de nutrición (PLAN_NUTRICION_PROGRESO.md): "Mis platos
 * habituales" — el socio guarda una comida ya registrada como plantilla
 * (nombre + porciones) para volver a registrarla con un tap, sin escribir
 * los cuatro números de nuevo.
 *
 * Mismo patrón que meal_logs: sin gym_id, hereda el aislamiento de sede a
 * través de member_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->enum('meal_type', ['desayuno', 'almuerzo', 'cena', 'merienda']);
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('saved_meal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_meal_id')->constrained()->cascadeOnDelete();
            $table->enum('portion_type', ['palma', 'puno', 'cuenco', 'pulgar']);
            $table->unsignedTinyInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['saved_meal_id', 'portion_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_meal_items');
        Schema::dropIfExists('saved_meals');
    }
};
