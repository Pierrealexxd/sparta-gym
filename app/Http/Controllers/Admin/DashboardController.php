<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Sale;
use App\Support\GymContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * El panel de control. Todo lo que aquí se muestra es una consulta directa
 * —nada de tablas de resumen precalculadas— porque el volumen de un
 * gimnasio (miles de filas, no millones) no lo justifica todavía. Si algún
 * día pesa, el primer paso es cachear este método, no rediseñarlo.
 */
class DashboardController extends Controller
{
    /** Sedes elegidas en ?sedes[] (modo "todas las sedes" only), o null = todas. */
    private ?array $sedes = null;

    public function __invoke(Request $request): View
    {
        if (GymContext::id() === null) {
            $this->sedes = $this->sedesElegidas($request);
        }

        // Cierre del día frente a hace exactamente una semana (mismo día de
        // la semana): la variación da contexto a un solo dato suelto — S/ 45
        // no dice nada hasta que sabes que la semana pasada fueron S/ 20.
        $hoy = (float) $this->sales()->completadas()->delDia()->sum('total');
        $semanaPasada = (float) $this->sales()->completadas()
            ->whereDate('sold_at', now()->subWeek()->toDateString())
            ->sum('total');

        // Retención: de las membresías que vencieron en los últimos 30 días,
        // cuántas sí se renovaron (hay otra membresía con renewed_from
        // apuntando a ellas). Se compara contra el tramo de 30 días previo
        // (día -60 a -31) para darle variación al número, mismo patrón que
        // el cierre de hoy vs. hace una semana.
        $retencionActual   = $this->retencion(now()->subDays(30), now());
        $retencionAnterior = $this->retencion(now()->subDays(60), now()->subDays(31));

        $datos = [
            'kpis' => [
                'ingresosHoy'          => $hoy,
                'ingresosSemanaPasada' => $semanaPasada,
                'variacionHoy'         => $semanaPasada > 0
                    ? round(($hoy - $semanaPasada) / $semanaPasada * 100)
                    : ($hoy > 0 ? 100 : 0),
                'ingresosMes'       => (float) $this->sales()->completadas()->delMes()->sum('total'),
                'clientesActivos'   => $this->members()->activos()->count(),
                'clientesInactivos' => $this->members()->where('status', 'inactivo')->count(),
                'membresiasVencidas'=> $this->memberships()->vencidas()->count(),
                'matriculasMes'     => $this->members()->whereMonth('joined_at', now()->month)->whereYear('joined_at', now()->year)->count(),
                'asistenciaHoy'     => $this->attendances()->deHoy()->count(),
                'retencion30dias'      => $retencionActual['pct'],
                'retencion30diasAntes' => $retencionAnterior['pct'],
                'retencionVariacion'   => $retencionActual['pct'] - $retencionAnterior['pct'],
            ],

            'porVencer' => $this->members()->with('currentMembership')
                ->porVencer(7)
                ->orderBy('last_name')
                ->take(8)
                ->get(),

            'ultimasVentas' => $this->sales()->with('member')
                ->completadas()
                ->latest('sold_at')
                ->take(6)
                ->get(),

            'graficoIngresos'   => $this->ingresosUltimosDias(30),
            'graficoAsistencia' => $this->asistenciaUltimosDias(14),
            'graficoMetodos'    => $this->distribucionMetodos(),
            'graficoAcumulado'  => $this->ingresosAcumuladosDelMes(),
            'graficoAltas'      => $this->altasSocios(6),

            // Eje de clientes (rebalanceo del dashboard, sesgado a ingresos):
            // altas diarias, altas vs bajas reales por mes, y una foto de la
            // composición actual de la cartera.
            'graficoAltasDiarias'   => $this->altasDiarias(30),
            'graficoAltasVsBajas'   => $this->altasVsBajasPorMes(6),
            'graficoComposicion'    => $this->composicionClientes(),
        ];

        // Modo "todas las sedes" (GymContext::id() es null): además del
        // filtro de arriba (que ya alcanza a KPIs y series), se añade el
        // desglose por sede.
        if (GymContext::id() === null) {
            $datos['sedesFiltradas'] = $this->sedes;
            $datos['porSede'] = $this->desglosePorSede($this->sedes);
        }

        return view('admin.dashboard', $datos);
    }

    /** Las sedes marcadas en ?sedes[], saneadas contra las del usuario; null = todas. */
    private function sedesElegidas(Request $request): ?array
    {
        if (! $request->filled('sedes')) {
            return null;
        }

        $elegidas = array_map('intval', (array) $request->input('sedes'));

        return array_intersect($elegidas, $request->user()->sedesDisponibles()->pluck('id')->all());
    }

    /**
     * Punto único por el que pasan todas las consultas de sedes: en modo
     * "todas las sedes" sin filtro, no se toca nada (BelongsToGym ya deja
     * pasar todo); con ?sedes[] elegidas, se cruza la sede a mano con
     * sinFiltroDeGimnasio() — el único cruce permitido (ver AGENTS.md).
     */
    private function conSedes(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        return $this->sedes
            ? $q->sinFiltroDeGimnasio()->whereIn('gym_id', $this->sedes)
            : $q;
    }

