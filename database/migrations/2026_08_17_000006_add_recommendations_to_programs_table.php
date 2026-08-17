<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 3 de PLAN-GUIAS-EJERCICIO.md: recomendaciones del programa
 * (alimentación, recuperación, hidratación, suplementos), como arrays JSON
 * de strings cortos — mismo patrón que `highlights` en esta tabla o
 * `muscle_groups` en exercises. Todas nullable: un programa sin
 * recomendaciones simplemente no muestra la sección en "Mi Rutina".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->json('nutrition_tips')->nullable()->after('highlights');
            $table->json('recovery_tips')->nullable()->after('nutrition_tips');
            $table->json('hydration_tips')->nullable()->after('recovery_tips');
            $table->json('supplements_tips')->nullable()->after('hydration_tips');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['nutrition_tips', 'recovery_tips', 'hydration_tips', 'supplements_tips']);
        });
    }
};
