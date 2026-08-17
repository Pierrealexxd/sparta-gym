<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 1 de PLAN-GUIAS-EJERCICIO.md: overrides de guía por ejercicio dentro
 * de una rutina base de programa. Todos nullable: sin override, se hereda
 * del Exercise (ver ProgramRoutineExercise::getEffective*Attribute), así que
 * las filas existentes no cambian de comportamiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_routine_exercises', function (Blueprint $table) {
            $table->string('guide_video_url')->nullable()->after('notes');
            $table->string('guide_video_source', 20)->default('youtube')->after('guide_video_url');
            $table->string('guide_video_file_path')->nullable()->after('guide_video_source');
            $table->text('guide_description')->nullable()->after('guide_video_file_path');
            $table->text('guide_tips')->nullable()->after('guide_description');
            $table->text('guide_common_mistakes')->nullable()->after('guide_tips');
        });
    }

    public function down(): void
    {
        Schema::table('program_routine_exercises', function (Blueprint $table) {
            $table->dropColumn([
                'guide_video_url', 'guide_video_source', 'guide_video_file_path',
                'guide_description', 'guide_tips', 'guide_common_mistakes',
            ]);
        });
    }
};
