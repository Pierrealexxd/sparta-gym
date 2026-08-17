<header class="nav" data-nav>
    <a class="nav__marca" href="{{ route('landing') }}">
        <span>Sparta</span><em>Gym L.</em>
    </a>

    {{-- Velo oscuro detrás del cajón: cierra el menú al tocar afuera (ver
         ui.js) y separa visualmente el cajón del resto de la página. --}}
    <div class="nav__velo" data-menu-velo></div>

    <nav class="nav__enlaces" data-menu-panel aria-label="Secciones">
        {{-- Cabecera propia del cajón: solo la marca, para identificarlo
             (antes, abierto, no se veía de qué sitio era el menú). Sin
             botón de cerrar aquí — se cierra tocando fuera (el velo),
             eligiendo un enlace o con Escape; un aspa aparte al lado del
             logo sobraba. --}}
        <div class="nav__enlaces-cabecera">
            <span class="nav__enlaces-marca"><span>Sparta</span><em>Gym L.</em></span>
        </div>

        <div class="nav__enlaces-lista">
            <a class="nav__enlace" href="#historia"><x-icono nombre="reloj" /> Historia</a>
            <a class="nav__enlace" href="#ejercicios"><x-icono nombre="pesa" /> Biblioteca</a>
            <a class="nav__enlace" href="#guias"><x-icono nombre="lista" /> Guías</a>
            <a class="nav__enlace" href="#planes"><x-icono nombre="tarjetas" /> Planes</a>
            <a class="nav__enlace" href="#preguntas"><x-icono nombre="chat" /> Preguntas</a>
            <a class="nav__enlace" href="#contacto"><x-icono nombre="correo" /> Contacto</a>
        </div>

        {{-- Los botones de .nav__acciones se ocultan en móvil/tablet (ver
             landing.css) — sin esto, un socio no tiene ninguna vía visible
             para entrar desde el celular. --}}
        <div class="nav__enlaces-pie">
            <a class="nav__enlace nav__enlace--cuenta" href="{{ auth()->check() ? route(auth()->user()->rutaDeInicio()) : route('login') }}">
                <x-icono nombre="perfil" />
                {{ auth()->check() ? 'Mi panel' : 'Login' }}
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
            <a class="btn btn--vidrio" href="{{ route('login') }}">Login</a>
            <a class="btn btn--fuego" href="#planes">Inscribirme</a>
        @endauth

        <button class="nav__menu" data-menu type="button"
                aria-expanded="false" aria-label="Abrir menú">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>
