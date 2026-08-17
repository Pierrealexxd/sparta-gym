@extends('layouts.panel')

@section('titulo', 'Dashboard')
@section('subtitulo', now()->translatedFormat('l, d \d\e F'))

@section('contenido')
    <div class="kpis" data-revelar data-revelar-grupo>
        <article class="tarjeta kpi tarjeta--interactiva">
            <div class="kpi__cabecera">
                <span class="kpi__icono"><x-icono nombre="billetera" /></span>
            </div>
            <b class="kpi__valor">S/ <span data-contador="{{ $kpis['ingresosHoy'] }}">0</span></b>
            <span class="kpi__etiqueta">Ingresos de hoy</span>
        </article>

        <article class="tarjeta kpi tarjeta--interactiva">
            <div class="kpi__cabecera">
                <span class="kpi__icono" style="background:rgba(255,106,31,.12);border-color:rgba(255,106,31,.3);color:var(--brasa)">
                    <x-icono nombre="balanza" />
                </span>
            </div>
            <b class="kpi__valor" style="color:var(--{{ $kpis['variacionHoy'] < 0 ? 'sangre' : 'brasa' }})">
                {{ $kpis['variacionHoy'] >= 0 ? '+' : '−' }}<span data-contador="{{ abs($kpis['variacionHoy']) }}">0</span>%
            </b>
            <span class="kpi__etiqueta">Cierre de hoy vs. hace una semana</span>
            <span class="kpi__variacion" style="color:var(--humo)">
                Hoy S/ {{ number_format($kpis['ingresosHoy'], 2) }} · Hace una semana S/ {{ number_format($kpis['ingresosSemanaPasada'], 2) }}
            </span>
        </article>

        <article class="tarjeta kpi tarjeta--interactiva">
            <div class="kpi__cabecera">
                <span class="kpi__icono"><x-icono nombre="grafico" /></span>
            </div>
            <b class="kpi__valor">S/ <span data-contador="{{ $kpis['ingresosMes'] }}">0</span></b>
            <span class="kpi__etiqueta">Ingresos del mes</span>
        </article>

        <article class="tarjeta kpi tarjeta--interactiva">
            <div class="kpi__cabecera">
                <span class="kpi__icono"><x-icono nombre="usuarios" /></span>
            </div>
            <b class="kpi__valor"><span data-contador="{{ $kpis['clientesActivos'] }}">0</span></b>
            <span class="kpi__etiqueta">Clientes activos</span>
            <span class="kpi__variacion" style="color:var(--humo)">{{ $kpis['clientesInactivos'] }} inactivos</span>
        </article>

        <article class="tarjeta kpi tarjeta--interactiva">
            <div class="kpi__cabecera">
                <span class="kpi__icono"><x-icono nombre="entrada" /></span>
            </div>
            <b class="kpi__valor"><span data-contador="{{ $kpis['asistenciaHoy'] }}">0</span></b>
            <span class="kpi__etiqueta">Asistencias hoy</span>
        </article>

        <article class="tarjeta kpi tarjeta--interactiva">
            <div class="kpi__cabecera">
                <span class="kpi__icono" style="background:rgba(255,106,31,.12);border-color:rgba(255,106,31,.3);color:var(--brasa)">
                    <x-icono nombre="campana" />
                </span>
            </div>
            <b class="kpi__valor"><span data-contador="{{ $kpis['membresiasVencidas'] }}">0</span></b>
            <span class="kpi__etiqueta">Membresías vencidas</span>
        </article>

        <article class="tarjeta kpi tarjeta--interactiva">
            <div class="kpi__cabecera">
                <span class="kpi__icono"><x-icono nombre="agregar" /></span>
            </div>
            <b class="kpi__valor"><span data-contador="{{ $kpis['matriculasMes'] }}">0</span></b>
            <span class="kpi__etiqueta">Matrículas este mes</span>
        </article>

        <article class="tarjeta kpi tarjeta--interactiva">
            <div class="kpi__cabecera">
                <span class="kpi__icono"><x-icono nombre="escudo" /></span>
            </div>
            <b class="kpi__valor"><span data-contador="{{ $kpis['retencion30dias'] }}">0</span>%</b>
            <span class="kpi__etiqueta">Retención · últimos 30 días</span>
            <span class="kpi__variacion" style="color:var(--{{ $kpis['retencionVariacion'] < 0 ? 'sangre' : 'brasa' }})">
                {{ $kpis['retencionVariacion'] >= 0 ? '+' : '−' }}{{ abs($kpis['retencionVariacion']) }} pts vs. tramo anterior
            </span>
        </article>

    </div>

    @if (\App\Support\GymContext::id() === null)
        <div data-revelar>
            <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-4)">Desglose por sede · mes actual</h3>
            <div class="kpis">
                @foreach ($porSede as $sede)
                    <article class="tarjeta kpi">
                        <span class="kpi__icono"><x-icono nombre="ubicacion" /></span>
                        <b class="kpi__valor">{{ $sede['clientes'] }}</b>
                        <span class="kpi__etiqueta">{{ $sede['nombre'] }} · S/ {{ number_format($sede['ingresos'], 0) }}</span>
                    </article>
                @endforeach
                <article class="tarjeta kpi kpi--resumen">
                    <b class="kpi__valor">{{ $porSede->sum('clientes') }}</b>
                    <span class="kpi__etiqueta">Todas · S/ {{ number_format($porSede->sum('ingresos'), 0) }}</span>
                </article>
            </div>
        </div>
    @endif

    <div class="g-2-1" data-revelar data-revelar-grupo>
        <article class="tarjeta grafico">
            <div class="grafico__cabecera">
                <h3 style="font-size:var(--t-lg)">Ingresos · últimos 30 días</h3>
            </div>
            <div class="grafico__lienzo">
                <canvas data-grafico="{{ json_encode(['tipo' => 'line', 'labels' => $graficoIngresos['labels'], 'datasets' => [['label' => 'Ingresos (S/)', 'data' => $graficoIngresos['data'], 'token' => '--sangre']]]) }}"></canvas>
            </div>
        </article>

        <article class="tarjeta grafico">
            <div class="grafico__cabecera">
                <h3 style="font-size:var(--t-lg)">Cobros por método</h3>
            </div>
            <div class="grafico__lienzo">
                <canvas data-grafico="{{ json_encode(['tipo' => 'doughnut', 'labels' => $graficoMetodos['labels'], 'label' => 'Métodos', 'data' => $graficoMetodos['data']]) }}"></canvas>
            </div>
        </article>
    </div>

    <div class="g-1-1" data-revelar data-revelar-grupo>
        <article class="tarjeta grafico">
            <div class="grafico__cabecera">
                <h3 style="font-size:var(--t-lg)">Cómo va el mes · acumulado</h3>
            </div>
            <div class="grafico__lienzo">
                <canvas data-grafico="{{ json_encode($graficoAcumulado) }}"></canvas>
            </div>
        </article>

        <article class="tarjeta grafico">
            <div class="grafico__cabecera">
                <h3 style="font-size:var(--t-lg)">Nuevos clientes · últimos 6 meses</h3>
            </div>
            <div class="grafico__lienzo">
                <canvas data-grafico="{{ json_encode($graficoAltas) }}"></canvas>
            </div>
        </article>
    </div>

    <div class="g-2-1" data-revelar data-revelar-grupo>
        <article class="tarjeta grafico">
            <div class="grafico__cabecera">
                <h3 style="font-size:var(--t-lg)">Altas de clientes · últimos 30 días</h3>
            </div>
            @if (array_sum($graficoAltasDiarias['data']) > 0)
                <div class="grafico__lienzo">
                    <canvas data-grafico="{{ json_encode(['tipo' => 'bar', 'labels' => $graficoAltasDiarias['labels'], 'datasets' => [['label' => 'Altas', 'data' => $graficoAltasDiarias['data'], 'token' => '--brasa']]]) }}"></canvas>
                </div>
            @else
                <x-estado-vacio icono="usuarios" texto="Sin altas registradas en los últimos 30 días." />
            @endif
        </article>

        <article class="tarjeta grafico">
            <div class="grafico__cabecera">
                <h3 style="font-size:var(--t-lg)">Composición de clientes · hoy</h3>
            </div>
            @if (array_sum($graficoComposicion['data']) > 0)
                <div class="grafico__lienzo">
                    <canvas data-grafico="{{ json_encode(['tipo' => 'doughnut', 'labels' => $graficoComposicion['labels'], 'label' => 'Clientes', 'data' => $graficoComposicion['data']]) }}"></canvas>
                </div>
            @else
                <x-estado-vacio icono="usuarios" texto="Todavía no hay clientes registrados." />
            @endif
        </article>
    </div>

    <article class="tarjeta grafico" data-revelar>
        <div class="grafico__cabecera">
            <h3 style="font-size:var(--t-lg)">Altas vs. bajas · últimos 6 meses</h3>
        </div>
        @if (array_sum($graficoAltasVsBajas['datasets'][0]['data']) > 0 || array_sum($graficoAltasVsBajas['datasets'][1]['data']) > 0)
            <div class="grafico__lienzo">
                <canvas data-grafico="{{ json_encode($graficoAltasVsBajas) }}"></canvas>
            </div>
        @else
            <x-estado-vacio icono="grafico" texto="Sin altas ni bajas en los últimos 6 meses." />
        @endif
    </article>

    <article class="tarjeta grafico" data-revelar>
        <div class="grafico__cabecera">
            <h3 style="font-size:var(--t-lg)">Asistencia · últimos 14 días</h3>
        </div>
        <div class="grafico__lienzo">
            <canvas data-grafico="{{ json_encode(['tipo' => 'bar', 'labels' => $graficoAsistencia['labels'], 'datasets' => [['label' => 'Visitas', 'data' => $graficoAsistencia['data'], 'token' => '--brasa']]]) }}"></canvas>
        </div>
    </article>

    <div class="g-1-1" data-revelar data-revelar-grupo>
        <article class="tarjeta">
            <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-4)">Vencen esta semana</h3>
            <div class="tabla-envoltorio">
                <table class="tabla tabla--tarjetas">
                    <thead><tr><th>Cliente</th><th>Código</th><th>Vence</th></tr></thead>
                    <tbody>
                        @forelse ($porVencer as $socio)
                            <tr>
                                <td class="es-fuerte" data-etiqueta="Cliente">
                                    <a href="{{ route('admin.clientes.show', $socio) }}">{{ $socio->full_name }}</a>
                                </td>
                                <td data-etiqueta="Código">{{ $socio->code }}</td>
                                <td data-etiqueta="Vence">{{ $socio->currentMembership?->ends_at->translatedFormat('d M') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="tabla__vacio" data-etiqueta=""><x-estado-vacio icono="reloj" texto="Nadie vence esta semana." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="tarjeta">
            <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-4)">Últimas ventas</h3>
            <div class="tabla-envoltorio">
                <table class="tabla tabla--tarjetas">
                    <thead><tr><th>Cliente</th><th>Monto</th><th>Método</th></tr></thead>
                    <tbody>
                        @forelse ($ultimasVentas as $venta)
                            <tr>
                                <td class="es-fuerte" data-etiqueta="Cliente">{{ $venta->member?->full_name ?? '—' }}</td>
                                <td data-etiqueta="Monto">S/ {{ number_format($venta->total, 2) }}</td>
                                <td data-etiqueta="Método">{{ config("sparta.metodos_pago.$venta->method", $venta->method) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="tabla__vacio" data-etiqueta=""><x-estado-vacio icono="billetera" texto="Sin ventas todavía." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </div>

    @if (\App\Support\GymContext::id() === null)
        <article class="tarjeta filtro-sedes" x-data="{ abierto: false }" data-revelar>
            <button type="button" class="filtro-sedes__toggler" @click="abierto = !abierto">
                <x-icono nombre="grafico" />
                Algunas sedes
            </button>
            <div x-show="abierto" x-cloak class="filtro-sedes__cuerpo">
                <form method="GET" action="{{ route('admin.dashboard') }}">
                    @foreach (auth()->user()->sedesDisponibles() as $sede)
                        <label class="filtro-sedes__opcion">
                            <input type="checkbox" name="sedes[]" value="{{ $sede->id }}"
                                   @checked(in_array($sede->id, $sedesFiltradas ?? []))
                                   @change="$el.closest('form').submit()">
                            {{ $sede->name }}
                        </label>
                    @endforeach
                </form>
                <span class="filtro-sedes__ayuda">Sólo cuentan las marcadas.</span>
            </div>
        </article>
    @endif
@endsection
