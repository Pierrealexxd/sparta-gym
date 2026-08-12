@props([
    'ruta',
    'anterior',
    'siguiente',
    'celdas' => [],
    'contadorTexto' => 'movimiento',
    'filtros' => [],
])

{{-- Cuadrícula mensual compartida por Personal, Actividad y Asistencias:
     la misma que se construía a mano en cada vista, extraída a un solo
     lugar para que no haya cuatro copias que divergir. La navegación por
     mes, las celdas con contador y el esqueleto del modal viven acá; la
     lista de items de cada día la aporta el padre en el slot, con acceso
     a sus propias colecciones agrupadas por fecha.

     El contexto del mes ($nombreMes, $offset, $diasDelMes, $anio, $mes)
     se deriva de $anterior — el primer día del mes previo — en vez de
     pedirlo al padre: un componente anónimo no hereda el scope de la
     vista que lo invoca, solo sus atributos y el slot. --}}
@php
    $primerDia = $anterior->copy()->addMonth();
    $nombreMes = $primerDia->translatedFormat('F Y');
    $offset    = $primerDia->dayOfWeek === 0 ? 6 : $primerDia->dayOfWeek - 1;
    $diasDelMes = $primerDia->daysInMonth;
    $anio = $primerDia->year;
    $mes  = $primerDia->month;
@endphp
<div x-data="{ diaAbierto: null, itemAbierto: null }">
    <div class="calendario__nav" data-revelar>
        <a class="btn btn--vidrio" href="{{ route($ruta, array_merge(['mes' => $anterior->month, 'anio' => $anterior->year], $filtros)) }}">
            <x-icono nombre="flecha-der" style="transform:rotate(180deg)" /> {{ $anterior->translatedFormat('M') }}
        </a>
        <h2 class="calendario__titulo">{{ ucfirst($nombreMes) }}</h2>
        <a class="btn btn--vidrio" href="{{ route($ruta, array_merge(['mes' => $siguiente->month, 'anio' => $siguiente->year], $filtros)) }}">
            {{ $siguiente->translatedFormat('M') }} <x-icono nombre="flecha-der" />
        </a>
    </div>

    <div class="calendario" data-revelar data-revelar-grupo>
        @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dia)
            <span class="calendario__cabecera">{{ $dia }}</span>
        @endforeach

        @for ($i = 0; $i < $offset; $i++)
            <span class="calendario__celda calendario__celda--vacia"></span>
        @endfor

        @for ($dia = 1; $dia <= $diasDelMes; $dia++)
            @php
                $fecha = \Illuminate\Support\Carbon::create($anio, $mes, $dia)->toDateString();
                $cantidad = $celdas[$fecha] ?? 0;
            @endphp
            <button type="button"
                    class="calendario__celda {{ $cantidad ? 'calendario__celda--con-actividad' : '' }}"
                    @if ($cantidad) @click="diaAbierto = '{{ $fecha }}'; itemAbierto = null" @endif>
                <span class="calendario__numero">{{ $dia }}</span>
                @if ($cantidad)
                    <span class="calendario__contador">{{ $cantidad }} {{ $cantidad === 1 ? $contadorTexto : $contadorTexto . 's' }}</span>
                @endif
            </button>
        @endfor
    </div>

    <div class="modal__fondo" x-show="diaAbierto" x-cloak
         @keydown.escape.window="diaAbierto = null">
        <div class="tarjeta modal__caja" @click.outside="diaAbierto = null">
            <div class="modal__cabecera">
                <h3 style="font-size:var(--t-lg)" x-text="diaAbierto ? new Date(diaAbierto + 'T12:00:00').toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long' }) : ''"></h3>
                <button class="modal__cerrar" type="button" @click="diaAbierto = null"><x-icono nombre="cerrar" /></button>
            </div>
            {{ $slot }}
        </div>
    </div>
</div>
