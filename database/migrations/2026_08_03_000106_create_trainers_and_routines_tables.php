<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entrenadores, biblioteca de ejercicios y rutinas.
 *
 * La rutina se modela en tres niveles —rutina → día → ejercicio— porque un
 * plan real es "lunes empuje, miércoles tirón": aplanarlo a una sola lista
 * obligaría a repetir el día en cada fila y haría imposible reordenar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('specialty')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo_path')->nullable();
            $table->json('certifications')->nullable();
            $table->json('schedule')->nullable();
            $table->json('socials')->nullable();
            $table->unsignedTinyInteger('years_experience')->nullable();

            $table->boolean('is_public')->default(true);   // ¿aparece en la landing?
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['gym_id', 'is_active']);
        });

        // Asignación con vigencia: un socio puede cambiar de entrenador y el
        // histórico tiene que sobrevivir al cambio.
        Schema::create('trainer_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->date('assigned_at');
            $table->date('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'ended_at']);
            $table->index(['trainer_id', 'ended_at']);
        });

        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            // gym_id nulo = ejercicio de la biblioteca global, compartido por
            // todos los gimnasios. Los propios de un gimnasio llevan su id.
            $table->foreignId('gym_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();

            $table->json('muscle_groups')->nullable();     // ['pecho', 'triceps']
            $table->enum('level', ['principiante', 'intermedio', 'avanzado'])->default('principiante');
            $table->string('equipment')->nullable();
            $table->enum('category', ['fuerza', 'cardio', 'movilidad', 'core', 'funcional'])->default('fuerza');

            $table->string('image_path')->nullable();
            $table->string('video_url')->nullable();
            $table->text('common_mistakes')->nullable();
            $table->text('tips')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['gym_id', 'slug']);
            $table->index('category');
        });

        Schema::create('routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('objective')->nullable();
            $table->text('notes')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->enum('status', ['activa', 'finalizada', 'borrador'])->default('activa');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['member_id', 'status']);
        });

        Schema::create('routine_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->constrained()->cascadeOnDelete();
            $table->string('name');                        // "Día 1 · Empuje"
            $table->string('focus')->nullable();           // "Pecho y tríceps"
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['routine_id', 'sort_order']);
        });

        Schema::create('routine_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('sets')->default(3);
            $table->string('reps', 20)->nullable();        // "8-10" o "al fallo": texto, no número
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->unsignedSmallInteger('time_seconds')->nullable();
            $table->unsignedSmallInteger('rest_seconds')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['routine_day_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_exercises');
        Schema::dropIfExists('routine_days');
        Schema::dropIfExists('routines');
        Schema::dropIfExists('exercises');
        Schema::dropIfExists('trainer_assignments');
        Schema::dropIfExists('trainers');
    }
};
