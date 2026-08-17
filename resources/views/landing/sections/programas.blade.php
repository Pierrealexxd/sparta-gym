{{-- Programas: los dos caminos que ofrece el gimnasio, debajo de la
     biblioteca de ejercicios. Mismo patrón de tarjeta interactiva que
     ejercicios.blade.php; el contenido viene de la BD (tabla programs, ver
     ProgramSeeder), no hardcodeado. El detalle completo vive en el modal de
     abajo (Fase 2) — acá sólo la tarjeta y su llamado a la acción. --}}
@php
    // Ejemplos por objetivo, aplanados a lo que necesita la tarjeta del
    // modal (nombre/categoría/equipo) — ver LandingController::index().
    $ejemplosPlanos = collect($ejemplosPrograma ?? [])->map(
        fn ($lista) => $lista->map(fn ($e) => ['nombre' => $e->name, 'categoria' => $e->category, 'equipo' => $e->equipment])->all()
    );
@endphp
<section class="seccion" id="programas"
         x-data="{ programaActivo: null, programas: {{ Illuminate\Support\Js::from($programs->map(fn ($p) => [
             'slug' => $p->slug, 'nombre' => $p->name, 'tagline' => $p->tagline,
             'descripcion' => $p->description, 'highlights' => $p->highlights,
             'icono' => $p->icon, 'duracion' => $p->duration_weeks, 'dificultad' => $p->difficulty,
             'ejemplos' => $ejemplosPlanos[$p->objective] ?? [],
         ])) }}, abrirPrograma(slug) { this.programaActivo = this.programas.find(p => p.slug === slug); } }">
    <div class="contenedor">
        <div class="seccion__cabecera" data-revelar>
            <span class="eyebrow">Programas</span>
            <h2>Elige tu <span class="fuego">camino</span></h2>
            <p class="lead">Dos objetivos. Dos estrategias. Un solo gimnasio.</p>
        </div>

        <div class="programas" data-revelar data-revelar-grupo>
            @foreach ($programs as $programa)
                <article class="tarjeta tarjeta--interactiva programa"
                         @click="abrirPrograma('{{ $programa->slug }}')">
                    <span class="tarjeta__filo"></span>

                    <div class="programa__icono">
                        <x-icono :nombre="$programa->icon" />
                    </div>
                    <h3 class="programa__nombre">{{ $programa->name }}</h3>
                    <p class="programa__objetivo">{{ $programa->tagline }}</p>

                    <ul class="programa__caracteristicas">
                        @foreach (array_slice($programa->highlights ?? [], 0, 3) as $rasgo)
                            <li><x-icono nombre="check" /> {{ $rasgo }}</li>
                        @endforeach
                    </ul>

                    <span class="btn btn--fuego programa__cta">
                        Ver programa <x-icono nombre="flecha-der" />
                    </span>
                </article>
            @endforeach
        </div>
    </div>

    @include('landing.sections.programa-modal')
</section>
