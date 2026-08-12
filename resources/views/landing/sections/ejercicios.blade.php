{{-- La biblioteca: cada ejercicio de la sala con su técnica, su error más
     común y, si existe, su video. El filtro es Alpine puro: sin peticiones. --}}
<section class="seccion" id="ejercicios"
         x-data="{ activa: 'todas', video: null, abrir(titulo, url) { this.video = { titulo, url } } }">
    <div class="contenedor">
        <div class="seccion__cabecera" data-revelar>
            <span class="eyebrow">La biblioteca</span>
            <h2>Aprende antes<br>de <span class="fuego">cargar</span></h2>
            <p class="lead">Técnica antes que peso. Mira el video, lee el error y llega a la sala sabiendo lo que vas a hacer.</p>
        </div>

        <div class="filtros" data-revelar>
            <button type="button" class="filtro"
                    :class="{ 'is-activo': activa === 'todas' }" @click="activa = 'todas'">
                Todas <b>{{ $ejercicios->count() }}</b>
            </button>
            @foreach ($categorias as $categoria)
                <button type="button" class="filtro"
                        :class="{ 'is-activo': activa === '{{ $categoria }}' }" @click="activa = '{{ $categoria }}'">
                    {{ ucfirst($categoria) }} <b>{{ $ejercicios->where('category', $categoria)->count() }}</b>
                </button>
            @endforeach
        </div>

        <div class="biblioteca" data-revelar data-revelar-grupo>
            @foreach ($ejercicios as $ejercicio)
                <article class="tarjeta ejercicio"
                         x-show="activa === 'todas' || activa === '{{ $ejercicio->category }}'">
                    <span class="tarjeta__filo"></span>

                    <div class="ejercicio__migas">
                        <span class="etiqueta">{{ ucfirst($ejercicio->category) }}</span>
                        <span class="ejercicio__nivel">{{ ucfirst($ejercicio->level) }}</span>
                    </div>

                    <h3 class="ejercicio__nombre">{{ $ejercicio->name }}</h3>

                    <p class="ejercicio__equipo">
                        <x-icono nombre="pesa" />
                        <span>{{ $ejercicio->equipment }}</span>
                    </p>

                    <ul class="ejercicio__musculos">
                        @foreach ($ejercicio->muscle_groups as $musculo)
                            <li>{{ $musculo }}</li>
                        @endforeach
                    </ul>

                    <div class="ejercicio__claves">
                        <div class="ejercicio__clave ejercicio__clave--evita">
                            <b>Evita</b>
                            <p>{{ $ejercicio->common_mistakes }}</p>
                        </div>
                        <div class="ejercicio__clave">
                            <b>Hazlo así</b>
                            <p>{{ $ejercicio->tips }}</p>
                        </div>
                    </div>

                    @if ($ejercicio->video_embed)
                        <button type="button" class="btn btn--vidrio ejercicio__video"
                                @click="abrir('{{ $ejercicio->name }}', '{{ $ejercicio->video_embed }}')">
                            <x-icono nombre="youtube" />
                            Ver técnica
                        </button>
                    @endif
                </article>
            @endforeach
        </div>

        {{-- Flechas + contador (no puntos): con hasta 18 tarjetas en
             "Todas", un punto por tarjeta sería más ruido que ayuda. Se
             reconstruye solo con "filtro-cambiado" (arriba), no con cada
             cambio de x-show individual. --}}
        <div class="carrusel__control" data-carrusel-control data-carrusel-objetivo=".biblioteca" data-carrusel-modo="flechas" data-carrusel-auto="6500" aria-hidden="true">
            <button type="button" class="carrusel__flecha" data-carrusel-prev aria-label="Ejercicio anterior">‹</button>
            <span class="carrusel__contador" data-carrusel-contador>1 / 1</span>
            <button type="button" class="carrusel__flecha" data-carrusel-next aria-label="Siguiente ejercicio">›</button>
        </div>

        <p class="biblioteca__nota" data-revelar>
            ¿No encuentras lo tuyo? Pregunta en recepción. Si no está en la sala, lo pedimos.
        </p>
    </div>

    {{-- Modal de video: se llena desde Alpine y se cierra con Escape o al fondo --}}
    <div class="video" x-cloak x-show="video" @keydown.escape.window="video = null"
         role="dialog" aria-modal="true" aria-label="Video de técnica">
        <div class="video__fondo" @click="video = null"></div>
        <div class="video__caja">
            <button type="button" class="video__cerrar" @click="video = null" aria-label="Cerrar video">
                <x-icono nombre="cerrar" />
            </button>
            <h3 class="video__titulo" x-text="video?.titulo"></h3>
            <div class="video__marco">
                <iframe :src="video?.url" title="Video tutorial" loading="lazy" allowfullscreen
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
            </div>
        </div>
    </div>
</section>
