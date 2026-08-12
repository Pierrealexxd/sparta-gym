<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\MealLog;
use App\Models\MemberGoal;
use App\Models\MemberMeasurement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProgressController extends Controller
{
    /** Los cuatro tipos de comida que soporta el diario (Fase 2). */
    private const TIPOS_COMIDA = ['desayuno', 'almuerzo', 'cena', 'merienda'];

    public function __invoke(Request $request): View
    {
        $socio = $request->user()->member()->with([
            'measurements' => fn ($q) => $q->orderBy('measured_at'),
            'goals' => fn ($q) => $q->activos(),
            'mealLogs' => fn ($q) => $q->delDia()->with('items'),
            // Fase 4 del plan de nutrición: "Mis platos habituales".
            'savedMeals' => fn ($q) => $q->with('items')->latest(),
        ])->firstOrFail();

        // Por tipo de comida, para precargar el formulario de "Hoy" con lo
        // que ya se registró (y no partir de cero si vuelve a entrar).
        $comidasHoy = $socio->mealLogs->keyBy('meal_type');

        // Suma del día, para comparar contra la referencia de la Fase 1
        // (Member::porcionesDiarias) — mismo cálculo que <x-discos> ya usa
        // para "cuánto llevas de tu meta".
        $totalHoy = ['palma' => 0, 'puno' => 0, 'cuenco' => 0, 'pulgar' => 0];
        foreach ($socio->mealLogs as $comida) {
            foreach ($comida->conteo as $tipo => $cantidad) {
                $totalHoy[$tipo] += $cantidad;
            }
        }

        return view('cliente.progreso', [
            'comidasHoy'      => $comidasHoy,
            'totalHoy'        => $totalHoy,
            'objetivoDiario'  => $socio->porcionesDiarias(),
            'tiposComida'     => self::TIPOS_COMIDA,
            'platosHabituales'=> $socio->savedMeals,
            'socio'   => $socio,
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

                return ['meta' => $meta, 'progreso' => $progreso];
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

        $socio->measurements()->updateOrCreate(
            ['member_id' => $socio->id, 'measured_at' => $datos['measured_at']],
            $datos + ['recorded_by' => $request->user()->id],
        );

        return back()->with('exito', 'Medida registrada.');
    }

    /**
     * Diario de comidas por porciones (Fase 2): un registro por comida por
     * día — volver a enviar la misma actualiza los conteos, no acumula
     * filas (mismo criterio que guardar() con el peso). Sin calorías ni
     * gramos: cuatro números, uno por porción de mano.
     */
    public function guardarComida(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'meal_type' => ['required', Rule::in(self::TIPOS_COMIDA)],
            'palma'     => ['nullable', 'integer', 'min:0', 'max:20'],
            'puno'      => ['nullable', 'integer', 'min:0', 'max:20'],
            'cuenco'    => ['nullable', 'integer', 'min:0', 'max:20'],
            'pulgar'    => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);

        $socio = $request->user()->member()->firstOrFail();

        $comida = $socio->mealLogs()->updateOrCreate(
            ['member_id' => $socio->id, 'meal_type' => $datos['meal_type'], 'logged_on' => now()->toDateString()],
            [],
        );

        foreach (['palma', 'puno', 'cuenco', 'pulgar'] as $tipo) {
            $comida->items()->updateOrCreate(
                ['meal_log_id' => $comida->id, 'portion_type' => $tipo],
                ['count' => $datos[$tipo] ?? 0],
            );
        }

        return back()->with('exito', ucfirst($datos['meal_type']) . ' registrado.');
    }
}
