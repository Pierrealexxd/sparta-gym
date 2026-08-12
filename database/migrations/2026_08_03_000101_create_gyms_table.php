<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raíz del modelo multi-gimnasio. Todo dato operativo cuelga de un gym_id,
 * de modo que convertir la plataforma en SaaS es dar de alta una fila más
 * y no reescribir el esquema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gyms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();

            $table->string('logo_path')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('whatsapp', 40)->nullable();

            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('PE');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Estructuras flexibles: cambian por gimnasio y no merecen tabla propia.
            $table->json('schedule')->nullable();   // [{dia, abre, cierra}]
            $table->json('socials')->nullable();    // {instagram, facebook, tiktok...}
            $table->json('settings')->nullable();   // moneda, zona horaria, preferencias

            $table->string('currency', 3)->default('PEN');
            $table->string('timezone')->default('America/Lima');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gyms');
    }
};
