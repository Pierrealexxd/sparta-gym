<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programas públicos de la landing (Fase 0 de PLAN-RUTINAS-PERSONALIZADAS.md).
 * Mismo patrón que Exercise/Recipe: gym_id nulo = biblioteca compartida,
 * visible desde todos los gimnasios. Sin BelongsToGym a propósito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug', 60)->unique();
            $table->string('name', 100);
            $table->string('tagline', 200)->nullable();
            $table->enum('objective', [
                'ganar_masa', 'perder_grasa', 'fuerza', 'resistencia', 'salud', 'otro',
            ]);
            $table->text('description');
            $table->json('highlights')->nullable();
            $table->string('icon', 40)->nullable();
            $table->string('accent_color', 9)->nullable();
            $table->unsignedTinyInteger('duration_weeks')->nullable();
            $table->enum('difficulty', ['principiante', 'intermedio', 'avanzado'])->default('intermedio');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'is_public']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
