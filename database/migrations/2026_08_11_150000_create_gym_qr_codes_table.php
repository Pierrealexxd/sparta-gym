<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credenciales QR de asistencia LABORAL del staff. Cada gimnasio tiene un QR
 * impreso en recepción; escanearlo es la alternativa a marcar a mano desde el
 * panel del entrenador. El token es la única pieza que viaja dentro del QR:
 * nunca el gym_id, porque ese sale del QR en el momento del marcado.
 *
 * Rotación y revocación: al generar un QR nuevo el anterior queda inactivo
 * con `revoked_at` marcado, de modo que un papel impreso que se pierde deja
 * de servir al instante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('token', 36)->unique();
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->index(['gym_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_qr_codes');
    }
};
