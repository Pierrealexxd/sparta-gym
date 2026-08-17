{{-- Layout mínimo para previsualización de secciones de la landing dentro
     del panel admin. Carga los mismos CSS que la página pública (tokens,
     base, components, landing) para garantizar fidelidad visual pixel-perfecta.
     No incluye nav, footer, splash, partículas ni elementos del panel. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0A0A0B">
    <title>Previsualización</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    @vite(['resources/css/public-entry.css', 'resources/js/app-public.js'])
</head>
<body class="preview-body">
    <main>
        @yield('contenido')
    </main>

    <style>
        {{-- Reseteo del body para que la previsualización se vea idéntica a
             la landing: fondo oscuro, sin margen, scrollbar oscuro. --}}
        .preview-body {
            margin: 0;
            background: var(--obsidiana);
            color: var(--hueso);
            font-family: var(--f-texto);
            -webkit-font-smoothing: antialiased;
        }
        .preview-body::-webkit-scrollbar { width: 6px; }
        .preview-body::-webkit-scrollbar-track { background: var(--grafito); }
        .preview-body::-webkit-scrollbar-thumb { background: var(--acero); border-radius: 3px; }

        {{-- Sin padding en main: cada .seccion ya trae su propio
             padding-block: var(--seccion). Duplicarlo desperdicia
             viewport vertical dentro del iframe. --}}
        main { padding: 0; }

        {{-- En la landing real, [data-revelar] arranca en opacity:0 y GSAP
             los revela con ScrollTrigger al entrar en viewport. Dentro del
             iframe esto no se dispara porque el contenido ya está visible
             al cargar — las secciones se quedarían invisibles. Forzamos
             opacity:1 y sin transform para que todo se vea de entrada. --}}
        [data-revelar] {
            opacity: 1 !important;
            transform: none !important;
            will-change: auto !important;
        }

        {{-- Los carruseles 3D (planes, testimonios, ejercicios) usan
             --coverflow queGSAP/escribir. Sin él, las tarjetas laterales
             quedan con opacidad 0 y escalonamiento raro. --}}
        [style*="--coverflow"] {
            opacity: 1 !important;
            transform: none !important;
        }
    </style>

    <script>
        {{-- Bloquea toda navegación dentro del iframe: solo se permiten
             anclajes internos (#algo). Links a rutas externas (admin,
             login, etc.) y submits de formulario se cancelan. --}}
        document.addEventListener('click', function (e) {
            var link = e.target.closest('a[href]');
            if (!link) return;
            var href = link.getAttribute('href');
            if (href && href.startsWith('#')) return;
            e.preventDefault();
            e.stopPropagation();
        }, true);

        document.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopPropagation();
        }, true);
    </script>
</body>
</html>
