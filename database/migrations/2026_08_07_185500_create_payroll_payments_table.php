<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dinero que sale hacia el equipo: sueldos, comisiones, bonos. Registro
 * manual del pago hecho a cada trabajador (ver docs/gestion-personal.md,
 * Parte F) — no un motor de cálculo de nómina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();

            // Qué se paga: sueldo/comisión/bono, texto libre como en payments.
            $table->string('concept');
            $table->decimal('amount', 10, 2);
            $table->enum('method', [
                'efectivo', 'transferencia', 'yape', 'plin', 'tarjeta', 'otro',
            ])->default('efectivo');

            // Período que cubre el pago, distinto de la fecha en que se pagó.
            $table->date('period_start');
            $table->date('period_end');
            $table->dateTime('paid_at');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['gym_id', 'paid_at']);
            $table->index(['gym_id', 'user_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payments');
    }
};
