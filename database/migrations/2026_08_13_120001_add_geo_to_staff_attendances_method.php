<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Suma 'geo' al enum `method` para que la marcación por geolocalización
 * (AttendanceController::marcarGeo) quede auditable como una vía aparte de
 * 'manual' y 'qr', igual de trazable que las otras dos.
 *
 * MODIFY COLUMN en crudo porque el proyecto es MySQL-only (ver nota en
 * database/migrations/2026_08_08_185901_create_staff_attendances_table.php
 * y DEPLOY_RENDER.md): Doctrine DBAL no hace falta y así se evita la
 * dependencia extra solo para este cambio de enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE staff_attendances MODIFY method ENUM('manual', 'qr', 'geo') NOT NULL DEFAULT 'manual'");
    }

    public function down(): void
    {
        DB::statement("UPDATE staff_attendances SET method = 'manual' WHERE method = 'geo'");
        DB::statement("ALTER TABLE staff_attendances MODIFY method ENUM('manual', 'qr') NOT NULL DEFAULT 'manual'");
    }
};
