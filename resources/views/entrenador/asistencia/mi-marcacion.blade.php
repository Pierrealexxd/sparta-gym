@extends('layouts.panel')

@section('titulo', 'Asistencia')
@section('subtitulo', 'Tu propio horario de trabajo — marca tu entrada y salida')

@section('contenido')
    <article class="tarjeta" style="padding:var(--e-6)" data-revelar>
        @if ($abierta)
            <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-4)">
                Entraste a las <b style="color:var(--hueso)">{{ $abierta->clocked_in_at->format('H:i') }}</b>
                ({{ $abierta->turno_legible }}) y sigues en turno.
            </p>
            <div style="display:flex;gap:var(--e-3);align-items:center;flex-wrap:wrap">
                <form method="POST" action="{{ route('entrenador.asistencia.marcar') }}">
                    @csrf
                    <button class="btn btn--fuego btn--grande" type="submit"><x-icono nombre="entrada" /> Marcar salida</button>
                </form>
                <button class="btn btn--vidrio btn--grande" type="button" @click="$dispatch('abrir-escaneo-qr')">
                    <x-icono nombre="qr" /> Escanear QR
                </button>
            </div>
        @else
            <form method="POST" action="{{ route('entrenador.asistencia.marcar') }}" class="formulario-panel__fila" style="align-items:flex-end">
                @csrf
                <label class="campo"><span class="campo__etiqueta">Turno</span>
                    <select class="campo__control" name="turno" required>
                        <option value="manana">Mañana</option>
                        <option value="tarde">Tarde</option>
                        <option value="doble">Doble turno</option>
                    </select>
                </label>
                <button class="btn btn--fuego btn--grande" type="submit"><x-icono nombre="entrada" /> Marcar entrada</button>
                <button class="btn btn--vidrio btn--grande" type="button" @click="$dispatch('abrir-escaneo-qr')">
                    <x-icono nombre="qr" /> Escanear QR
                </button>
            </form>
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
                        @forelse ($marcaciones as $m)
                            @php $pendiente = $m->editRequests->first(); @endphp
                            <tr x-data="{ editando: false }">
                                <td class="es-fuerte" data-etiqueta="Entrada">{{ $m->clocked_in_at->format('d/m/y H:i') }}</td>
                                <td data-etiqueta="Salida">{{ $m->clocked_out_at?->format('H:i') ?? 'En curso' }}</td>
                                <td data-etiqueta="Turno">{{ $m->turno_legible }}</td>
                                <td data-etiqueta="nada" style="display:flex;gap:var(--e-2);align-items:center">
                                    @if ($pendiente)
                                        <span class="estado" style="color:var(--bronce)">Edición pendiente de aprobar</span>
                                    @else
                                        <button class="btn btn--desnudo" type="button" @click="editando = !editando">Editar</button>
                                        <form method="POST" action="{{ route('entrenador.asistencia.eliminar', $m) }}"
                                              onsubmit="return confirm('¿Eliminar este registro? No se puede deshacer.')">
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
        </x-slot:lista>

        <x-slot:calendario>
            <x-calendario ruta="entrenador.asistencia.mi-marcacion" :anterior="$anterior" :siguiente="$siguiente"
                          :celdas="$celdas" contador-texto="marcación" :filtros="$filtros">
                @foreach ($porDia as $fecha => $lista)
                    <div x-show="diaAbierto === '{{ $fecha }}'" x-cloak class="calendario__lista">
                        @foreach ($lista->sortByDesc('clocked_in_at') as $m)
                            @php $pendiente = $m->editRequests->first(); @endphp
                            <article class="calendario__rutina">
                                <div>
                                    <b class="es-fuerte" style="color:var(--hueso)">
                                        {{ $m->turno_legible }}
                                        @if ($pendiente)
                                            <span class="estado" style="color:var(--bronce);font-size:var(--t-xs);font-weight:normal;margin-left:var(--e-2)">
                                                ✎ editado (pendiente)
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
@endsection
