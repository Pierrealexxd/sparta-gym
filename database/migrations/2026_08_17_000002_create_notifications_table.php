<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 60);
            // Id del recurso que originó el evento (producto, conversación,
            // venta, solicitud…) — la clave del dedupe vive en el servicio
            // (NotificationService), no en un índice único: MySQL no permite
            // NULLs múltiples en una clave única y el dedupe es por fila sin
            // leer, no por fila a secas.
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('title', 120);
            $table->string('body', 255);
            $table->string('icon', 40)->default('campana');
            $table->enum('priority', ['alta', 'media', 'baja'])->default('media');
            $table->string('action_url', 255)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Cómo se lee: contador por usuario, dedupe por usuario+tipo+sujeto,
            // y borrado por antigüedad (vigencia de 24 h).
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'type', 'subject_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
