@extends('layouts.panel')

@section('titulo', 'Mi rutina')

@section('contenido')
    {{-- Módulo canónico de la rutina (PROMPT-EJECUCION-MI-RUTINA.md, Parte 1):
         pensada para leerse de pie, con el celular en la mano, entre series —
         nada de tablas densas, tipografía cómoda y objetivos táctiles
         grandes. El dashboard y Mi progreso enlazan acá en vez de repetir el
         detalle completo. --}}
    @if ($rutinaActiva)
        <article class="tarjeta" data-revelar>
            <h3 style="font-size:var(--t-xl);font-family:var(--f-display)">{{ $rutinaActiva->name }}</h3>
            @if ($rutinaActiva->program)
                <p style="color:var(--brasa);font-size:var(--t-sm);margin-top:var(--e-1)">Programa: {{ $rutinaActiva->program->name }}</p>
            @endif
            {{-- Solo aporta cuando la rutina NO viene de un programa (el
                 entrenador la armó directo): ahí "objective" es el único
                 dato de para qué es. Si viene de un programa, el nombre de
                 arriba ya lo dice y esto quedaría repetido — se omite.
                 Bug real encontrado en QA: se imprimía el valor crudo del
                 enum ("ganar_masa") en vez de la etiqueta legible. --}}
            @if ($rutinaActiva->objective && ! $rutinaActiva->program)
                @php
                    $etiquetasObjetivo = [
                        'ganar_masa' => 'Ganar masa', 'perder_grasa' => 'Perder grasa',
                        'fuerza' => 'Fuerza', 'resistencia' => 'Resistencia',
                        'salud' => 'Salud', 'otro' => 'Otro',
                    ];
                @endphp
                <p style="color:var(--ceniza);font-size:var(--t-sm);margin-top:var(--e-3)">
                    {{ $etiquetasObjetivo[$rutinaActiva->objective] ?? ucfirst($rutinaActiva->objective) }}
                </p>
            @endif
            @if ($rutinaActiva->notes)
                <p style="color:var(--humo);font-size:var(--t-sm);margin-top:var(--e-2)">{{ $rutinaActiva->notes }}</p>
            @endif
        </article>

        <div class="rutina-completa" data-revelar data-revelar-grupo>
            @foreach ($rutinaActiva->days as $dia)
                <article class="tarjeta rutina-dia-completo" x-data="{ abierta: {{ $loop->first ? 'true' : 'false' }} }">
                    <button type="button" class="rutina-dia-completo__cabecera" @click="abierta = !abierta" :aria-expanded="abierta.toString()">
                        <span class="rutina-dia-completo__titulos">
                            <b class="rutina-dia-completo__nombre">{{ $dia->name }}</b>
                            @if ($dia->focus)
                                <span class="rutina-dia-completo__foco">{{ $dia->focus }}</span>
                            @endif
                        </span>
                        <span class="rutina-dia__flecha" :style="'transform:rotate(' + (abierta ? '180deg' : '0deg') + ')'">
                            <x-icono nombre="flecha-der" style="width:1.2em;height:1.2em;transform:rotate(90deg)" />
                        </span>
                    </button>

                    <div class="rutina-ejercicios" x-show="abierta" x-cloak>
                        @forelse ($dia->exercises as $re)
                            <div class="rutina-ejercicio">
                                <p class="rutina-ejercicio__nombre">{{ $re->exercise->name }}</p>
                                @if ($re->prescripcion)
                                    <p class="rutina-ejercicio__prescripcion">{{ $re->prescripcion }}</p>
                                @endif
                                @if ($re->notes)
                                    <p class="rutina-ejercicio__notas">{{ $re->notes }}</p>
                                @endif
                            </div>
                        @empty
                            <x-estado-vacio icono="lista" texto="Sin ejercicios en este día." />
                        @endforelse
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <article class="tarjeta" data-revelar>
            <x-estado-vacio icono="pesa" texto="Todavía no tienes una rutina asignada. Elige un programa en la página principal o pásate por recepción para que te armen una." />
            <div style="text-align:center;margin-top:var(--e-5)">
                <a href="{{ route('landing') }}#programas" class="btn btn--fuego">Ver programas</a>
            </div>
        </article>
    @endif
@endsection
