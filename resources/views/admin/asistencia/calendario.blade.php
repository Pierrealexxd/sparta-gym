@extends('layouts.panel')

@section('titulo', 'Asistencia')
@section('subtitulo', $dentro . ' clientes dentro ahora mismo')

@if (auth()->user()->tienePermiso('asistencia.registrar'))
    @section('acciones')
        {{-- Abre el modal de marcar entrada (ver abajo) — el evento cruza de
             @section('acciones') a @section('contenido') porque el layout
             no comparte un mismo x-data entre las dos. --}}
        <button class="btn btn--fuego" type="button" @click="window.dispatchEvent(new CustomEvent('abrir-torno'))">
            <x-icono nombre="entrada" /> Marcar entrada
        </button>
    @endsection
@endif

@section('contenido')
    @include('admin.asistencia._pestanas')

    <form class="panel__toolbar" method="GET">
        <select class="campo__control" name="entrenador" style="max-width:220px">
            <option value="">Todos los entrenadores</option>
            @foreach ($entrenadores as $e)
                <option value="{{ $e->id }}" @selected($entrenador === $e->id)>{{ $e->name }}</option>
            @endforeach
        </select>
        <div class="panel__busqueda">
            <x-icono nombre="lupa" />
            <input class="campo__control" type="search" name="cliente" value="{{ $cliente }}"
                   placeholder="Buscar cliente…">
        </div>
        <button class="btn btn--vidrio" type="submit">Filtrar</button>
    </form>

    <x-calendario ruta="admin.asistencia.calendario" :anterior="$anterior" :siguiente="$siguiente"
                  :celdas="$celdas" contador-texto="asistencia" :filtros="$filtros">
        @foreach ($porDia as $fecha => $lista)
            <div x-show="diaAbierto === '{{ $fecha }}'" x-cloak class="calendario__lista">
                @foreach ($lista->sortByDesc('checked_in_at') as $a)
                    <article class="calendario__rutina">
                        <div>
                            <b class="es-fuerte" style="color:var(--hueso)">{{ $a->member?->full_name ?? '—' }}</b>
                            <span class="calendario__meta">
                                {{ $a->checked_out_at ? 'Salió ' . $a->checked_out_at->format('H:i') : 'Sigue dentro' }}
                                · {{ ucfirst($a->method) }} · {{ $a->registeredBy?->name ?? '—' }}
                                @if ($modoTodas) · <span class="estado">{{ $a->gym?->name ?? '—' }}</span> @endif
                            </span>
                        </div>
                        <time class="calendario__hora">{{ $a->checked_in_at->format('H:i') }}</time>
                        @unless ($a->checked_out_at)
                            <form method="POST" action="{{ route('admin.asistencia.salir', $a) }}" style="margin-top:var(--e-3)">
                                @csrf
                                <button class="btn btn--desnudo" type="submit">Marcar salida</button>
                            </form>
                        @endunless
                    </article>
                @endforeach
            </div>
        @endforeach
    </x-calendario>

    @if (auth()->user()->tienePermiso('asistencia.registrar'))
        {{-- Marcar entrada/salida de un socio (código, documento o QR) — antes
             era su propia pantalla ("Torno"), ahora es un modal de acá.
             Se abre solo si la última sumisión trajo un error de validación,
             para no perder lo tecleado. --}}
        <div x-data="{ abierta: {{ $errors->has('busqueda') ? 'true' : 'false' }} }"
             x-on:abrir-torno.window="abierta = true"
             @keydown.escape.window="abierta = false">
            <div class="modal__fondo" x-show="abierta" x-cloak>
                <form class="tarjeta modal__caja" method="POST" action="{{ route('admin.asistencia.entrar') }}"
                      @click.outside="abierta = false" style="display:grid;gap:var(--e-4)">
                    @csrf
                    <div class="modal__cabecera">
                        <h3>Marcar entrada de socio</h3>
                        <button class="modal__cerrar" type="button" @click="abierta = false" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
                    </div>

                    <label class="campo">
                        <span class="campo__etiqueta">Código, documento o QR</span>
                        <input class="campo__control" type="text" name="busqueda" autofocus placeholder="Escanea o escribe el código del socio…"
                               @error('busqueda') aria-invalid="true" @enderror>
                        @error('busqueda')<span class="campo__error">{{ $message }}</span>@enderror
                    </label>
                    <button class="btn btn--fuego btn--grande" type="submit"><x-icono nombre="entrada" /> Marcar entrada / salida</button>
                </form>
            </div>
        </div>
    @endif
@endsection
