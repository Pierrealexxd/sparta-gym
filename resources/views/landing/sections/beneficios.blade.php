{{-- Retícula "bento": una pieza manda (grande), el resto la acompaña.
     Referencia: las tarjetas de experimentos de Google Labs — tamaños
     desiguales y cada una reacciona al cursor (.tarjeta--interactiva,
     ver interacciones.js). El color del brillo es de la marca, no el
     arcoíris de Labs; lo prestado es el mecanismo, no la paleta.

     En móvil el bento se aplana a una rejilla 2×2 de solo título (ver
     landing.css): el texto completo no cabe sin volverse un scroll largo,
     así que se mueve a un modal que se abre al tocar la tarjeta. En
     escritorio no hace falta —el texto ya está a la vista— así que el
     click solo abre el modal por debajo de 741px (ver comprobación de
     innerWidth abajo). --}}
@php
    $beneficios = [
        ['pesa',     'Hierro de sobra',      'Cuatro racks, dos plataformas y mancuernas hasta 60 kg. Nunca esperas turno más de un par de minutos.', 'grande'],
        ['objetivo', 'Rutina para ti',       'Sales del primer día con un plan hecho para tu nivel y tu objetivo, no con una hoja fotocopiada.', 'ancha'],
        ['grafico',  'Progreso medido',      'Peso, medidas e IMC registrados cada mes. Ves tu evolución en tu panel, no en tu memoria.', null],
        ['usuarios', 'Alguien te espera',    'Entrenadores en sala corrigiendo técnica. Si desapareces dos semanas, te escribimos.', null],
    ];
@endphp
<section class="seccion" x-data="{ abierto: null }">
    <div class="contenedor">
        <div class="seccion__cabecera" data-revelar>
            <span class="eyebrow">Por qué aquí</span>
            <h2>Lo que sí hacemos</h2>
            <p class="lead">Cuatro cosas. Las hacemos bien y no prometemos más.</p>
        </div>

        <div class="bento beneficios" data-revelar data-revelar-grupo>
            @foreach ($beneficios as $i => [$icono, $titulo, $texto, $tamano])
                <article @class([
                            'tarjeta', 'tarjeta--interactiva', 'beneficio', 'bento__pieza',
                            "bento__pieza--$tamano" => $tamano,
                        ])
                        @click="if (window.innerWidth <= 740) abierto = {{ $i }}">
                    <span class="tarjeta__filo"></span>
                    <div class="beneficio__icono"><x-icono :nombre="$icono" /></div>
                    <h3>{{ $titulo }}</h3>
                    <p>{{ $texto }}</p>
                </article>
            @endforeach
        </div>
    </div>

    {{-- Modal informativo: solo se abre en móvil (ver arriba), con el
         mismo lenguaje visual del modal de video de la biblioteca. --}}
    <div class="modal-info" x-cloak x-show="abierto !== null" @keydown.escape.window="abierto = null"
         role="dialog" aria-modal="true" aria-label="Detalle">
        <div class="modal-info__fondo" x-show="abierto !== null" x-transition.opacity @click="abierto = null"></div>
        <div class="modal-info__caja" x-show="abierto !== null"
             x-transition:enter="modal-info__entra" x-transition:enter-start="modal-info__entra-desde" x-transition:enter-end="modal-info__entra-hasta"
             x-transition:leave="modal-info__entra" x-transition:leave-start="modal-info__entra-hasta" x-transition:leave-end="modal-info__entra-desde">
            <button type="button" class="modal-info__cerrar" @click="abierto = null" aria-label="Cerrar">
                <x-icono nombre="cerrar" />
            </button>
            @foreach ($beneficios as $i => [$icono, $titulo, $texto, $tamano])
                <template x-if="abierto === {{ $i }}">
                    <div class="modal-info__cuerpo">
                        <div class="beneficio__icono"><x-icono :nombre="$icono" /></div>
                        <h3>{{ $titulo }}</h3>
                        <p>{{ $texto }}</p>
                    </div>
                </template>
            @endforeach
        </div>
    </div>
</section>
