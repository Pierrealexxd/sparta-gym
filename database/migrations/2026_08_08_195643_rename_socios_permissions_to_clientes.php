<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renombra los slugs de permisos socios.* → clientes.* y el grupo "Socios"
 * → "Clientes" (terminología, ver docs/plan-restructuracion.md). Solo
 * renombra: las asignaciones rol↔permiso (role_permission) apuntan por
 * permission_id, así que sobreviven el cambio de slug sin tocarlas.
 */
return new class extends Migration
{
    private const MAPA = [
        'socios.ver'      => 'clientes.ver',
        'socios.crear'    => 'clientes.crear',
        'socios.editar'   => 'clientes.editar',
        'socios.eliminar' => 'clientes.eliminar',
    ];

    public function up(): void
    {
        foreach (self::MAPA as $antiguo => $nuevo) {
            DB::table('permissions')->where('slug', $antiguo)->update(['slug' => $nuevo]);
        }

        DB::table('permissions')->where('group', 'Socios')->update(['group' => 'Clientes']);
    }

    public function down(): void
    {
        foreach (self::MAPA as $antiguo => $nuevo) {
            DB::table('permissions')->where('slug', $nuevo)->update(['slug' => $antiguo]);
        }

        DB::table('permissions')->where('group', 'Clientes')->update(['group' => 'Socios']);
    }
};
