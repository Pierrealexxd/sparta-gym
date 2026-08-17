<?php

use App\Models\Membership;
use App\Services\NotificationService;
use App\Support\GymContext;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/* ------------------------------------------------------------------ */
/* Notificaciones (ver docs/plan-notificaciones-toast.md)             */
/* ------------------------------------------------------------------ */

// La vigencia es de 24 h: lo que pasa de ahí se borra cada hora. Las
// consultas del servicio además filtran por vigencia, así que aunque este
// cron no corra, el cajón nunca muestra algo vencido.
Schedule::command('notificaciones:limpiar')->hourly();

// Aviso diario de vencimientos: al socio (por vencer / vencida) y al staff
// (conteo agrupado). Idempotente gracias al dedupe por (usuario, tipo,
// sujeto) del servicio: correrlo dos veces el mismo día no duplica avisos.
Schedule::command('notificaciones:vencimientos')->dailyAt('08:00');

Artisan::command('notificaciones:limpiar', function () {
    $borradas = app(NotificationService::class)->limpiarVencidas();
    $this->comment("Notificaciones vencidas eliminadas: {$borradas}");
})->purpose('Elimina notificaciones con más de 24 h de vida');

Artisan::command('notificaciones:vencimientos', function () {
    $servicio = app(NotificationService::class);
    $umbral   = (int) config('sparta.aviso_vencimiento_dias', 7);

    $porVencer = Membership::with('member')->vigentes()->vencenEn($umbral)->get();
    $vencidas  = Membership::with('member')->vencidas()->get();

    $avisos = 0;

    foreach ($porVencer as $membresia) {
        $socio = $membresia->member;
        if (! $socio?->user_id) {
            continue;
        }

        $servicio->disparar(
            $socio->user,
            'membresia.por-vencer',
            'Tu membresía está por vencer',
            'Te quedan ' . $membresia->dias_restantes . ' días (vence el '
                . $membresia->ends_at->translatedFormat('d M') . '). Renueva para no perder días.',
            'reloj',
            'media',
            $socio->id,
            route('cliente.dashboard'),
        );
        $avisos++;
    }

    foreach ($vencidas as $membresia) {
        $socio = $membresia->member;
        if (! $socio?->user_id) {
            continue;
        }

        $servicio->disparar(
            $socio->user,
            'membresia.vencida',
            'Tu membresía venció',
            'Tu membresía venció el ' . $membresia->ends_at->translatedFormat('d M')
                . '. Pasa por recepción para renovarla.',
            'reloj',
            'alta',
            $socio->id,
            route('cliente.dashboard'),
        );
        $avisos++;
    }

    // Staff: un solo aviso agrupado por día (subject nulo → el dedupe lo
    // refresca a diario con el conteo nuevo, sin acumular filas).
    $total = $porVencer->count() + $vencidas->count();
    if ($total > 0) {
        $servicio->dispararA(
            $servicio->staffDeSede(GymContext::id()),
            'membresia.vencimientos',
            'Membresías por vencer',
            "{$total} membresías entre por vencer y vencidas. Revisa el dashboard.",
            'reloj',
            $vencidas->isNotEmpty() ? 'alta' : 'media',
            null,
            route('admin.dashboard'),
        );
    }

    $this->comment("Avisos de vencimiento enviados: {$avisos}");
})->purpose('Avisa por notificación las membresías por vencer y vencidas');
