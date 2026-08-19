@extends('layouts.panel')

@section('titulo', 'Asistencia')
@section('subtitulo', 'Tu propio horario de trabajo — marca tu entrada y salida')

@section('contenido')
    {{-- Indicadores del mes que se está mirando (antes vivían en el
         "Resumen" aparte, que se dio de baja). --}}
    <div class="kpis" data-revelar data-revelar-grupo>
        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="reloj" /></span>
            <b class="kpi__valor"><span data-contador="{{ $kpis['diasTrabajados'] }}">0</span></b>
            <span class="kpi__etiqueta">Días trabajados este mes</span>
        </article>
        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="entrada" /></span>
            {{-- Sin data-contador: ese animador redondea a entero
                 (Math.round en animations.js), y acá interesa la fracción
                 (8.5 h), no solo el número entero. --}}
            <b class="kpi__valor">{{ number_format($kpis['horasTrabajadas'], 1) }} h</b>
            <span class="kpi__etiqueta">Horas marcadas este mes</span>
        </article>
    </div>

    <article class="tarjeta" style="padding:var(--e-6)" data-revelar>
        @if ($abierta)
            <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-4)">
                Entraste a las <b style="color:var(--hueso)">{{ $abierta->clocked_in_at->format('H:i') }}</b>
                ({{ $abierta->turno_legible }}) y sigues en turno.
            </p>
        @endif

        {{-- El QR es la única vía: al escanearlo, el mismo modal pide turno
             si hace falta, la ubicación en tiempo real y registra la
             marcación — ver _escaneo-qr.blade.php. --}}
        <button class="btn btn--fuego btn--grande" type="button" @click="$dispatch('abrir-escaneo-qr')">
            <x-icono nombre="qr" /> Escanear QR
        </button>
    </article>

    {{-- "¿Cuán constante soy yo?" — fijo a las últimas 12 semanas, sin
         importar el mes que se esté mirando abajo. Solo sus marcaciones. --}}
    <article class="tarjeta grafico" data-revelar>
        <div class="grafico__cabecera">
            <h3 style="font-size:var(--t-lg)">Mi constancia · últimas 12 semanas</h3>
        </div>
        @if (array_sum($graficoMarcaciones['datasets'][0]['data']) > 0)
            <div class="grafico__lienzo">
                <canvas data-grafico="{{ json_encode(['tipo' => 'bar'] + $graficoMarcaciones) }}"></canvas>
            </div>
        @else
            <x-estado-vacio icono="reloj" texto="Sin marcaciones en las últimas 12 semanas." />
        @endif
    </article>

    <form class="panel__toolbar" method="GET" data-revelar>
        <input type="hidden" name="mes" value="{{ $mes }}">
        <input type="hidden" name="anio" value="{{ $anio }}">
        <select class="campo__control" name="turno" style="max-width:200px" onchange="this.form.submit()">
            <option value="">Todos los turnos</option>
            <option value="manana" @selected($turno === 'manana')>Mañana</option>
            <option value="tarde" @selected($turno === 'tarde')>Tarde</option>
            <option value="doble" @selected($turno === 'doble')>Doble turno</option>
        </select>
        <noscript><button class="btn btn--vidrio" type="submit">Filtrar</button></noscript>
    </form>

    <x-alterna-vista clave="mi-marcacion">
        <x-slot:lista>
            <div class="tabla-envoltorio" data-revelar>
                <table class="tabla tabla--tarjetas">
                    <thead><tr><th>Entrada</th><th>Salida</th><th>Turno</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($marcacionesPag as $m)
                            @php $pendiente = $m->editRequests->first(); @endphp
                            <tr x-data="{ editando: false }"
                                @click="if (!editando) $dispatch('abrir-detalle-mi', { url: '{{ route('entrenador.asistencia.detalle', $m) }}' })"
                                style="cursor:pointer">
                                <td class="es-fuerte" data-etiqueta="Entrada">{{ $m->clocked_in_at->format('d/m/y H:i') }}</td>
                                <td data-etiqueta="Salida">{{ $m->clocked_out_at?->format('H:i') ?? 'En curso' }}</td>
                                <td data-etiqueta="Turno">{{ $m->turno_legible }}</td>
                                <td data-etiqueta="nada" style="display:flex;gap:var(--e-2);align-items:center">
                                    @if ($pendiente)
                                        <span class="estado" style="color:var(--bronce)">{{ $pendiente->es_eliminacion ? 'Eliminación pendiente de aprobar' : 'Edición pendiente de aprobar' }}</span>
                                    @else
                                        <button class="btn btn--desnudo" type="button" @click.stop="editando = !editando">Editar</button>
                                        <form method="POST" action="{{ route('entrenador.asistencia.eliminar', $m) }}"
                                              @click.stop
                                              onsubmit="return confirm('¿Solicitar la eliminación de este registro? Queda pendiente de aprobación del admin.')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn--desnudo" type="submit" style="color:var(--sangre-viva)">Eliminar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @if (! $pendiente)
                                <tr x-show="editando" x-cloak>
                                    <td colspan="4" data-etiqueta="">
                                        <form method="POST" action="{{ route('entrenador.asistencia.solicitar-edicion', $m) }}" class="formulario-panel__fila">
                                            @csrf
                                            <label class="campo"><span class="campo__etiqueta">Nueva entrada</span>
                                                <input class="campo__control" type="datetime-local" name="checked_in_at"
                                                       value="{{ $m->clocked_in_at->format('Y-m-d\TH:i') }}" required></label>
                                            <label class="campo"><span class="campo__etiqueta">Nueva salida (opcional)</span>
                                                <input class="campo__control" type="datetime-local" name="checked_out_at"
                                                       value="{{ $m->clocked_out_at?->format('Y-m-d\TH:i') }}"></label>
                                            <label class="campo"><span class="campo__etiqueta">Motivo (opcional)</span>
                                                <input class="campo__control" type="text" name="reason" maxlength="255"></label>
                                            <div style="align-self:end">
                                                <button class="btn btn--fuego" type="submit">Enviar solicitud al admin</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="4" class="tabla__vacio" data-etiqueta=""><x-estado-vacio icono="entrada" texto="Sin marcaciones este mes." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="paginacion">{{ $marcacionesPag->links() }}</div>
        </x-slot:lista>

        <x-slot:calendario>
            <x-calendario ruta="entrenador.asistencia.mi-marcacion" :anterior="$anterior" :siguiente="$siguiente"
                          :celdas="$celdas" contador-texto="marcación" :filtros="$filtros">
                @foreach ($porDia as $fecha => $lista)
                    <div x-show="diaAbierto === '{{ $fecha }}'" x-cloak class="calendario__lista">
                        @foreach ($lista->sortByDesc('clocked_in_at') as $m)
                            @php $pendiente = $m->editRequests->first(); @endphp
                            <article class="calendario__rutina"
                                     @click="$dispatch('abrir-detalle-mi', { url: '{{ route('entrenador.asistencia.detalle', $m) }}' })"
                                     style="cursor:pointer">
                                <div>
                                    <b class="es-fuerte" style="color:var(--hueso)">
                                        {{ $m->turno_legible }}
                                        @if ($pendiente)
                                            <span class="estado" style="color:var(--bronce);font-size:var(--t-xs);font-weight:normal;margin-left:var(--e-2)">
                                                {{ $pendiente->es_eliminacion ? '🗑 eliminación (pendiente)' : '✎ editado (pendiente)' }}
                                            </span>
                                        @endif
                                    </b>
                                    <span class="calendario__meta">
                                        {{ $m->clocked_out_at ? 'Salió ' . $m->clocked_out_at->format('H:i') : 'Sigue en turno' }}
                                    </span>
                                </div>
                                <time class="calendario__hora">{{ $m->clocked_in_at->format('H:i') }}</time>
                            </article>
                        @endforeach
                    </div>
                @endforeach
            </x-calendario>
        </x-slot:calendario>
    </x-alterna-vista>

    @include('entrenador.asistencia._escaneo-qr')
    @include('entrenador.asistencia._detalle-marcacion')
@endsection
