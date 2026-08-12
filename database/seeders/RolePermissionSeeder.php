<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Roles y permisos. Es idempotente: se puede volver a lanzar tras añadir un
 * permiso nuevo sin duplicar nada ni perder asignaciones manuales.
 */
class RolePermissionSeeder extends Seeder
{
    /** Permisos agrupados tal y como se mostrarán en la pantalla de roles. */
    private const PERMISOS = [
        'Clientes' => [
            'clientes.ver'      => 'Ver clientes',
            'clientes.crear'    => 'Registrar clientes',
            'clientes.editar'   => 'Editar clientes',
            'clientes.eliminar' => 'Eliminar clientes',
        ],
        'Membresías' => [
            'membresias.ver'      => 'Ver membresías',
            'membresias.gestionar'=> 'Crear y renovar membresías',
            'planes.gestionar'    => 'Gestionar planes',
        ],
        'Pagos' => [
            'pagos.ver'      => 'Ver pagos',
            'pagos.registrar'=> 'Registrar pagos',
            'pagos.anular'   => 'Anular pagos',
            'caja.ver'       => 'Ver arqueo de caja',
            'caja.cerrar'    => 'Cerrar caja del día',
        ],
        'Asistencia' => [
            'asistencia.registrar' => 'Registrar entradas y salidas',
            'asistencia.ver'       => 'Ver historial de asistencia',
            'asistencia.aprobar'   => 'Aprobar solicitudes de edición de asistencia',
        ],
        'Entrenamiento' => [
            'entrenadores.gestionar' => 'Gestionar entrenadores',
            'rutinas.ver'            => 'Ver rutinas',
            'rutinas.gestionar'      => 'Crear y editar rutinas',
            'ejercicios.gestionar'   => 'Gestionar biblioteca de ejercicios',
            'medidas.registrar'      => 'Registrar medidas y progreso',
        ],
        'Inventario' => [
            'inventario.ver'      => 'Ver inventario',
            'inventario.gestionar'=> 'Gestionar productos y stock',
            'ventas.registrar'    => 'Registrar ventas',
        ],
        'Nutrición' => [
            'recetas.gestionar' => 'Gestionar biblioteca de recetas',
        ],
        'Sistema' => [
            'reportes.ver'          => 'Ver reportes',
            'reportes.exportar'     => 'Exportar reportes',
            'configuracion.editar'  => 'Editar configuración del gimnasio',
            'usuarios.gestionar'    => 'Gestionar usuarios y permisos',
            'planilla.gestionar'    => 'Gestionar planilla de pagos',
            'web.editar'            => 'Editar contenido de la web',
            'sedes.ver-todas'       => 'Ver el panel con todas las sedes a la vez',
            'sedes.gestionar'       => 'Crear y editar sedes',
        ],
    ];

    /**
     * Qué puede cada rol. El administrador no aparece: `User::tienePermiso()`
     * le concede todo sin enumerar, así que listarlo aquí sería mentira a
     * medias (una lista que hay que mantener y que nadie consulta).
     */
    private const ASIGNACIONES = [
        'recepcion' => [
            'clientes.ver', 'clientes.crear', 'clientes.editar',
            'membresias.ver', 'membresias.gestionar',
            'pagos.ver', 'pagos.registrar', 'caja.ver',
            'asistencia.registrar', 'asistencia.ver',
            'inventario.ver', 'ventas.registrar',
            'reportes.ver',
        ],
        'entrenador' => [
            'clientes.ver', 'clientes.crear',
            'pagos.registrar',
            'asistencia.ver', 'asistencia.registrar',
            'rutinas.ver', 'rutinas.gestionar',
            'ejercicios.gestionar', 'medidas.registrar',
            'reportes.ver',
            'ventas.registrar',
        ],
        'cliente' => [
            'rutinas.ver',
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISOS as $grupo => $permisos) {
            foreach ($permisos as $slug => $nombre) {
                Permission::updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $nombre, 'group' => $grupo]
                );
            }
        }

        foreach (config('sparta.roles') as $slug => $datos) {
            $rol = Role::updateOrCreate(
                ['slug' => $slug],
                ['name' => $datos['nombre'], 'level' => $datos['nivel']]
            );

            if ($slug === 'admin') {
                continue;
            }

            $ids = Permission::whereIn('slug', self::ASIGNACIONES[$slug] ?? [])->pluck('id');
            $rol->permissions()->sync($ids);
        }
    }
}
