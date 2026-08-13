<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coordenadas opcionales de la marcación por geolocalización (ver
 * AttendanceController::marcarGeo). Nullable a propósito: las marcaciones
 * manuales y por QR no mandan lat/lng, y las marcas viejas quedan en NULL
 * sin romper nada — no se usan para validar nada, solo quedan de dato
 * complementario para reportes/auditoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_attendances', function (Blueprint $table) {
            $table->decimal('location_lat', 10, 8)->nullable()->after('method');
            $table->decimal('location_lng', 11, 8)->nullable()->after('location_lat');
        });
    }

    public function down(): void
    {
        Schema::table('staff_attendances', function (Blueprint $table) {
            $table->dropColumn(['location_lat', 'location_lng']);
        });
    }
};
