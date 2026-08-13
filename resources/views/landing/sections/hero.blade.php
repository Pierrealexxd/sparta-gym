{{-- El hero: un video de fondo que arranca solo en cuanto carga la
     página y se reproduce en bucle. Nada de scroll-jacking: la sección
     mide una pantalla y el usuario baja con normalidad, como el resto
     de la página, dejando el video atrás. --}}
<section class="hero" data-hero>

    <div class="hero__video-capa" aria-hidden="true">
        @if ($heroVideo)
            {{-- poster: mientras el video todavía se está pidiendo (red lenta,
                 o el único worker de Render ocupado con otra petición), se ve
                 el primer fotograma en vez de blanco — el "corte" al arrancar
                 se siente menos brusco. --}}
            <video class="hero__video" autoplay muted loop playsinline preload="auto"
                   @if ($heroPoster) poster="{{ $heroPoster }}" @endif>
                <source src="{{ $heroVideo }}" type="video/mp4">
            </video>
        @endif
        <div class="hero__vigneta"></div>
    </div>

    <div class="hero__contenido">
        <span class="eyebrow" data-hero-eyebrow>{{ $gym->city }} · Desde 2019</span>
        <h1 class="hero__moto" data-hero-titulo>
            <span>Hierro.</span>
            <span>Sudor.</span>
            <span class="fuego">Sangre.</span>
        </h1>
        <p class="hero__entradilla" data-hero-texto>Un gimnasio sin atajos. Hierro de
            verdad, entrenadores que corrigen y una comunidad que nota
            cuando faltas.</p>
        <div class="hero__acciones" data-hero-acciones>
            <a class="btn btn--fuego btn--grande" href="#historia">Ver más</a>
        </div>
    </div>

    {{-- Las cifras reales, en la base del escenario. --}}
    <div class="hero__pie">
        <div class="hero__cifras">
            <div class="hero__cifra" data-hero-cifra>
                <b data-contador="{{ $cifras['clientes'] }}" data-sufijo="+">0</b>
                <small>Clientes activos</small>
            </div>
            <div class="hero__cifra" data-hero-cifra>
                <b data-contador="{{ $cifras['sesiones'] }}">0</b>
                <small>Sesiones registradas</small>
            </div>
            <div class="hero__cifra" data-hero-cifra>
                <b data-contador="{{ $cifras['entrenadores'] }}">0</b>
                <small>Entrenadores</small>
            </div>
            <div class="hero__cifra" data-hero-cifra>
                <b data-contador="{{ $cifras['anios'] }}">0</b>
                <small>Años abiertos</small>
            </div>
        </div>
    </div>
</section>
