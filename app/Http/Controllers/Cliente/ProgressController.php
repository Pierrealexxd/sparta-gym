<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\MemberGoal;
use App\Models\MemberMeasurement;
use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function __invoke(Request $request): View
    {
        // La rutina se quitó de acá (PROMPT-EJECUCION-MI-RUTINA.md, Parte 1):
        // Progreso es medición, la rutina completa vive en /cliente/rutina —
        // ya no hace falta cargar 'routines' en este controlador.
        $socio = $request->user()->member()->with([
            'measurements' => fn ($q) => $q->orderBy('measured_at'),
            'goals' => fn ($q) => $q->activos(),
        ])->firstOrFail();

        $medidasPag = $socio->measurements()->latest('measured_at')->paginate(10);

        return view('cliente.progreso', [
            'medidasPag'      => $medidasPag,
            'socio'           => $socio,
            'ultima'  => $socio->measurements->last(),
            'primera' => $socio->measurements->first(),
            'hoy'     => $socio->measurements->first(fn (MemberMeasurement $m) => $m->measured_at->isToday()),
            'graficoPeso' => [
                'labels' => $socio->measurements->map(fn (MemberMeasurement $m) => $m->measured_at->format('d/m/y'))->all(),
                'data'   => $socio->measurements->map(fn (MemberMeasurement $m) => (float) $m->weight_kg)->all(),
            ],
            'graficoGrasa' => [
                'labels' => $socio->measurements->whereNotNull('body_fat_pct')->map(fn (MemberMeasurement $m) => $m->measured_at->format('d/m/y'))->values()->all(),
                'data'   => $socio->measurements->whereNotNull('body_fat_pct')->map(fn (MemberMeasurement $m) => (float) $m->body_fat_pct)->values()->all(),
            ],
            // Fase 4 (PLAN-RUTINAS-PERSONALIZADAS.md): peso y grasa en el
            // mismo gráfico, en dos ejes, para ver si de verdad se
            // correlacionan — reemplaza los dos gráficos sueltos de antes
            // (ver progreso.blade.php), que decían lo mismo por separado.
            'graficoCombinado' => [
                'tipo'   => 'line',
                'labels' => $socio->measurements->map(fn (MemberMeasurement $m) => $m->measured_at->format('d/m/y'))->all(),
                'tituloEjeY'  => 'Peso (kg)',
                'tituloEjeY1' => '% Grasa',
                'datasets' => [
                    [
                        'label'  => 'Peso (kg)',
                        'data'   => $socio->measurements->map(fn (MemberMeasurement $m) => (float) $m->weight_kg)->all(),
                        'token'  => '--sangre',
                    ],
                    [
                        'label'  => '% Grasa',
                        'data'   => $socio->measurements->map(fn (MemberMeasurement $m) => $m->body_fat_pct !== null ? (float) $m->body_fat_pct : null)->all(),
                        'token'  => '--brasa',
                        'eje'    => 'y1',
                        'relleno'=> false,
                    ],
                ],
            ],
            'metas' => $socio->goals->map(function (MemberGoal $meta) use ($socio) {
                $primera = $socio->measurements->first();
                $ultima  = $socio->measurements->last();

                $progreso = null;
                if ($primera && $ultima && $primera->id !== $ultima->id && $meta->target_value) {
                    $inicio  = (float) $primera->weight_kg;
                    $actual  = (float) $ultima->weight_kg;
                    $objetivo = (float) $meta->target_value;

                    $progreso = match ($meta->type) {
                        'perder_peso'  => $inicio - $objetivo > 0
                            ? round(min(1, max(0, ($inicio - $actual) / ($inicio - $objetivo))), 2)
                            : null,
                        'ganar_musculo' => $objetivo - $inicio > 0
                            ? round(min(1, max(0, ($actual - $inicio) / ($objetivo - $inicio))), 2)
                            : null,
                        default => null,
                    };
                }

                // "¿Cuánto me falta?" en unidades reales, no sólo el % —
                // responde a la pregunta que el % no contesta por sí solo
                // (PROMPT-EJECUCION-MI-RUTINA.md, Parte 3). Sólo necesita la
                // última medida, no dos como el % de tendencia de arriba.
                $restante = null;
                if ($ultima && $meta->target_value && in_array($meta->type, ['perder_peso', 'ganar_musculo'], true)) {
                    $actual   = (float) $ultima->weight_kg;
                    $objetivo = (float) $meta->target_value;

                    $restante = $meta->type === 'perder_peso'
                        ? round($actual - $objetivo, 1)
                        : round($objetivo - $actual, 1);
                }

                return ['meta' => $meta, 'progreso' => $progreso, 'restante' => $restante];
            }),
        ]);
    }

    /**
     * El socio se registra sus propias medidas. Una por día: si ya anotó
     * esa fecha, la actualiza en vez de acumular ruido en la curva.
     * El registro completo (perímetros, masa muscular) sigue siendo de
     * recepción y del entrenador.
     */
    public function guardar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'measured_at' => ['required', 'date', 'before_or_equal:today'],
            'weight_kg'   => ['required', 'numeric', 'min:20', 'max:400'],
            'body_fat_pct'=> ['nullable', 'numeric', 'min:2', 'max:70'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ]);

        $socio = $request->user()->member()->firstOrFail();

        $medida = $socio->measurements()->updateOrCreate(
            ['member_id' => $socio->id, 'measured_at' => $datos['measured_at']],
            $datos + ['recorded_by' => $request->user()->id],
        );

        // Su entrenador se entera (si lo tiene) — el propio socio ya tiene su
        // toast flash de confirmación.
        app(NotificationService::class)->notificarMedidaCliente($medida);

        return back()->with('exito', 'Medida registrada.');
    }
}
