<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `method` recuerda si una marcación se hizo a mano desde el panel del
 * entrenador o escaneando el QR de la sede. No se usa para decidir nada
 * (la regla de entrada/salida es la misma), pero alimenta los reportes
 * y permite auditar qué vía registró cada fichada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_attendances', function (Blueprint $table) {
            $table->enum('method', ['manual', 'qr'])->default('manual')->after('turno');
        });
    }

    public function down(): void
    {
        Schema::table('staff_attendances', function (Blueprint $table) {
            $table->dropColumn('method');
        });
    }
};
