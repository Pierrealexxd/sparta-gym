<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>QR de asistencia · {{ $sede->name }} · Sparta Gym</title>
    <style>
        {{-- Misma filosofía que admin/clientes/carnet: blanco sobre negro,
             autónoma, lista para "Guardar como PDF" del navegador. --}}
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 2.5rem;
            font-family: -apple-system, system-ui, 'Segoe UI', sans-serif;
            color: #111; background: #fff;
            display: flex; justify-content: center;
        }
        .qr-sede {
            width: 340px;
            border: 2px solid #111;
            border-radius: 16px;
            padding: 1.75rem;
            text-align: center;
        }
        .qr-sede__marca { font-size: .7rem; letter-spacing: .2em; text-transform: uppercase; color: #555; margin-bottom: .25rem; }
        .qr-sede__nombre { font-size: 1.15rem; font-weight: 700; margin: 0 0 1.25rem; }
        canvas { max-width: 100%; height: auto; }
        .qr-sede__token { font-family: monospace; font-size: .65rem; color: #777; word-break: break-all; margin-top: .75rem; }
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

        <div class="qr-sede">
            <div class="qr-sede__marca">Sparta Gym</div>
            <p class="qr-sede__nombre">{{ $sede->name }}</p>
            <p style="font-size:.8rem;color:#555;margin-top:-.75rem">Asistencia del personal — escaneá con tu panel</p>

            <canvas data-qr="{{ $qr->token }}" data-qr-tamano="240"></canvas>

            <p class="qr-sede__token">{{ $qr->token }}</p>
        </div>
    </div>
</body>
</html>
