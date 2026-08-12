<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contenido editable de la landing. Todo lo que se ve en la web pública sale
 * de aquí, no de texto incrustado en las vistas: el administrador puede
 * cambiar testimonios, preguntas o fotos sin tocar Blade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();

            $table->string('author');
            $table->string('role')->nullable();           // "Socio desde 2021"
            $table->text('content');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->string('photo_path')->nullable();

            $table->boolean('is_published')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['gym_id', 'is_published']);
        });

        // Instalaciones y máquinas comparten forma; el tipo las distingue.
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['instalacion', 'maquina'])->default('instalacion');
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('icon', 40)->nullable();
            $table->json('specs')->nullable();            // {marca, unidades, zona}

            $table->boolean('is_published')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['gym_id', 'type', 'is_published']);
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->text('answer');
            $table->string('category', 40)->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['gym_id', 'is_published']);
        });

        Schema::create('gallery_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('path');
            $table->string('category', 40)->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['gym_id', 'is_published']);
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('interested_in')->nullable();  // plan sobre el que pregunta

            $table->enum('status', ['nuevo', 'leido', 'atendido', 'descartado'])->default('nuevo');
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('gallery_images');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('testimonials');
    }
};