    private function sales(): \Illuminate\Database\Eloquent\Builder       { return $this->conSedes(Sale::query()); }
    private function members(): \Illuminate\Database\Eloquent\Builder     { return $this->conSedes(Member::query()); }
    private function memberships(): \Illuminate\Database\Eloquent\Builder{ return $this->conSedes(Membership::query()); }
    private function attendances(): \Illuminate\Database\Eloquent\Builder{ return $this->conSedes(Attendance::query()); }

    /** Socios activos e ingresos del mes, una fila por sede. Filtra por ?sedes[] si viene. */
    private function desglosePorSede(?array $sedes): \Illuminate\Support\Collection
    {
        return Gym::query()
            ->when($sedes !== null, fn ($q) => $q->whereIn('id', $sedes))
            ->withCount(['members as clientes_activos' => fn ($q) => $q->where('status', 'activo')])
            ->orderBy('name')
            ->get()
            ->map(fn (Gym $gym) => [
                'nombre'   => $gym->name,
                'clientes' => $gym->clientes_activos,
                'ingresos' => (float) Sale::sinFiltroDeGimnasio()
                    ->where('gym_id', $gym->id)
                    ->completadas()
                    ->delMes()
                    ->sum('total'),
            ]);
    }

    /** Serie diaria de ingresos: lo que alimenta la línea de fuego del dashboard. */
    private function ingresosUltimosDias(int $dias): array
    {
        $desde = now()->subDays($dias - 1)->startOfDay();

        $filas = $this->sales()->completadas()
            ->where('sold_at', '>=', $desde)
            ->selectRaw('DATE(sold_at) as dia, SUM(total) as total')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        return $this->rellenarDias($dias, fn (Carbon $d) => (float) ($filas[$d->toDateString()] ?? 0));
    }

    private function asistenciaUltimosDias(int $dias): array
    {
        $desde = now()->subDays($dias - 1)->startOfDay();

        $filas = $this->attendances()->where('attended_on', '>=', $desde->toDateString())
            ->selectRaw('attended_on, COUNT(*) as total')
            ->groupBy('attended_on')
            ->pluck('total', 'attended_on');

        return $this->rellenarDias($dias, fn (Carbon $d) => (int) ($filas[$d->toDateString()] ?? 0));
    }

    /**
     * Genera las etiquetas de los últimos N días y aplica $valor a cada uno,
     * incluidos los días sin ningún registro: sin este relleno, un día sin
     * ingresos desaparecería del eje en vez de mostrar un cero real.
     */
    private function rellenarDias(int $dias, callable $valor): array
    {
        $etiquetas = [];
        $datos = [];

        for ($i = $dias - 1; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $etiquetas[] = $d->translatedFormat('d M');
            $datos[] = $valor($d);
        }

        return ['labels' => $etiquetas, 'data' => $datos];
    }

    private function distribucionMetodos(): array
    {
        $filas = $this->sales()->completadas()->delMes()
            ->selectRaw('method, SUM(total) as total')
            ->groupBy('method')
            ->pluck('total', 'method');

        return [
            'labels' => $filas->keys()->map(fn ($m) => config("sparta.metodos_pago.$m", $m))->all(),
            'data'   => $filas->values()->map(fn ($v) => (float) $v)->all(),
        ];
    }

