<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El socio del gimnasio.
 *
 * Se separa de `users` a propósito: no todo socio tiene cuenta (los que sólo
 * pasan por recepción no necesitan login), y el expediente físico —medidas,
 * objetivos, fotos— no pertenece a la identidad de acceso.
 *
 * La altura vive en la ficha del socio y el peso en cada medición: la altura
 * es prácticamente constante y el peso es justo lo que se quiere seguir. El
 * IMC no se almacena, se calcula (ver App\Models\MemberMeasurement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Código corto que el socio teclea en recepción si no lleva el QR.
            $table->string('code', 20);
            // Token opaco del QR: se puede rotar sin tocar el código visible.
            $table->uuid('qr_token')->unique();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('document', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['M', 'F', 'O'])->nullable();
            $table->string('photo_path')->nullable();

            $table->string('emergency_contact')->nullable();
            $table->string('emergency_phone', 40)->nullable();
            $table->text('medical_notes')->nullable();

            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->date('joined_at');
            $table->enum('status', ['activo', 'inactivo', 'suspendido'])->default('activo');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['gym_id', 'code']);
            $table->index(['gym_id', 'status']);
            $table->index(['gym_id', 'last_name', 'first_name']);
        });

        // Serie temporal de mediciones: es lo que dibuja la evolución del socio.
        Schema::create('member_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->date('measured_at');
            $table->decimal('weight_kg', 5, 2);
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->decimal('body_fat_pct', 4, 1)->nullable();
            $table->decimal('muscle_mass_kg', 5, 2)->nullable();

            // Perímetros en cm; todos opcionales porque no toda toma los mide.
            $table->decimal('chest_cm', 5, 1)->nullable();
            $table->decimal('waist_cm', 5, 1)->nullable();
            $table->decimal('hip_cm', 5, 1)->nullable();
            $table->decimal('arm_cm', 5, 1)->nullable();
            $table->decimal('thigh_cm', 5, 1)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'measured_at']);
        });

        Schema::create('member_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['perder_peso', 'ganar_musculo', 'fuerza', 'resistencia', 'salud', 'otro']);
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('target_value', 8, 2)->nullable();
            $table->string('unit', 20)->nullable();
            $table->date('target_date')->nullable();
            $table->enum('status', ['activo', 'logrado', 'abandonado'])->default('activo');
            $table->timestamps();

            $table->index(['member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_goals');
        Schema::dropIfExists('member_measurements');
        Schema::dropIfExists('members');
    }
};
