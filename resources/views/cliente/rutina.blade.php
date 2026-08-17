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

        {{-- FASE 3 de PLAN-GUIAS-EJERCICIO.md: recomendaciones del programa
             (alimentación, recuperación, hidratación, suplementos). Solo si
             la rutina viene de un programa y tiene al menos una cargada. --}}
        @if ($rutinaActiva->program)
            @php
                $programaGuia = $rutinaActiva->program;
                $tieneRecomendaciones = collect([
                    $programaGuia->nutrition_tips,
                    $programaGuia->recovery_tips,
                    $programaGuia->hydration_tips,
                    $programaGuia->supplements_tips,
                ])->filter()->isNotEmpty();
            @endphp

            @if ($tieneRecomendaciones)
                <article class="tarjeta recomendaciones" data-revelar>
                    <h3 style="font-family:var(--f-display);font-size:var(--t-lg)">Recomendaciones del programa</h3>

                    @if ($programaGuia->nutrition_tips)
                        <div class="recomendacion">
                            <h4><x-icono nombre="plato" /> Alimentación</h4>
                            <ul>
                                @foreach ($programaGuia->nutrition_tips as $tip)
                                    <li>{{ $tip }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($programaGuia->recovery_tips)
                        <div class="recomendacion">
                            <h4><x-icono nombre="reloj" /> Recuperación</h4>
                            <ul>
                                @foreach ($programaGuia->recovery_tips as $tip)
                                    <li>{{ $tip }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($programaGuia->hydration_tips)
                        <div class="recomendacion">
                            <h4><x-icono nombre="gota" /> Hidratación</h4>
                            <ul>
                                @foreach ($programaGuia->hydration_tips as $tip)
                                    <li>{{ $tip }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($programaGuia->supplements_tips)
                        <div class="recomendacion">
                            <h4><x-icono nombre="polvo" /> Suplementos</h4>
                            <ul>
                                @foreach ($programaGuia->supplements_tips as $tip)
                                    <li>{{ $tip }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </article>
            @endif
        @endif

        {{-- FASE 2 de PLAN-GUIAS-EJERCICIO.md: modal de guía por ejercicio,
             con estado compartido a nivel de toda la rutina (un solo modal,
             no uno por día) para no duplicar el árbol x-show en cada tarjeta. --}}
        <div class="rutina-completa" data-revelar data-revelar-grupo
             x-data="{
                ejercicioModal: null,
                abrirEjercicio(ej) { this.ejercicioModal = ej },
                cerrarEjercicio() { this.ejercicioModal = null }
             }">
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
                                <div style="display:flex;justify-content:space-between;align-items:start;gap:var(--e-3)">
                                    <p class="rutina-ejercicio__nombre">{{ $re->exercise->name }}</p>
                                    @if ($re->effective_description || $re->effective_video_embed)
                                        <button type="button" class="btn btn--desnudo btn--pequeno"
                                                @click="abrirEjercicio({
                                                    nombre: @js($re->exercise->name),
                                                    video: @js($re->effective_video_embed),
                                                    descripcion: @js($re->effective_description),
                                                    tips: @js($re->effective_tips),
                                                    errores: @js($re->effective_common_mistakes),
                                                    musculos: @js($re->exercise->muscle_groups),
                                                    equipo: @js($re->exercise->equipment),
                                                    imagen: @js($re->exercise->image_path ? asset('storage/' . $re->exercise->image_path) : null)
                                                })">
                                            <x-icono nombre="youtube" /> Ver guía
                                        </button>
                                    @endif
                                </div>
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

            {{-- Modal de guía de ejercicio: reutiliza la estructura
                 .modal__fondo/.modal__caja ya usada en todo el panel (el
                 ".video" de la landing vive en landing.css, que este layout
                 no carga — ver panel.css para el mismo componente aplicado
                 a este caso). --}}
            <div class="modal__fondo" x-show="ejercicioModal" x-cloak
                 @keydown.escape.window="cerrarEjercicio()"
                 role="dialog" aria-modal="true" aria-label="Guía de ejercicio">
                <div class="tarjeta modal__caja modal__caja--ancho" @click.outside="cerrarEjercicio()">
                    <div class="modal__cabecera">
                        <h3 x-text="ejercicioModal?.nombre"></h3>
                        <button type="button" class="modal__cerrar" @click="cerrarEjercicio()" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
                    </div>

                    <template x-if="ejercicioModal?.video">
                        <div class="ejercicio-detalle__video">
                            <iframe :src="ejercicioModal?.video" title="Video tutorial" loading="lazy" allowfullscreen
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                        </div>
                    </template>

                    <template x-if="!ejercicioModal?.video && ejercicioModal?.imagen">
                        <img :src="ejercicioModal?.imagen" :alt="ejercicioModal?.nombre"
                             style="width:100%;border-radius:var(--r-md);object-fit:cover;margin-bottom:var(--e-4)">
                    </template>

                    <div class="ejercicio-detalle">
                        <template x-if="ejercicioModal?.descripcion">
                            <div class="ejercicio-detalle__seccion">
                                <h4>Descripción</h4>
                                <p x-text="ejercicioModal?.descripcion"></p>
                            </div>
                        </template>

                        <template x-if="ejercicioModal?.tips">
                            <div class="ejercicio-detalle__seccion ejercicio-detalle__seccion--tips">
                                <h4>Hazlo así</h4>
                                <p x-text="ejercicioModal?.tips"></p>
                            </div>
                        </template>

                        <template x-if="ejercicioModal?.errores">
                            <div class="ejercicio-detalle__seccion ejercicio-detalle__seccion--errores">
                                <h4>Evita</h4>
                                <p x-text="ejercicioModal?.errores"></p>
                            </div>
                        </template>

                        <div class="ejercicio-detalle__meta">
                            <template x-if="ejercicioModal?.musculos?.length">
                                <div>
                                    <h4>Músculos</h4>
                                    <div class="ejercicio-detalle__musculos">
                                        <template x-for="m in ejercicioModal?.musculos" :key="m">
                                            <span class="etiqueta" x-text="m"></span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <template x-if="ejercicioModal?.equipo">
                                <div><h4>Equipo</h4><p x-text="ejercicioModal?.equipo"></p></div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
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
