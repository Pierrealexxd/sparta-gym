<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las solicitudes de corrección eran para `attendances` (socios) por un
 * malentendido de alcance — se corrige apuntándolas a `staff_attendances`
 * (la asistencia laboral real). La tabla se creó hoy mismo, sin datos
 * reales todavía, así que no hace falta migrar filas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_edit_requests', function (Blueprint $table) {
            $table->dropForeign(['attendance_id']);
            $table->dropColumn('attendance_id');
        });

        Schema::table('attendance_edit_requests', function (Blueprint $table) {
            $table->foreignId('staff_attendance_id')->after('gym_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_edit_requests', function (Blueprint $table) {
            $table->dropForeign(['staff_attendance_id']);
            $table->dropColumn('staff_attendance_id');
        });

        Schema::table('attendance_edit_requests', function (Blueprint $table) {
            $table->foreignId('attendance_id')->after('gym_id')->constrained()->cascadeOnDelete();
        });
    }
};
