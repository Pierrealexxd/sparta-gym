<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hasta ahora esta tabla solo servía para solicitudes de EDICIÓN: borrar
 * una marcación propia se aplicaba directo (ver docblock viejo en
 * AttendanceController), porque "es obvio que pasó, el registro ya no
 * está". Se decidió que no: el entrenador también debe pedir permiso para
 * eliminar, igual que para editar, y que le llegue al admin por la misma
 * campanita — ver pierre.md / conversación del 13-08-2026.
 *
 * `tipo` distingue 'edicion' (aplica checked_in_at/checked_out_at nuevos al
 * aprobar) de 'eliminacion' (borra el registro al aprobar, no necesita
 * horas nuevas). `checked_in_at` pasa a nullable porque una solicitud de
 * eliminación no propone una hora nueva — la hora "actual" que se muestra
 * en la cola de aprobación sigue viniendo del registro objetivo mientras
 * sigue vivo (pendiente de revisión), no de esta tabla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_edit_requests', function (Blueprint $table) {
            $table->enum('tipo', ['edicion', 'eliminacion'])->default('edicion')->after('requested_by');
        });

        DB::statement('ALTER TABLE attendance_edit_requests MODIFY checked_in_at DATETIME NULL');
    }

    public function down(): void
    {
        DB::statement("DELETE FROM attendance_edit_requests WHERE tipo = 'eliminacion' AND checked_in_at IS NULL");
        DB::statement('ALTER TABLE attendance_edit_requests MODIFY checked_in_at DATETIME NOT NULL');

        Schema::table('attendance_edit_requests', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
