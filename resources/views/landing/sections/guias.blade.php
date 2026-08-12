{{-- Guías: las tres primeras semanas, en tres tarjetas. Y la rutina
     personalizada, que es lo que convierte a un visitante en socio.

     En móvil las tarjetas se comprimen a número + título (la lista completa
     no cabe sin alargar demasiado la página) y la lista se mueve a un modal
     al tocar — mismo patrón y mismo componente que en beneficios.blade.php. --}}
@php
    $guias = [
        [
            'numero' => '01',
            'titulo' => 'La primera semana',
            'tipo'   => 'ol',
            'items'  => [
                'Pide la evaluación de ingreso. Es tuya, no un trámite.',
                'Entrena el patrón antes que el peso: la técnica manda.',
                'Termina cada serie antes de estar fundido. Mañana hay más.',
            ],
        ],
        [
            'numero' => '02',
            'titulo' => 'Entrenar bien se nota',
            'tipo'   => 'ul',
            'items'  => [
                '<b>Sin lesiones.</b> La técnica correcta protege rodillas y espalda.',
                '<b>Más progreso.</b> El mismo esfuerzo, mejor dirigido, rinde más.',
                '<b>Dormir mejor.</b> Un cuerpo fuerte se descansa y se recupera.',
            ],
        ],
        [
            'numero' => '03',
            'titulo' => 'La regla de las 48 horas',
            'tipo'   => 'ol',
            'items'  => [
                'Un grupo muscular descansa 48 horas antes de volver.',
                'Proteína en cada comida, no solo el día de pierna.',
                'Un kilo más a la semana es poco. A fin de año es mucho.',
            ],
        ],
    ];
@endphp
<section class="seccion" id="guias" x-data="{ abierta: null }">
    <div class="contenedor">
        <div class="seccion__cabecera" data-revelar>
            <span class="eyebrow">Para empezar</span>
            <h2>Tres semanas</h2>
            <p class="lead">El cuerpo se adapta a lo que le das. Dáselo bien desde el primer día.</p>
        </div>

        <div class="guias" data-revelar data-revelar-grupo>
            @foreach ($guias as $i => $guia)
                <article class="tarjeta guia" @click="if (window.innerWidth <= 740) abierta = {{ $i }}">
                    <span class="tarjeta__filo"></span>
                    <p class="guia__numero">{{ $guia['numero'] }}</p>
                    <h3>{{ $guia['titulo'] }}</h3>
                    <{{ $guia['tipo'] }} class="guia__lista">
                        @foreach ($guia['items'] as $item)
                            <li>{!! $item !!}</li>
                        @endforeach
                    </{{ $guia['tipo'] }}>
                </article>
            @endforeach
        </div>

        <div class="rutina" data-revelar>
            <div class="rutina__cuerpo">
                <span class="eyebrow">Rutina personalizada</span>
                <h3>No copies la de internet.<br>La tuya se escribe contigo.</h3>
                <p>Evaluación de ingreso, una rutina para tu nivel y tu objetivo, y control de medidas cada mes. Cuando deje de servirte, cambia.</p>
            </div>
            <div class="rutina__accion">
                <a class="btn btn--fuego btn--grande" href="#planes">Empezar hoy</a>
                <span class="rutina__nota">Incluida en el plan mensual</span>
            </div>
        </div>
    </div>

    {{-- Modal informativo: mismo componente y mismo criterio (solo móvil)
         que en beneficios.blade.php. --}}
    <div class="modal-info" x-cloak x-show="abierta !== null" @keydown.escape.window="abierta = null"
         role="dialog" aria-modal="true" aria-label="Detalle de la guía">
        <div class="modal-info__fondo" x-show="abierta !== null" x-transition.opacity @click="abierta = null"></div>
        <div class="modal-info__caja" x-show="abierta !== null"
             x-transition:enter="modal-info__entra" x-transition:enter-start="modal-info__entra-desde" x-transition:enter-end="modal-info__entra-hasta"
             x-transition:leave="modal-info__entra" x-transition:leave-start="modal-info__entra-hasta" x-transition:leave-end="modal-info__entra-desde">
            <button type="button" class="modal-info__cerrar" @click="abierta = null" aria-label="Cerrar">
                <x-icono nombre="cerrar" />
            </button>
            @foreach ($guias as $i => $guia)
                <template x-if="abierta === {{ $i }}">
                    <div class="modal-info__cuerpo">
                        <p class="guia__numero">{{ $guia['numero'] }}</p>
                        <h3>{{ $guia['titulo'] }}</h3>
                        <{{ $guia['tipo'] }} class="guia__lista">
                            @foreach ($guia['items'] as $item)
                                <li>{!! $item !!}</li>
                            @endforeach
                        </{{ $guia['tipo'] }}>
                    </div>
                </template>
            @endforeach
        </div>
    </div>
</section>
