<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complemento no listado en PLAN-GUIAS-EJERCICIO.md pero necesario para que
 * la Fase 1 (overrides) realmente llegue al cliente: "Mi Rutina" no lee
 * `program_routine_exercises` (la plantilla del admin) sino `routine_exercises`
 * (la copia real, clonada al asignar el programa — ver
 * Cliente\ProgramController::asignar). Sin estas mismas columnas acá, un
 * override cargado en la plantilla nunca se vería en el panel del socio.
 * Mismas columnas, mismos defaults, misma regla de "nullable = hereda del
 * Exercise" que en program_routine_exercises.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_exercises', function (Blueprint $table) {
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
        Schema::table('routine_exercises', function (Blueprint $table) {
            $table->dropColumn([
                'guide_video_url', 'guide_video_source', 'guide_video_file_path',
                'guide_description', 'guide_tips', 'guide_common_mistakes',
            ]);
        });
    }
};
