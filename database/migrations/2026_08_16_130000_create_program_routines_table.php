<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas de rutina por programa (Parte 2 de PLAN-PROGRAMAS.md): el admin
 * define días y ejercicios por programa; al asignar el programa a un socio
 * (Cliente\ProgramController::asignar) se clonan como una Routine real.
 * Nunca tienen member_id — no son rutinas reales hasta que se copian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('program_id');
        });

        Schema::create('program_routine_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_routine_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('focus', 100)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('program_routine_id');
        });

        Schema::create('program_routine_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_routine_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('sets')->default(3);
            $table->string('reps', 20)->default('8-10');
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->unsignedSmallInteger('time_seconds')->nullable();
            $table->unsignedSmallInteger('rest_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('program_routine_day_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_routine_exercises');
        Schema::dropIfExists('program_routine_days');
        Schema::dropIfExists('program_routines');
    }
};
