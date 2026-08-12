<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC propio y deliberadamente pequeño: cuatro roles fijos y una lista de
 * permisos granulares por rol. Se resuelve con Gate, sin paquetes externos,
 * porque el modelo de permisos aquí es estable y conocido de antemano.
 *
 * Los roles son globales (mismo significado en todos los gimnasios); lo que
 * cambia por gimnasio es a quién se le asigna, y eso vive en users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();       // admin, recepcion, entrenador, cliente
            $table->string('name');
            $table->string('description')->nullable();
            // Jerarquía numérica: permite comparaciones sin listar roles a mano.
            $table->unsignedTinyInteger('level')->default(0);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();       // clientes.crear, pagos.ver...
            $table->string('name');
            $table->string('group', 40)->index();       // agrupa la UI de permisos
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        // El usuario se ata a un gimnasio y a un rol.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('gym_id')->nullable()->after('id')
                  ->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->after('gym_id')
                  ->constrained()->nullOnDelete();

            $table->string('phone', 40)->nullable()->after('email');
            $table->string('avatar_path')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('avatar_path');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->softDeletes();

            $table->index(['gym_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['gym_id']);
            $table->dropForeign(['role_id']);
            $table->dropIndex(['gym_id', 'role_id']);
            $table->dropColumn([
                'gym_id', 'role_id', 'phone', 'avatar_path',
                'is_active', 'last_login_at', 'deleted_at',
            ]);
        });

        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
