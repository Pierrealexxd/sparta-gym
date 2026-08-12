<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diario de comidas por porciones de mano — Fase 2 de
 * PLAN_NUTRICION_PROGRESO.md. Sin calorías ni gramos: el socio anota
 * cuántas palmas/puños/cuencos/pulgares comió en cada comida.
 *
 * Sin gym_id propio, a propósito: sigue el mismo patrón que
 * member_measurements y member_goals (ver 2026_08_03_000103), que tampoco
 * lo tienen — el aislamiento por sede ya lo hereda de member_id (Member sí
 * usa BelongsToGym). Duplicar gym_id acá abriría la puerta a que se
 * desincronice del que ya tiene el socio, sin ganar nada a cambio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();

            $table->enum('meal_type', ['desayuno', 'almuerzo', 'cena', 'merienda']);
            $table->date('logged_on');
            $table->string('notes', 500)->nullable();

            $table->timestamps();

            // Un registro por comida por día: registrar de nuevo actualiza
            // el mismo, no acumula filas — igual que member_measurements
            // con [member_id, measured_at].
            $table->unique(['member_id', 'meal_type', 'logged_on']);
            $table->index(['member_id', 'logged_on']);
        });

        Schema::create('meal_log_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_log_id')->constrained()->cascadeOnDelete();

            $table->enum('portion_type', ['palma', 'puno', 'cuenco', 'pulgar']);
            $table->unsignedTinyInteger('count');
            // Reservado para cuando la Fase 3 permita registrar una comida
            // a partir de una receta — hoy el formulario no lo pide.
            $table->string('food_name', 120)->nullable();

            $table->timestamps();

            // Una fila por tipo de porción dentro de la misma comida: el
            // formulario es 4 campos numéricos, no una lista de items.
            $table->unique(['meal_log_id', 'portion_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_log_items');
        Schema::dropIfExists('meal_logs');
    }
};
