<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 del plan de nutrición (PLAN_NUTRICION_PROGRESO.md): biblioteca de
 * recetas peruanas con macros expresados en porciones de mano, no en
 * calorías por gramo.
 *
 * `recipes` copia el patrón de `exercises`: gym_id nulo = receta de la
 * biblioteca compartida, visible desde cualquier sede (ver
 * Recipe::scopeDisponibles). No usa BelongsToGym a propósito, igual que
 * Exercise — un gimnasio podría añadir las suyas encima sin que el scope
 * global se las oculte a los demás.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->text('ingredients')->nullable();
            $table->text('steps')->nullable();
            $table->unsignedSmallInteger('prep_minutes')->nullable();
            $table->unsignedTinyInteger('servings')->nullable();
            $table->json('tags')->nullable(); // ['criollo', 'sin gluten', ...]

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('recipe_portions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->enum('portion_type', ['palma', 'puno', 'cuenco', 'pulgar']);
            $table->unsignedTinyInteger('count')->default(0);
            $table->string('food_name')->nullable(); // p.ej. "arroz", "pollo"
            $table->timestamps();

            $table->unique(['recipe_id', 'portion_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_portions');
        Schema::dropIfExists('recipes');
    }
};
