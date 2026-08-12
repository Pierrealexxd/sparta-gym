<?php

/**
 * Configuración propia de la plataforma.
 *
 * Todo lo que hoy es "de Sparta Gym" y mañana será "del inquilino de turno"
 * pasa por aquí, para que la migración a SaaS no sea una cacería de constantes.
 */
return [

    // Gimnasio que atiende la web pública mientras la plataforma sea de uno solo.
    'gym_slug' => env('SPARTA_GYM_SLUG', 'sparta-gym'),

    // Días de aviso antes de que venza una membresía.
    'aviso_vencimiento_dias' => 7,

    // Métodos de pago admitidos, en el orden en que se muestran en recepción.
    'metodos_pago' => [
        'efectivo'      => 'Efectivo',
        'yape'          => 'Yape',
        'plin'          => 'Plin',
        'transferencia' => 'Transferencia',
        'tarjeta'       => 'Tarjeta',
        'otro'          => 'Otro',
    ],

    // Roles del sistema. El nivel ordena la jerarquía sin listar nombres.
    'roles' => [
        'admin'      => ['nombre' => 'Administrador', 'nivel' => 100],
        'recepcion'  => ['nombre' => 'Recepcionista', 'nivel' => 60],
        'entrenador' => ['nombre' => 'Entrenador',    'nivel' => 40],
        'cliente'    => ['nombre' => 'Cliente',       'nivel' => 10],
    ],

    // Rutas de aterrizaje tras iniciar sesión, por rol.
    'inicio_por_rol' => [
        'admin'      => 'admin.dashboard',
        'recepcion'  => 'admin.dashboard',
        'entrenador' => 'entrenador.dashboard',
        'cliente'    => 'cliente.dashboard',
    ],
];
