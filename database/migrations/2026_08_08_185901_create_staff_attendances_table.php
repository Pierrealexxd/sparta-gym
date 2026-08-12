<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asistencia LABORAL del staff (hoy solo entrenadores) — cuándo llegó a
 * trabajar, no cuándo llegó un socio a entrenar. Esas dos cosas viven
 * separadas a propósito: `attendances` sigue siendo el torno de entrada
 * de socios (QR/código), esto es otra cosa por completo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->dateTime('clocked_in_at');
            $table->dateTime('clocked_out_at')->nullable();
            $table->enum('turno', ['manana', 'tarde', 'doble'])->default('manana');

            $table->timestamps();

            $table->index(['gym_id', 'clocked_in_at']);
            $table->index(['user_id', 'clocked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendances');
    }
};
