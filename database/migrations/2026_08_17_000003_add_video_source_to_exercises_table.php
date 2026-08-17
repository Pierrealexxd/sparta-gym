<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 1 de PLAN-GUIAS-EJERCICIO.md: soporte multi-fuente de video.
 * `video_url` se mantiene tal cual (YouTube/Vimeo/GDrive/URL genérica);
 * `video_source` indica cómo interpretarlo. Default 'youtube' → todos los
 * ejercicios existentes (que ya traían solo enlaces de YouTube) siguen
 * funcionando exactamente igual sin tocar un solo registro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('video_source', 20)->default('youtube')->after('video_url');
            // youtube | vimeo | gdrive | url | upload
            $table->string('video_file_path')->nullable()->after('video_source');
            // Solo para video_source = 'upload'.
        });
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn(['video_source', 'video_file_path']);
        });
    }
};
