<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adjuntos en la mensajería: imágenes y archivos sueltos. El cuerpo pasa a
 * opcional cuando el mensaje es solo el archivo — la previsualización de la
 * lista lo reemplaza por una etiqueta según el tipo ("Imagen", "Archivo").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('body');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            // Categoría resuelta en el backend, no el mime crudo:
            // imagen | archivo. La vista solo decide cómo pintar.
            $table->string('attachment_type')->nullable()->after('attachment_name');

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'created_at']);
            $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_type']);
        });
    }
};
