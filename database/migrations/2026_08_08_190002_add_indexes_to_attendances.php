<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para las dos consultas nuevas del módulo de asistencias:
 * el calendario por registrador (entrenador) y el historial por cliente
 * ("¿este socio ya entró hoy?").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['gym_id', 'registered_by', 'attended_on']);
            $table->index(['member_id', 'attended_on']);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['gym_id', 'registered_by', 'attended_on']);
            $table->dropIndex(['member_id', 'attended_on']);
        });
    }
};
