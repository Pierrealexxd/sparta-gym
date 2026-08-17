<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberGoal;
use App\Models\MemberMeasurement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $socio = $request->user()->member()->with([
            'currentMembership.plan',
            'currentAssignment.trainer.user',
            'goals' => fn ($q) => $q->activos(),
            // Rediseño de programas (PLAN-RUTINAS-PERSONALIZADAS.md): la
            // rutina puede venir de un programa asignado en la landing —
            // se carga 'program' para poder mostrar su nombre en "Mi rutina".
            'routines' => fn ($q) => $q->activas()->with(['days.exercises.exercise', 'program']),
            'sales' => fn ($q) => $q->completadas()->latest('sold_at')->take(8),
            'attendances' => fn ($q) => $q->latest('checked_in_at')->take(5),
            'measurements' => fn ($q) => $q->orderBy('measured_at'),
            'testimonial',
        ])->firstOrFail();

        // ── KPIs ──────────────────────────────────────────────
        // Corrección al plan: se agrupa/filtra por `attended_on` (columna
        // generada STORED de `checked_in_at`, indexada junto a member_id)
        // en vez de envolver checked_in_at en DATE()/MONTH() — ver el
        // comentario de la migración 2026_08_03_000105.
        $asistenciasMes = $socio->attendances()
            ->whereMonth('attended_on', now()->month)
            ->whereYear('attended_on', now()->year)
            ->count();

        $mesAnterior = now()->subMonthNoOverflow();
        $asistenciasMesAnterior = $socio->attendances()
            ->whereMonth('attended_on', $mesAnterior->month)
            ->whereYear('attended_on', $mesAnterior->year)
            ->count();

        $deltaAsistencia = $asistenciasMesAnterior > 0
            ? round((($asistenciasMes - $asistenciasMesAnterior) / $asistenciasMesAnterior) * 100)
            : null;

        $racha = $this->calcularRacha($socio);

        $ultimaMedida = $socio->measurements->last();
        $primeraMedida = $socio->measurements->first();
        $deltaPeso = ($primeraMedida && $ultimaMedida && $primeraMedida->id !== $ultimaMedida->id)
            ? round($ultimaMedida->weight_kg - $primeraMedida->weight_kg, 1)
            : null;

        $duracionPlan = $socio->currentMembership?->plan?->duration_days;
        $diasRestantesPct = ($duracionPlan && $duracionPlan > 0)
            ? max(0, min(100, round((1 - ($socio->days_left ?? 0) / $duracionPlan) * 100)))
            : 0;

        $kpis = [
            'diasRestantes'    => $socio->days_left,
            'diasRestantesPct' => $diasRestantesPct,
            'asistenciasMes'   => $asistenciasMes,
            'deltaAsistencia'  => $deltaAsistencia,
            'racha'            => $racha,
            'pesoActual'       => $ultimaMedida?->weight_kg,
            'deltaPeso'        => $deltaPeso,
        ];

        // ── GRÁFICA 1: Asistencia semanal (12 semanas) ────────
        $inicioVentana = now()->subWeeks(11)->startOfWeek();
        $asistenciasPorSemana = $socio->attendances()
            ->where('attended_on', '>=', $inicioVentana->toDateString())
            ->selectRaw('YEARWEEK(attended_on, 1) as semana, COUNT(*) as total')
            ->groupBy('semana')
            ->pluck('total', 'semana');

        $semanasLabels = [];
        $semanasData = [];
        for ($i = 11; $i >= 0; $i--) {
            $fecha = now()->subWeeks($i);
            // Corrección al plan: YEARWEEK(x, 1) devuelve el año ISO
            // (format('o')), no el año calendario (format('Y')) — divergen
            // en el cambio de año y rellenaban toda la gráfica con ceros.
            $clave = (int) ($fecha->format('o') . $fecha->format('W'));
            $semanasLabels[] = 'Sem ' . (12 - $i);
            $semanasData[] = (int) ($asistenciasPorSemana[$clave] ?? 0);
        }

        $hayAsistenciaSemanal = array_sum($semanasData) > 0;

        $graficoAsistencia = [
            'tipo' => 'bar',
            'labels' => $semanasLabels,
            'datasets' => [[
                'label' => 'Visitas',
                'data' => $semanasData,
                'token' => '--sangre',
            ]],
            'tituloEjeY' => 'Visitas',
        ];

        // ── GRÁFICA 2: Frecuencia por día de la semana (3 meses) ──
        $asistenciasPorDia = $socio->attendances()
            ->where('attended_on', '>=', now()->subMonths(3)->toDateString())
            ->selectRaw('DAYOFWEEK(attended_on) as dia, COUNT(*) as total')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $diasNombres = [1 => 'Dom', 2 => 'Lun', 3 => 'Mar', 4 => 'Mié', 5 => 'Jue', 6 => 'Vie', 7 => 'Sáb'];
        $diasData = array_map(fn ($d) => (int) ($asistenciasPorDia[$d] ?? 0), range(1, 7));
        $hayFrecuenciaDias = array_sum($diasData) > 0;

        $diaMax = null;
        if ($hayFrecuenciaDias) {
            $indiceMax = array_search(max($diasData), $diasData);
            $diaMax = array_values($diasNombres)[$indiceMax];
        }

        $graficoFrecuencia = [
            'tipo' => 'bar',
            // Se lee del config global, no del dataset (ver graficos.js).
            'horizontal' => true,
            'labels' => array_values($diasNombres),
            'datasets' => [[
                'label' => 'Visitas',
                'data' => $diasData,
                'token' => '--brasa',
            ]],
            'tituloEjeY' => 'Visitas',
        ];

        // ── GRÁFICA 3: Progreso corporal (peso + grasa) ───────
        // Mismo patrón de eje dual que ProgressController::graficoCombinado
        // (no se toca ese controlador), recortado a las últimas 12 tomas
        // para que la mini-gráfica del dashboard no se sature.
        $medidas = $socio->measurements->slice(-12)->values();
        $hayMedidas = $medidas->isNotEmpty();

        $graficoProgreso = [
            'tipo' => 'line',
            'labels' => $medidas->map(fn (MemberMeasurement $m) => $m->measured_at->format('d/m'))->all(),
            'tituloEjeY' => 'Peso (kg)',
            'tituloEjeY1' => '% Grasa',
            'datasets' => [
                [
                    'label' => 'Peso (kg)',
                    'data' => $medidas->map(fn (MemberMeasurement $m) => (float) $m->weight_kg)->all(),
                    'token' => '--sangre',
                ],
                [
                    'label' => '% Grasa',
                    'data' => $medidas->map(fn (MemberMeasurement $m) => $m->body_fat_pct !== null ? (float) $m->body_fat_pct : null)->all(),
                    'token' => '--brasa',
                    'eje' => 'y1',
                    'relleno' => false,
                ],
            ],
        ];

        // ── GRÁFICA 4: Inversión acumulada (12 meses) ─────────
        $ventasPorMes = $socio->sales()
            ->completadas()
            ->where('sold_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw('DATE_FORMAT(sold_at, "%Y-%m") as mes, SUM(total) as total')
            ->groupBy('mes')
            ->orderBy('mes')
            ->pluck('total', 'mes');

        $acumulado = 0;
        $inversionLabels = [];
        $inversionData = [];
        for ($i = 11; $i >= 0; $i--) {
            $fecha = now()->subMonths($i);
            $clave = $fecha->format('Y-m');
            $acumulado += (float) ($ventasPorMes[$clave] ?? 0);
            $inversionLabels[] = $fecha->translatedFormat('M');
            $inversionData[] = round($acumulado, 2);
        }

        $hayInversion = $acumulado > 0;

        $graficoInversion = [
            'tipo' => 'line',
            'labels' => $inversionLabels,
            'datasets' => [[
                'label' => 'Inversión (S/)',
                'data' => $inversionData,
                'token' => '--bronce',
                'relleno' => true,
            ]],
            'tituloEjeY' => 'S/',
        ];

        // ── Mis metas ──────────────────────────────────────────
        // Mismo cálculo de progreso que ProgressController@__invoke (no se
        // toca ese controlador): % avanzado entre la primera y la última
        // medida de peso hacia el valor objetivo de cada meta.
        $metas = $socio->goals->map(function (MemberGoal $meta) use ($primeraMedida, $ultimaMedida) {
            $progreso = null;
            if ($primeraMedida && $ultimaMedida && $primeraMedida->id !== $ultimaMedida->id && $meta->target_value) {
                $inicio = (float) $primeraMedida->weight_kg;
                $actual = (float) $ultimaMedida->weight_kg;
                $objetivo = (float) $meta->target_value;

                $progreso = match ($meta->type) {
                    'perder_peso' => $inicio - $objetivo > 0
                        ? round(min(1, max(0, ($inicio - $actual) / ($inicio - $objetivo))), 2)
                        : null,
                    'ganar_musculo' => $objetivo - $inicio > 0
                        ? round(min(1, max(0, ($actual - $inicio) / ($objetivo - $inicio))), 2)
                        : null,
                    default => null,
                };
            }

            return ['meta' => $meta, 'progreso' => $progreso];
        });

        return view('cliente.dashboard', [
            'socio'                => $socio,
            'kpis'                 => $kpis,
            'graficoAsistencia'    => $graficoAsistencia,
            'hayAsistenciaSemanal' => $hayAsistenciaSemanal,
            'graficoFrecuencia'    => $graficoFrecuencia,
            'hayFrecuenciaDias'    => $hayFrecuenciaDias,
            'diaMaxFrecuencia'     => $diaMax,
            'graficoProgreso'      => $graficoProgreso,
            'hayMedidas'           => $hayMedidas,
            'graficoInversion'     => $graficoInversion,
            'hayInversion'         => $hayInversion,
            'rutinaActiva'         => $socio->routines->first(),
            'metas'                => $metas,
        ]);
    }

    /**
     * Semanas consecutivas con al menos una visita.
     *
     * Corrección al plan (2 problemas reales del loop de 52 `exists()`):
     *  1. Rendimiento: una sola query trae las semanas con asistencia de
     *     los últimos 12 meses (DISTINCT YEARWEEK), y el conteo de
     *     consecutivas se hace en PHP — 1 query en vez de 52 por carga.
     *  2. Racha que se rompe cada lunes: si la semana en curso todavía no
     *     tiene visita (p. ej. lunes por la mañana), NO se cuenta pero
     *     TAMPOCO corta la racha — se empieza a contar desde la semana
     *     anterior, que si tuvo visita la semana pasada sigue viva.
     */
    private function calcularRacha(Member $socio): int
    {
        $semanasAsistidas = $socio->attendances()
            ->where('attended_on', '>=', now()->subWeeks(52)->startOfWeek()->toDateString())
            ->selectRaw('DISTINCT YEARWEEK(attended_on, 1) as semana')
            ->pluck('semana')
            ->map(fn ($s) => (int) $s)
            ->flip();

        $cursor = now()->startOfWeek();
        $claveSemana = fn (Carbon $f) => (int) ($f->format('o') . $f->format('W'));

        if (! isset($semanasAsistidas[$claveSemana($cursor)])) {
            $cursor = $cursor->subWeek();
        }

        $racha = 0;
        for ($i = 0; $i < 52; $i++) {
            if (! isset($semanasAsistidas[$claveSemana($cursor)])) {
                break;
            }

            $racha++;
            $cursor = $cursor->copy()->subWeek();
        }

        return $racha;
    }
}
