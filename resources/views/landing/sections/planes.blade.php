<section class="seccion" id="planes">
    <div class="contenedor">
        <div class="seccion__cabecera" data-revelar>
            <span class="eyebrow">Planes</span>
            <h2>Sin matrícula.<br>Sin permanencia.</h2>
            <p class="lead">Pagas y entrenas. Si un mes no puedes venir, no pagas ese mes.</p>
        </div>

        {{-- data-carrusel: en móvil esta rejilla se vuelve un carrusel que
             avanza solo cada 3,2 s, en bucle y sin botones (ver
             carrusel.js). En escritorio el atributo no hace nada.
             data-carrusel-3d: coverflow + atenuación (P1+P2 del plan de
             carruseles 3D) — antes planes se quedaba sin esto a propósito
             ("comparar precios pide quietud"), decisión revertida a
             pedido explícito. --}}
        <div class="planes" data-carrusel="3200" data-carrusel-3d data-revelar data-revelar-grupo>
            @foreach ($planes as $plan)
                <article @class(['tarjeta', 'tarjeta--interactiva', 'plan', 'plan--destacado' => $plan->is_featured])
                         @style(["--acento-plan: {$plan->accent_color}" => $plan->accent_color])>
                    <span class="tarjeta__filo"></span>

                    <header>
                        @if ($plan->is_featured)
                            <span class="etiqueta etiqueta--fuego">El más elegido</span>
                        @endif
                        <h3 class="plan__nombre">{{ $plan->name }}</h3>
                    </header>

                    <p class="plan__lema">{{ $plan->tagline }}</p>

                    <div class="plan__precio">
                        <b>S/ {{ number_format($plan->price, 0) }}</b>
                        <small>/ {{ $plan->duracion_legible }}</small>
                    </div>

                    <ul class="plan__beneficios">
                        @foreach ($plan->features ?? [] as $beneficio)
                            <li><x-icono nombre="check" /><span>{{ $beneficio }}</span></li>
                        @endforeach
                    </ul>

                    <a @class(['btn', 'btn--bloque', $plan->is_featured ? 'btn--fuego' : 'btn--vidrio'])
                       href="#contacto"
                       data-plan="{{ $plan->name }}">
                        Quiero este
                    </a>
                </article>
            @endforeach
        </div>
    </div>
</section>
