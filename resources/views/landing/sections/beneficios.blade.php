{{-- Retícula "bento": una pieza manda (grande), el resto la acompaña.
     Referencia: las tarjetas de experimentos de Google Labs — tamaños
     desiguales y cada una reacciona al cursor (.tarjeta--interactiva,
     ver interacciones.js). El color del brillo es de la marca, no el
     arcoíris de Labs; lo prestado es el mecanismo, no la paleta. --}}
<section class="seccion">
    <div class="contenedor">
        <div class="seccion__cabecera" data-revelar>
            <span class="eyebrow">Por qué aquí</span>
            <h2>Lo que sí hacemos</h2>
            <p class="lead">Cuatro cosas. Las hacemos bien y no prometemos más.</p>
        </div>

        <div class="bento beneficios" data-revelar data-revelar-grupo>
            @foreach ([
                ['pesa',     'Hierro de sobra',      'Cuatro racks, dos plataformas y mancuernas hasta 60 kg. Nunca esperas turno más de un par de minutos.', 'grande'],
                ['objetivo', 'Rutina para ti',       'Sales del primer día con un plan hecho para tu nivel y tu objetivo, no con una hoja fotocopiada.', 'ancha'],
                ['grafico',  'Progreso medido',      'Peso, medidas e IMC registrados cada mes. Ves tu evolución en tu panel, no en tu memoria.', null],
                ['usuarios', 'Alguien te espera',    'Entrenadores en sala corrigiendo técnica. Si desapareces dos semanas, te escribimos.', null],
            ] as [$icono, $titulo, $texto, $tamano])
                <article @class([
                    'tarjeta', 'tarjeta--interactiva', 'beneficio', 'bento__pieza',
                    "bento__pieza--$tamano" => $tamano,
                ])>
                    <span class="tarjeta__filo"></span>
                    <div class="beneficio__icono"><x-icono :nombre="$icono" /></div>
                    <h3>{{ $titulo }}</h3>
                    <p>{{ $texto }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
