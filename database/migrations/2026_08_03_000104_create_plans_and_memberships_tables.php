<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planes (el catálogo) y membresías (lo que un socio contrató de verdad).
 *
 * El precio se copia en la membresía en el momento de la venta: si mañana
 * sube el plan, el histórico no puede reescribirse solo. Es la misma razón
 * por la que una factura guarda el importe y no un puntero al catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();

            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('duration_days');
            $table->json('features')->nullable();       // lista de beneficios para la landing

            $table->string('accent_color', 9)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_public')->default(true); // ¿se muestra en la landing?
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['gym_id', 'slug']);
            $table->index(['gym_id', 'is_active']);
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Renovación encadenada: permite reconstruir la vida del socio.
            $table->foreignId('renewed_from')->nullable()->constrained('memberships')->nullOnDelete();

            $table->string('plan_name');                 // congelado por si el plan se borra
            $table->decimal('price', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);

            $table->date('starts_at');
            $table->date('ends_at');
            $table->enum('status', ['activa', 'vencida', 'cancelada', 'congelada'])->default('activa');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Índice pensado para la consulta más frecuente del dashboard:
            // "membresías que vencen entre estas dos fechas".
            $table->index(['gym_id', 'status', 'ends_at']);
            $table->index(['member_id', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('plans');
    }
};
