<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dinero que entra y gente que entra: las dos series que alimentan el
 * dashboard y los cierres de caja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('concept');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('PEN');

            // Métodos reales del mercado peruano, no una lista genérica.
            $table->enum('method', [
                'efectivo', 'transferencia', 'yape', 'plin', 'tarjeta', 'otro',
            ])->default('efectivo');

            $table->string('reference')->nullable();      // nº de operación
            $table->string('receipt_path')->nullable();   // comprobante subido
            $table->enum('status', ['pagado', 'pendiente', 'anulado'])->default('pagado');

            // Fecha del pago separada de created_at: recepción registra en
            // diferido y el arqueo debe cuadrar por fecha real, no de captura.
            $table->dateTime('paid_at');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['gym_id', 'paid_at']);
            $table->index(['gym_id', 'status', 'paid_at']);
            $table->index(['gym_id', 'method']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->dateTime('checked_in_at');
            $table->dateTime('checked_out_at')->nullable();
            $table->enum('method', ['qr', 'codigo', 'busqueda', 'manual'])->default('qr');

            // Columna generada: agrupar por día es la consulta estrella de los
            // informes, y hacerlo con DATE(checked_in_at) impide usar índice.
            $table->date('attended_on')->storedAs('DATE(checked_in_at)');

            $table->timestamps();

            $table->index(['gym_id', 'attended_on']);
            $table->index(['member_id', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('payments');
    }
};