    /**
     * "Cómo va el mes": acumulado de ingresos día a día frente al mes anterior
     * medido hasta el mismo día. Las dos líneas comparten eje (día 1..hoy) y
     * se lee como "vamos por delante o por detrás de lo del mes pasado".
     */
    private function ingresosAcumuladosDelMes(): array
    {
        $hoy = now();
        $dia = (int) $hoy->day;

        $diarioMes = $this->sales()->completadas()
            ->where('sold_at', '>=', $hoy->copy()->startOfMonth())
            ->selectRaw('DAY(sold_at) as dia, SUM(total) as total')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $inicioAnterior = $hoy->copy()->subMonthNoOverflow()->startOfMonth();
        $diarioAnterior = $this->sales()->completadas()
            ->whereBetween('sold_at', [
                $inicioAnterior,
                $inicioAnterior->copy()->addDays($dia - 1)->endOfDay(),
            ])
            ->selectRaw('DAY(sold_at) as dia, SUM(total) as total')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $labels = $mes = $anterior = [];
        $acumulado = 0.0;
        $acumuladoAnterior = 0.0;

        for ($d = 1; $d <= $dia; $d++) {
            $acumulado += (float) ($diarioMes[$d] ?? 0);
            $acumuladoAnterior += (float) ($diarioAnterior[$d] ?? 0);
            $labels[] = "Día $d";
            $mes[] = round($acumulado, 2);
            $anterior[] = round($acumuladoAnterior, 2);
        }

        return [
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Este mes', 'data' => $mes, 'token' => '--sangre'],
                ['label' => 'Mes anterior', 'data' => $anterior, 'token' => '--bronce', 'relleno' => false, 'guiones' => [6, 5]],
            ],
        ];
    }

    /** Altas de socios de los últimos N meses: la curva de crecimiento del gimnasio. */
    private function altasSocios(int $meses): array
    {
        $etiquetas = [];
        $datos = [];

        for ($i = $meses - 1; $i >= 0; $i--) {
            $m = now()->subMonthsNoOverflow($i);
            $etiquetas[] = $m->translatedFormat('M');
            $datos[] = $this->members()
                ->whereYear('joined_at', $m->year)
                ->whereMonth('joined_at', $m->month)
                ->count();
        }

        return [
            'labels' => $etiquetas,
            'datasets' => [['label' => 'Nuevos clientes', 'data' => $datos, 'token' => '--brasa']],
        ];
    }

    /** Altas día a día: "los indicadores diarios de los clientes que voy registrando". */
    private function altasDiarias(int $dias): array
    {
        $desde = now()->subDays($dias - 1)->startOfDay();

        $filas = $this->members()->where('joined_at', '>=', $desde->toDateString())
            ->selectRaw('joined_at as dia, COUNT(*) as total')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        return $this->rellenarDias($dias, fn (Carbon $d) => (int) ($filas[$d->toDateString()] ?? 0));
    }

    /**
     * Altas vs. bajas reales por mes: no hay tabla de historial de estado
     * (members.status es un enum del momento actual), así que la "baja" se
     * deriva de las membresías — una que venció y a la que nadie apunta
     * como renovación (renewed_from) es una baja de verdad. Dos consultas
     * agrupadas (una por serie), no una por mes en un loop.
     */
    private function altasVsBajasPorMes(int $meses): array
    {
        $desde = now()->subMonthsNoOverflow($meses - 1)->startOfMonth();

        $altas = $this->members()->where('joined_at', '>=', $desde->toDateString())
            ->selectRaw('YEAR(joined_at) as anio, MONTH(joined_at) as mes, COUNT(*) as total')
            ->groupBy('anio', 'mes')
            ->get()
            ->keyBy(fn ($f) => $f->anio . '-' . $f->mes);

        $bajas = $this->memberships()
            ->where('ends_at', '>=', $desde)
            ->where('ends_at', '<', now())
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('memberships as r')
                    ->whereColumn('r.renewed_from', 'memberships.id');
            })
            ->selectRaw('YEAR(ends_at) as anio, MONTH(ends_at) as mes, COUNT(*) as total')
            ->groupBy('anio', 'mes')
            ->get()
            ->keyBy(fn ($f) => $f->anio . '-' . $f->mes);

        $etiquetas = $datosAltas = $datosBajas = [];

        for ($i = $meses - 1; $i >= 0; $i--) {
            $m = now()->subMonthsNoOverflow($i);
            $clave = $m->year . '-' . $m->month;
            $etiquetas[] = $m->translatedFormat('M');
            $datosAltas[] = (int) ($altas[$clave]->total ?? 0);
            $datosBajas[] = (int) ($bajas[$clave]->total ?? 0);
        }

        return [
            'labels' => $etiquetas,
            'datasets' => [
                ['label' => 'Altas', 'data' => $datosAltas, 'token' => '--brasa'],
                ['label' => 'Bajas', 'data' => $datosBajas, 'token' => '--sangre', 'relleno' => false, 'guiones' => [6, 5]],
            ],
        ];
    }

    /** Foto de hoy: cuántos socios están activos, inactivos o suspendidos ahora mismo. */
    private function composicionClientes(): array
    {
        $filas = $this->members()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $mapa = ['activo' => 'Activos', 'inactivo' => 'Inactivos', 'suspendido' => 'Suspendidos'];

        return [
            'labels' => array_values($mapa),
            'data'   => array_map(fn ($clave) => (int) ($filas[$clave] ?? 0), array_keys($mapa)),
        ];
    }

    /**
     * % de membresías con ends_at en [$desde, $hasta] que sí se renovaron
     * (existe otra membresía con renewed_from apuntando a ellas). Una sola
     * consulta agregada, no una por membresía.
     */
    private function retencion(Carbon $desde, Carbon $hasta): array
    {
        $fila = $this->memberships()
            ->whereBetween('ends_at', [$desde->toDateString(), $hasta->toDateString()])
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN EXISTS (SELECT 1 FROM memberships r WHERE r.renewed_from = memberships.id) THEN 1 ELSE 0 END) as renovadas')
            ->first();

        $total = (int) ($fila->total ?? 0);
        $renovadas = (int) ($fila->renovadas ?? 0);

        return [
            'total'     => $total,
            'renovadas' => $renovadas,
            'pct'       => $total > 0 ? (int) round($renovadas / $total * 100) : 0,
        ];
    }
}
