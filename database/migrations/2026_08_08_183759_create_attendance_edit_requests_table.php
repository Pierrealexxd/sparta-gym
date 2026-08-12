<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El entrenador puede borrar sus propios registros de asistencia sin pedir
 * permiso (es reversible: el admin puede volver a marcar la entrada si fue
 * un error), pero EDITAR la hora ya guardada no se aplica directo — queda
 * pendiente aquí hasta que el admin la aprueba o la rechaza desde la
 * campanita de notificaciones. Cambiar una hora de asistencia sin que
 * quede rastro es justo el tipo de cosa que un admin necesita poder
 * auditar, a diferencia de borrar (que es obvio que pasó: el registro ya
 * no está).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_edit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();

            $table->dateTime('checked_in_at');
            $table->dateTime('checked_out_at')->nullable();
            $table->string('reason', 255)->nullable();

            $table->enum('status', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['gym_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_edit_requests');
    }
};
