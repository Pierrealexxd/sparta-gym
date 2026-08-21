@props(['nombre'])

{{-- Iconos en línea: son pocos y así no cargamos una fuente entera ni
     hacemos peticiones extra. Todos comparten caja de 24 y trazo de 1.6. --}}
@php
    $trazos = [
        'check'     => '<path d="M4 12.5l5 5L20 6.5"/>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/>',
        'facebook'  => '<path d="M14 8.5V6.8c0-.8.2-1.3 1.4-1.3H17V2.6c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4v2H8v3h2.8v8h3.2v-8h2.7l.4-3z"/>',
        'tiktok'    => '<path d="M16 3c.4 2.3 1.9 3.7 4 4v3c-1.5 0-2.9-.5-4-1.3V15a6 6 0 1 1-6-6v3.2A2.8 2.8 0 1 0 13 15V3z"/>',
        'youtube'   => '<rect x="2" y="5" width="20" height="14" rx="4"/><path d="M10 9.5l5 2.5-5 2.5z"/>',
        'estrella'  => '<path d="M12 3l2.6 5.6 6 .8-4.4 4.2 1.1 6L12 16.8 6.7 19.6l1.1-6L3.4 9.4l6-.8z" fill="currentColor" stroke="none"/>',
        'telefono'  => '<path d="M5 3h4l2 5-2.5 1.5a12 12 0 0 0 5 5L15 12l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
        'whatsapp'  => '<path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2z"/><path d="M17.5 14.4c-.3-.2-1.7-.9-2-1-.3-.1-.5-.2-.7.1-.2.3-.8 1-1 1.2-.2.2-.4.2-.7.1-.3-.2-1.3-.5-2.4-1.5-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.6l.5-.6c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.6-.1-.2-.7-1.8-1-2.4-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.2.3-.9.9-.9 2.2s.9 2.6 1.1 2.7c.1.2 1.8 2.8 4.5 3.9.6.3 1.1.4 1.5.6.6.2 1.2.2 1.6.1.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.1-1.2l-.3-.2z"/>',
        'correo'    => '<rect x="2.5" y="4.5" width="19" height="15" rx="3"/><path d="M3 7l9 6 9-6"/>',
        'ubicacion' => '<path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
        'reloj'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.4 2"/>',
        'pesa'      => '<path d="M4 9v6M7 6v12M17 6v12M20 9v6M7 12h10"/>',
        'objetivo'  => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/>',
        'grafico'   => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'escudo'    => '<path d="M12 3l8 3v6c0 4.5-3.3 8.3-8 9.5C7.3 20.3 4 16.5 4 12V6z"/>',
        'usuarios'  => '<circle cx="9" cy="8" r="3.4"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 5.2a3.4 3.4 0 0 1 0 5.6M17.5 20a6.5 6.5 0 0 0-2-4.7"/>',
        'qr'        => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v3M14 20h7"/>',

        // Panel
        'panel'      =>'<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="5" rx="1.5"/><rect x="13" y="10" width="8" height="11" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/>',
        'billetera'  => '<rect x="3" y="6" width="18" height="14" rx="2"/><path d="M3 10h18"/><circle cx="16" cy="15" r="1" fill="currentColor" stroke="none"/>',
        'tarjetas'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 9h18"/><path d="M7 14h4"/>',
        'entrada'    => '<path d="M9 21V3l7 4-7 4"/><path d="M9 13v8"/>',
        'entrenador' => '<circle cx="12" cy="7" r="3.4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/><path d="M9 12l1.5 1.5L15 9"/>',
        'lista'      => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1" fill="currentColor" stroke="none"/><circle cx="3.5" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="3.5" cy="18" r="1" fill="currentColor" stroke="none"/>',
        'balanza'    => '<path d="M12 3v18M6 21h12"/><path d="M4 7h6M14 7h6"/><path d="M4 7l-2.5 5a2.5 2.5 0 0 0 5 0zM20 7l-2.5 5a2.5 2.5 0 0 0 5 0z"/>',
        'engranaje'  => '<circle cx="12" cy="12" r="3.2"/><path d="M12 3v2.4M12 18.6V21M21 12h-2.4M5.4 12H3M18 6l-1.7 1.7M7.7 16.3L6 18M18 18l-1.7-1.7M7.7 7.7L6 6"/>',
        'agregar'    => '<path d="M12 5v14M5 12h14"/>',
        'lapiz'      => '<path d="M4 20h4l11-11-4-4L4 16z"/><path d="M14.5 6.5l3 3"/>',
        'papelera'   => '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/>',
        'ojo'        => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
        'ojo-tachado'=> '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/><path d="M4 4l16 16"/>',
        'lupa'       => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'descargar'  => '<path d="M12 3v12m0 0l-4-4m4 4l4-4"/><path d="M4 19h16"/>',
        'subir'      => '<path d="M12 19V5m0 0l-5 5m5-5l5 5"/><path d="M4 19h16"/>',
        'menu'       => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'cerrar'     => '<path d="M6 6l12 12M18 6L6 18"/>',
        'flecha-der' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'campana'    => '<path d="M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'llama'      => '<path d="M12 3c1 3 4 4 4 8a4 4 0 1 1-8 0c0-1 .3-2 1-3 .3 1 1 1.5 1.5 1 .5-1-.3-2 .5-3.5 .5-1 1-2 1-2.5z"/>',
        'reiniciar'  => '<path d="M4 4v5h5"/><path d="M20 20v-5h-5"/><path d="M5 9a8 8 0 0 1 14-3M19 15a8 8 0 0 1-14 3"/>',
        'sol'        => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2.4M12 19.6V22M2 12h2.4M19.6 12H22M4.9 4.9l1.7 1.7M17.4 17.4l1.7 1.7M4.9 19.1l1.7-1.7M17.4 6.6l1.7-1.7"/>',
        'luna'       => '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4 8.5 8.5 0 1 0 20 14.5z"/>',
        'caja'       => '<path d="M3 8l9-4 9 4-9 4-9-4z"/><path d="M3 8v9l9 4 9-4V8"/><path d="M12 12v9"/>',
        'chat'       => '<path d="M21 12a8.5 8.5 0 0 1-12.3 7.6L4 21l1.4-4.7A8.5 8.5 0 1 1 21 12z"/><path d="M8.5 12h7M8.5 8.5h4.5"/>',
        'perfil'     => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>',
        'avion'      => '<path d="M3 10.5L21 3l-7.5 18-3-6.5z"/><path d="M10.5 14.5L21 3"/>',
        'clip'       => '<path d="M21 12.5l-8.2 8.2a5.8 5.8 0 0 1-8.2-8.2l8.6-8.6a3.9 3.9 0 0 1 5.5 5.5L10.5 17.6a1.95 1.95 0 0 1-2.75-2.75L15.5 7"/>',

        // Método de la mano (nutrición, Fase 0 — ver PLAN_NUTRICION_PROGRESO.md)
        'mano-palma' => '<path d="M6 21v-6c0-4 2-8 6-8s6 4 6 8v6"/><path d="M9 8V5M12 7V4M15 8V5"/>',
        'mano-puno'  => '<rect x="6" y="8" width="12" height="10" rx="4"/><path d="M9 8V6M12 8V5M15 8V6"/><path d="M12 18v3"/>',
        'mano-cuenco'=> '<path d="M5 11c0 5 3.5 9 7 9s7-4 7-9"/><path d="M5 11h14"/>',
        'mano-pulgar'=> '<path d="M8 21h8a2 2 0 0 0 2-1.8l1-6.2a2 2 0 0 0-2-2.2h-4.3l.8-3.3a1.8 1.8 0 0 0-3.3-1.4L8 10.5V21z"/><path d="M8 10.5H6a1.5 1.5 0 0 0-1.5 1.5v7.5A1.5 1.5 0 0 0 6 21h2"/>',

        // Programas + recomendaciones (Fase 0 — PLAN-RUTINAS-PERSONALIZADAS.md)
        'rayo'      => '<path d="M13 2 4 14h6l-1 8 9-12h-6z"/>',
        'plato'     => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.4"/>',
        'proteina'  => '<path d="M12 3c2.5 2 4 4.4 4 7.2A4 4 0 0 1 8 10.2C8 7.4 9.5 5 12 3z"/><path d="M9 21h6M12 15v6"/>',
        'polvo'     => '<path d="M9 3h6l1 4H8z"/><path d="M8 7h8l1.2 11.2A2 2 0 0 1 15.2 20H8.8a2 2 0 0 1-2-1.8z"/><path d="M9.5 12h5"/>',
        'gota'      => '<path d="M12 3s6 6.8 6 11a6 6 0 1 1-12 0c0-4.2 6-11 6-11z"/>',
        'lampara'   => '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.5 10.9c.6.4.9 1 .9 1.6V16h5.2v-.5c0-.6.3-1.2.9-1.6A6 6 0 0 0 12 3z"/>',
        'libro'     => '<path d="M4 5.5C4 4.7 4.7 4 5.5 4H12v16H5.5A1.5 1.5 0 0 1 4 18.5z"/><path d="M20 5.5c0-.8-.7-1.5-1.5-1.5H12v16h6.5a1.5 1.5 0 0 0 1.5-1.5z"/>',
    ];
@endphp

<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
     {{ $attributes->merge(['style' => 'width:1.25em;height:1.25em']) }}>
    {!! $trazos[$nombre] ?? $trazos['check'] !!}
</svg>
