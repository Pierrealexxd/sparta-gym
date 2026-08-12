<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El cierre de caja del día: lo esperado según `payments` frente a lo que
 * de verdad se contó en efectivo. Un registro por gimnasio y día —no se
 * puede cerrar el mismo día dos veces— para que quede historial de a
 * cuánto cuadró (o no) cada jornada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->date('business_date');

            // Lo que dicen los pagos registrados ese día, y lo que de verdad
            // hay en la caja física. Sólo el efectivo se cuenta a mano: los
            // demás métodos ya quedan conciliados por el propio medio de pago.
            $table->decimal('expected_total', 10, 2);
            $table->decimal('expected_cash', 10, 2);
            $table->decimal('counted_cash', 10, 2);
            $table->decimal('difference', 10, 2);

            // Desglose por método ese día, para no tener que recalcularlo si
            // luego se anula o edita un pago fuera de rango.
            $table->json('breakdown')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            // Un cierre por gimnasio y día: cerrar dos veces el mismo día no
            // tiene sentido de negocio.
            $table->unique(['gym_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closings');
    }
};
