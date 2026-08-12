<section class="seccion" id="planes">
    <div class="contenedor">
        <div class="seccion__cabecera" data-revelar>
            <span class="eyebrow">Planes</span>
            <h2>Sin matrícula.<br>Sin permanencia.</h2>
            <p class="lead">Pagas y entrenas. Si un mes no puedes venir, no pagas ese mes.</p>
        </div>

        <div class="planes" data-revelar data-revelar-grupo>
            @foreach ($planes as $plan)
                <article @class(['tarjeta', 'tarjeta--interactiva', 'plan', 'plan--destacado' => $plan->is_featured])>
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
