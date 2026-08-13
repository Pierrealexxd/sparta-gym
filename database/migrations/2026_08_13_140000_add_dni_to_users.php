<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DNI/cédula del staff (hoy solo entrenadores lo usan): se guarda una vez
 * en el perfil y de ahí lo toma el flujo de escaneo QR de asistencia
 * laboral para mostrarlo junto al nombre — no se vuelve a pedir en cada
 * marcación. Nullable porque los clientes no lo necesitan y las cuentas
 * de staff existentes no lo tienen cargado todavía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('dni', 20)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dni');
        });
    }
};
