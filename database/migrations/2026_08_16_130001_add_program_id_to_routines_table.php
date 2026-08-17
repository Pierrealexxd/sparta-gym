<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * program_id nullable a propósito: `routines` ya tiene filas reales creadas
 * por entrenadores sin programa asociado (ver PROMPT-EJECUCION-PROGRAMAS.md
 * regla 9) — solo las rutinas nacidas de Cliente\ProgramController::asignar
 * la traen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('member_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
        });
    }
};
