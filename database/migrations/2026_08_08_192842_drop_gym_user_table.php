<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * gym_user nunca se llenó (User::gyms() y sedesDisponibles() siempre
 * devolvían vacío) — deuda muerta que invitaba a confusión de multi-sede.
 * Ver docs/multi-sede.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gym_user');
    }

    public function down(): void
    {
        Schema::create('gym_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'gym_id']);
        });
    }
};
