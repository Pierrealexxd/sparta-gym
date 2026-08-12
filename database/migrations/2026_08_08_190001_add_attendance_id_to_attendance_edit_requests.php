<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las solicitudes de corrección vuelven a poder apuntar a una asistencia
 * de CLIENTE (`attendances`) además de a una marcación laboral
 * (`staff_attendances`). Exactamente una de las dos debe estar presente:
 * la restricción CHECK lo garantiza a nivel de base, no en el código.
 */
return new class extends Migration
{
    private const CHECK = 'attendance_edit_requests_destino_unico';

    public function up(): void
    {
        // La corrección de staff sigue siendo la mayoría; solo cambia la
        // columna a nullable para que la fila quede anclada a attendance_id
        // o a staff_attendance_id, nunca a ambas (CHECK abajo).
        DB::statement('ALTER TABLE attendance_edit_requests MODIFY staff_attendance_id BIGINT UNSIGNED NULL');

        Schema::table('attendance_edit_requests', function (Blueprint $table) {
            $table->foreignId('attendance_id')->nullable()->after('gym_id')
                ->constrained()->cascadeOnDelete();
            $table->index(['gym_id', 'status', 'attendance_id']);
        });

        DB::statement('ALTER TABLE attendance_edit_requests ADD CONSTRAINT ' . self::CHECK .
            ' CHECK ((attendance_id IS NOT NULL) <> (staff_attendance_id IS NOT NULL))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE attendance_edit_requests DROP CHECK ' . self::CHECK);

        Schema::table('attendance_edit_requests', function (Blueprint $table) {
            $table->dropIndex(['gym_id', 'status', 'attendance_id']);
            $table->dropConstrainedForeignId('attendance_id');
        });

        DB::statement('ALTER TABLE attendance_edit_requests MODIFY staff_attendance_id BIGINT UNSIGNED NOT NULL');
    }
};
