<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hasta ahora attendance_id/staff_attendance_id eran CASCADE DELETE — tenía
 * sentido cuando borrar un Attendance/StaffAttendance era raro y nadie
 * necesitaba conservar el rastro. Con la solicitud de tipo 'eliminacion'
 * (ver 2026_08_13_130000) eso cambia: aprobar una eliminación SÍ borra el
 * registro real, y con CASCADE eso se llevaría por delante la propia
 * solicitud (y cualquier historial viejo que apuntara al mismo registro)
 * justo cuando más interesa que quede auditado.
 *
 * Se cambia a SET NULL: al borrar el registro real, las filas de
 * attendance_edit_requests que lo referenciaban quedan con el FK en NULL
 * pero sobreviven con su status/reason/reviewed_by intactos.
 *
 * El CHECK que exigía "exactamente un FK no nulo" se relaja para permitir
 * ambos en NULL una vez que la solicitud ya fue revisada (pendiente=false):
 * mientras está pendiente, sigue exigiendo el FK (lo necesitan las consultas
 * de deduplicación y la vista de aprobación); una vez aprobada/rechazada,
 * puede quedarse sin ninguno si el registro que apuntaba ya no existe.
 */
return new class extends Migration
{
    private const CHECK = 'attendance_edit_requests_destino_unico';

    public function up(): void
    {
        // dropForeign() en un try/catch: en algunos entornos (visto en
        // MariaDB local) la columna terminó con el índice de nombre
        // convencional pero sin la constraint FK real detrás — no hay nada
        // que soltar ahí, y no pasa nada si el intento falla.
        $this->intentarSoltar('attendance_id');
        $this->intentarSoltar('staff_attendance_id');

        $this->intentarEjecutar('ALTER TABLE attendance_edit_requests DROP CONSTRAINT ' . self::CHECK);

        // Datos huérfanos de cuando esta tabla nunca tuvo la FK real (visto
        // en el entorno local): sin esto, agregar la constraint de abajo
        // falla con error 1452. Es seguro poner NULL acá porque el CHECK
        // viejo ya se soltó y el nuevo (que permite ambos NULL fuera de
        // 'pendiente') todavía no se agregó.
        DB::table('attendance_edit_requests')
            ->whereNotNull('attendance_id')
            ->whereNotIn('attendance_id', DB::table('attendances')->pluck('id'))
            ->update(['attendance_id' => null]);
        DB::table('attendance_edit_requests')
            ->whereNotNull('staff_attendance_id')
            ->whereNotIn('staff_attendance_id', DB::table('staff_attendances')->pluck('id'))
            ->update(['staff_attendance_id' => null]);

        $this->intentarEjecutar('ALTER TABLE attendance_edit_requests
            ADD CONSTRAINT attendance_edit_requests_attendance_id_foreign
            FOREIGN KEY (attendance_id) REFERENCES attendances (id) ON DELETE SET NULL');
        $this->intentarEjecutar('ALTER TABLE attendance_edit_requests
            ADD CONSTRAINT attendance_edit_requests_staff_attendance_id_foreign
            FOREIGN KEY (staff_attendance_id) REFERENCES staff_attendances (id) ON DELETE SET NULL');

        $this->intentarEjecutar('ALTER TABLE attendance_edit_requests ADD CONSTRAINT ' . self::CHECK .
            " CHECK (status <> 'pendiente' OR ((attendance_id IS NOT NULL) <> (staff_attendance_id IS NOT NULL)))");
    }

    public function down(): void
    {
        $this->intentarSoltar('attendance_id');
        $this->intentarSoltar('staff_attendance_id');

        $this->intentarEjecutar('ALTER TABLE attendance_edit_requests DROP CONSTRAINT ' . self::CHECK);

        $this->intentarEjecutar('ALTER TABLE attendance_edit_requests
            ADD CONSTRAINT attendance_edit_requests_attendance_id_foreign
            FOREIGN KEY (attendance_id) REFERENCES attendances (id) ON DELETE CASCADE');
        $this->intentarEjecutar('ALTER TABLE attendance_edit_requests
            ADD CONSTRAINT attendance_edit_requests_staff_attendance_id_foreign
            FOREIGN KEY (staff_attendance_id) REFERENCES staff_attendances (id) ON DELETE CASCADE');

        $this->intentarEjecutar('ALTER TABLE attendance_edit_requests ADD CONSTRAINT ' . self::CHECK .
            ' CHECK ((attendance_id IS NOT NULL) <> (staff_attendance_id IS NOT NULL))');
    }

    private function intentarSoltar(string $columna): void
    {
        try {
            Schema::table('attendance_edit_requests', function (Blueprint $table) use ($columna) {
                $table->dropForeign([$columna]);
            });
        } catch (\Throwable) {
            // No había constraint real que soltar — sigue de largo.
        }
    }

    /**
     * Cada paso queda protegido: como el DDL de MySQL/MariaDB no es
     * transaccional, un intento anterior que falló a mitad de camino puede
     * haber dejado ya aplicado alguno de estos pasos. Reintentar la
     * migración completa no debe romperse por "ya existe" / "ya no existe".
     */
    private function intentarEjecutar(string $sql): void
    {
        try {
            DB::statement($sql);
        } catch (\Throwable) {
        }
    }
};
