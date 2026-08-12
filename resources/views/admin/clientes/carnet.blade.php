<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Carnet · {{ $cliente->full_name }} · Sparta Gym</title>
    <style>
        /* Misma filosofía que admin/reportes/imprimir-*: blanco sobre negro,
           sin dependencias, listo para "Guardar como PDF" del navegador. */
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 2.5rem;
            font-family: -apple-system, system-ui, 'Segoe UI', sans-serif;
            color: #111; background: #fff;
            display: flex; justify-content: center;
        }
        .carnet {
            width: 340px;
            border: 2px solid #111;
            border-radius: 16px;
            padding: 1.75rem;
            text-align: center;
        }
        .carnet__marca { font-size: .7rem; letter-spacing: .2em; text-transform: uppercase; color: #555; margin-bottom: 1rem; }
        .carnet__foto {
            width: 84px; height: 84px; border-radius: 50%; margin: 0 auto .75rem;
            object-fit: cover; border: 1px solid #ddd;
        }
        .carnet__nombre { font-size: 1.15rem; font-weight: 700; margin: 0; }
        .carnet__codigo { font-family: monospace; color: #555; font-size: .8rem; margin: .25rem 0 1.25rem; }
        canvas { max-width: 100%; height: auto; }
        .carnet__token { font-family: monospace; font-size: .65rem; color: #777; word-break: break-all; margin-top: .75rem; }
        .no-imprimir { margin-bottom: 1.5rem; text-align: center; }
        .no-imprimir button {
            font: inherit; padding: .6rem 1.2rem; border-radius: 8px; border: 1px solid #111;
            background: #111; color: #fff; cursor: pointer;
        }
        @media print { .no-imprimir { display: none; } body { padding: 1rem; } }
    </style>
    @vite(['resources/js/carnet.js'])
</head>
<body>
    <div>
        <div class="no-imprimir"><button type="button" onclick="window.print()">Imprimir / Guardar como PDF</button></div>

        <div class="carnet">
            <div class="carnet__marca">Sparta Gym</div>

            @if ($cliente->photo_path)
                <img class="carnet__foto" src="{{ asset('storage/' . $cliente->photo_path) }}" alt="{{ $cliente->full_name }}">
            @endif

            <p class="carnet__nombre">{{ $cliente->full_name }}</p>
            <p class="carnet__codigo">{{ $cliente->code }}</p>

            <canvas data-qr="{{ $cliente->qr_token }}" data-qr-tamano="240"></canvas>

            <p class="carnet__token">{{ $cliente->qr_token }}</p>
        </div>
    </div>
</body>
</html>
