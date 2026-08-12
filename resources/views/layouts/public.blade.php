<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0A0A0B">

    <title>@yield('titulo', $gym->name . ' · ' . $gym->tagline)</title>
    <meta name="description" content="@yield('descripcion', $gym->description)">

    {{-- Open Graph: lo que se ve al compartir el enlace por WhatsApp --}}
    <meta property="og:title" content="{{ $gym->name }} · {{ $gym->tagline }}">
    <meta property="og:description" content="{{ $gym->description }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">

    @if ($gym->logo_path)
        <link rel="icon" href="{{ asset('storage/' . $gym->logo_path) }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/app-public.js'])
</head>
<body>
    {{-- Splash de entrada: se retira solo (ver ui.js) apenas la página está
         lista, con un mínimo de medio segundo para que no sea un parpadeo.
         Vive sólo aquí, no en el panel: la landing nunca cambia de tema
         (siempre oscura, ver AGENTS.md), así que no hay claro/oscuro que
         conciliar. `prefers-reduced-motion` ya congela toda animación vía
         base.css; aquí sólo evitamos además la espera artificial. --}}
    @if ($logoUrl)
        <div class="splash" data-splash aria-hidden="true">
            <div class="splash__nube"></div>
            <div class="splash__anillo splash__anillo--1"></div>
            <div class="splash__anillo splash__anillo--2"></div>
            <div class="splash__anillo splash__anillo--3"></div>
            <div class="splash__anillo splash__anillo--4"></div>
            <img class="splash__logo" src="{{ $logoUrl }}" alt="">
        </div>
    @endif

    <a class="saltar" href="#contenido">Saltar al contenido</a>

    {{-- Ascuas de fondo: antes vivían sólo dentro del hero; ahora es una capa
         fija de toda la página, para que el rastro de fuego acompañe el
         scroll por Historia, Planes, etc. y no desaparezca al salir del
         primer tramo. Un único <canvas>, siempre el mismo, gestionado por
         particulas.js (ver resources/js/particulas.js). --}}
    <div class="atmosfera-embers" aria-hidden="true">
        <canvas data-particulas></canvas>
    </div>

    {{-- El logotipo como marca de agua: el video del hero ya lo muestra en
         movimiento, así que aquí queda fijo en pantalla y muy tenue, de
         fondo bajo el contenido, el resto de la página. --}}
    @if ($logoUrl)
        <div class="marca-agua" aria-hidden="true">
            <img src="{{ $logoUrl }}" alt="">
        </div>
    @endif

    <div class="pagina">
        @include('layouts.partials.nav')

        <main id="contenido">
            @yield('contenido')
        </main>

        @include('layouts.partials.pie')
    </div>
</body>
</html>
