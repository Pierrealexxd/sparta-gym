@extends('layouts.panel')

@section('titulo', 'Asistencia')
@section('subtitulo', 'Marcaciones laborales de los entrenadores — por QR, con ubicación')

@section('contenido')
    @include('admin.asistencia._pestanas')

    <form class="panel__toolbar" method="GET">
        <select class="campo__control" name="entrenador" style="max-width:220px">
            <option value="">Todos los entrenadores</option>
            @foreach ($entrenadores as $e)
                <option value="{{ $e->id }}" @selected($entrenador === $e->id)>{{ $e->name }}</option>
            @endforeach
        </select>
        <select class="campo__control" name="metodo" style="max-width:160px">
            <option value="">Todos los métodos</option>
            <option value="manual" @selected($metodo === 'manual')>Manual</option>
            <option value="qr" @selected($metodo === 'qr')>QR</option>
        </select>
        <button class="btn btn--vidrio" type="submit">Filtrar</button>
    </form>

    <x-calendario ruta="admin.asistencia.calendario" :anterior="$anterior" :siguiente="$siguiente"
                  :celdas="$celdas" contador-texto="marcación" :filtros="$filtros">
        @foreach ($porDia as $fecha => $lista)
            <div x-show="diaAbierto === '{{ $fecha }}'" x-cloak class="calendario__lista">
                @foreach ($lista->sortByDesc('clocked_in_at') as $m)
                    <article class="calendario__rutina">
                        <div>
                            <b class="es-fuerte" style="color:var(--hueso)">{{ $m->user?->name ?? '—' }}</b>
                            <span class="calendario__meta">
                                {{ $m->turno_legible }}
                                · <span class="estado">{{ $m->method_legible }}</span>
                                {{ $m->clocked_out_at ? '· Salió ' . $m->clocked_out_at->format('H:i') : '· En turno' }}
                                @if ($m->location_lat) · <span class="estado" title="Marcado con ubicación">📍</span> @endif
                                @if ($modoTodas) · <span class="estado">{{ $m->gym?->name ?? '—' }}</span> @endif
                            </span>
                        </div>
                        <time class="calendario__hora">{{ $m->clocked_in_at->format('H:i') }}</time>
                    </article>
                @endforeach
            </div>
        @endforeach
    </x-calendario>
@endsection
