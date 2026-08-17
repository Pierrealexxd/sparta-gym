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

    // Banda de "stock bajo" del inventario: un producto entra en ese estado
    // cuando su stock queda por debajo de min_stock × este multiplicador
    // (mínimo 1). Por debajo de min_stock ya es "por agotarse" y dispara la
    // alerta al administrador (ver StockAlertService).
    'stock_umbral_bajo' => 2,

    // Notificaciones unificadas (campanita + toasts, ver plan-notificaciones-toast.md).
    // La vigencia acota la tabla: lo que pasa de 24 h se borra por comando
    // programado (notificaciones:limpiar) y ninguna consulta lo muestra
    // aunque el cron no haya corrido.
    'notificaciones' => [
        'vigencia_horas'     => 24,        // horas de vida de una notificación
        'polling_segundos'   => 5,         // ciclo del badge + toasts realtime
        'toast_duracion'     => [          // segundos visibles por prioridad
            'baja'  => 4,
            'media' => 5,
            'alta'  => 8,
        ],
        'toast_max_visibles' => 4,         // toasts simultáneos antes de descartar
    ],

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
