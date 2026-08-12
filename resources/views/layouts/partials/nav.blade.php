<header class="nav" data-nav>
    <a class="nav__marca" href="{{ route('landing') }}">
        <span>Sparta</span><em>Gym</em>
    </a>

    {{-- Velo oscuro detrás del cajón: cierra el menú al tocar afuera (ver
         ui.js) y separa visualmente el cajón del resto de la página. --}}
    <div class="nav__velo" data-menu-velo></div>

    <nav class="nav__enlaces" data-menu-panel aria-label="Secciones">
        {{-- Cabecera propia del cajón: la marca lo identifica (antes,
             abierto, no se veía ni el logo ni de qué sitio era el menú) y
             el aspa de cerrar vive aquí, sobre el fondo sólido del propio
             panel — antes era el mismo botón hamburguesa de la barra, que
             al abrirse quedaba sobre el video atenuado por el velo, a
             medio ver. Ese botón se oculta mientras el cajón está abierto
             (ver landing.css) y este toma su lugar. --}}
        <div class="nav__enlaces-cabecera">
            <span class="nav__enlaces-marca"><span>Sparta</span><em>Gym</em></span>
            <button type="button" class="nav__enlaces-cerrar" data-menu-cerrar aria-label="Cerrar menú">
                <x-icono nombre="cerrar" />
            </button>
        </div>

        <div class="nav__enlaces-lista">
            <a class="nav__enlace" href="#historia">Historia</a>
            <a class="nav__enlace" href="#ejercicios">Biblioteca</a>
            <a class="nav__enlace" href="#guias">Guías</a>
            <a class="nav__enlace" href="#planes">Planes</a>
            <a class="nav__enlace" href="#preguntas">Preguntas</a>
            <a class="nav__enlace" href="#contacto">Contacto</a>
        </div>

        {{-- Los botones de .nav__acciones se ocultan en móvil/tablet (ver
             landing.css) — sin esto, un socio no tiene ninguna vía visible
             para entrar desde el celular. --}}
        <div class="nav__enlaces-pie">
            <a class="nav__enlace nav__enlace--cuenta" href="{{ auth()->check() ? route(auth()->user()->rutaDeInicio()) : route('login') }}">
                {{ auth()->check() ? 'Mi panel' : 'Acceder' }}
            </a>
            @guest
                <a class="btn btn--fuego nav__enlace--inscribirme" href="#planes">Inscribirme</a>
            @endguest
        </div>
    </nav>

    <div class="nav__acciones">
        @auth
            <a class="btn btn--fuego" href="{{ route(auth()->user()->rutaDeInicio()) }}">Mi panel</a>
        @else
            <a class="btn btn--vidrio" href="{{ route('login') }}">Acceder</a>
            <a class="btn btn--fuego" href="#planes">Inscribirme</a>
        @endauth

        <button class="nav__menu" data-menu type="button"
                aria-expanded="false" aria-label="Abrir menú">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>
