<!DOCTYPE html>
<html lang="es"
      x-data="{ menuAbierto: false, comprimido: localStorage.getItem('sidebarComprimido') === '1' }"
      :class="{ 'sin-scroll': menuAbierto }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" id="tema-color" content="#0A0A0B">
    <title>@yield('titulo', 'Panel') · Sparta Gym</title>
    @if ($logo = \App\Support\GymContext::current()?->logo_path)
        <link rel="icon" href="{{ asset('storage/' . $logo) }}">
    @endif
    @vite(['resources/css/panel-entry.css', 'resources/js/app-panel.js'])

    {{-- Campanita unificada (ver plan-notificaciones-toast.md): un solo
         origen de notificaciones para los tres paneles, en vez de sumar
         mensajes + stock + solicitudes en el cliente. Disponible en todas
         las páginas del panel porque la campanita vive en la cabecera. --}}
    @php
        // $toastDuracion aparte, no @json(config(..., ['baja'=>4,'media'=>5,'alta'=>8]))
        // inline: el parser de argumentos de @json de Blade se rompe con ESTA
        // combinación exacta de anidamiento (@json→config→array) en cuanto el
        // array literal trae 3 pares clave-valor — confirmado compilando el
        // directive suelto: con 2 claves compila bien, con 3 corta el PHP a
        // mitad de camino ("Unclosed '[' does not match ')'" en cada carga
        // del panel). Con la config ya resuelta en una variable simple,
        // @json() solo ve un argumento de un nivel y no dispara el bug.
        $toastDuracion = config('sparta.notificaciones.toast_duracion', ['baja' => 4, 'media' => 5, 'alta' => 8]);
    @endphp
    <script>
        window.spartaNotificaciones = {
            total: @json(route('notificaciones.total')),
            lista: @json(route('notificaciones.index')),
            nuevas: @json(route('notificaciones.nuevas')),
            leida: @json(route('notificaciones.leida', ['id' => '__ID__'])),
            leidas: @json(route('notificaciones.leidas')),
            pollingMs: {{ (int) config('sparta.notificaciones.polling_segundos', 15) * 1000 }},
            toastDuracion: @json($toastDuracion),
            toastMax: {{ (int) config('sparta.notificaciones.toast_max_visibles', 4) }},
        };
    </script>

    {{-- Tema claro/oscuro resuelto antes del primer pintado: sin esto, quien
         tiene claro guardado vería un fogonazo oscuro. Sólo el panel lleva
         data-tema; la landing nunca lo toca y sigue siendo oscura. --}}
    <script>
        (function () {
            try {
                var tema = localStorage.getItem('temaPanel');
                if (tema !== 'claro' && tema !== 'oscuro') {
                    tema = window.matchMedia('(prefers-color-scheme: light)').matches ? 'claro' : 'oscuro';
                }
                document.documentElement.setAttribute('data-tema', tema);
                var meta = document.getElementById('tema-color');
                if (meta) meta.setAttribute('content', tema === 'claro' ? '#F4F2EE' : '#0A0A0B');
            } catch (e) {}
        })();
    </script>
</head>
<body>
    <div class="panel" :class="{ 'is-comprimido': comprimido }">
        <aside class="panel__lateral" :class="{ 'is-abierta': menuAbierto, 'is-comprimida': comprimido }">
            <div class="panel__cabecera-lateral">
                <a class="panel__marca" href="{{ route('landing') }}">
                    @if ($logo)
                        <img src="{{ asset('storage/' . $logo) }}" alt="" style="width:1.6em;height:1.6em;border-radius:var(--r-2);object-fit:cover;margin-right:var(--e-2)">
                    @endif
                    <span>Sparta</span><em>Gym L.</em>
                </a>
                <button type="button" class="panel__comprimir"
                        @click="comprimido = !comprimido; localStorage.setItem('sidebarComprimido', comprimido ? '1' : '0')"
                        :aria-expanded="(!comprimido).toString()"
                        aria-label="Comprimir menú" title="Comprimir menú">
                    <x-icono nombre="flecha-der" />
                </button>
            </div>

            @include('layouts.partials.panel-nav')

            <div class="panel__usuario">
                <span class="panel__avatar">
                    @if (auth()->user()->avatar_path)
                        <img src="{{ asset('storage/' . auth()->user()->avatar_path) }}" alt="Foto de perfil">
                    @else
                        {{ auth()->user()->iniciales }}
                    @endif
                </span>
                <span class="panel__usuario-datos">
                    <b>{{ auth()->user()->name }}</b>
                    <small>{{ auth()->user()->role?->name }}</small>
                </span>
                <form method="POST" action="{{ route('salir') }}">
                    @csrf
                    <button class="panel__salir" type="submit" title="Cerrar sesión" aria-label="Cerrar sesión">
                        <x-icono nombre="entrada" style="transform: rotate(180deg)" />
                    </button>
                </form>
            </div>
        </aside>

        <div class="panel__cuerpo">
            <header class="panel__cabecera">
                <div class="panel__migas">
                    <button class="panel__menu-movil" type="button" @click="menuAbierto = !menuAbierto"
                            aria-label="Abrir menú">
                        <x-icono nombre="menu" />
                    </button>
                    <span class="panel__migas-base">Sparta</span>
                    <span class="panel__migas-separador">/</span>
                    <span class="panel__migas-actual">@yield('titulo', 'Panel')</span>
                </div>

                <div class="panel__cabecera-acciones">
                    <div class="notificaciones" x-data="campanita()" @keydown.escape.window="abierto = false">
                        <button type="button" class="panel__campana" @click="alternar()"
                                aria-label="Notificaciones" title="Notificaciones">
                            <x-icono nombre="campana" />
                            <span class="notificaciones__contador" x-show="total > 0" x-cloak x-text="total"></span>
                        </button>

                        {{-- x-teleport: la cabecera tiene backdrop-filter, que crea su
                             propio "containing block" para position:fixed — sin esto
                             el cajón lateral queda atrapado dentro de la cajita de la
                             cabecera en vez de cubrir la pantalla por la derecha. Se
                             saca al final del <body> mientras el x-data (abierto/items/
                             etc.) sigue siendo el mismo, así que el resto no cambia. --}}
                        <template x-teleport="body">
                            <div>
                                <div class="notificaciones__fondo" x-show="abierto" x-cloak x-transition.opacity.duration.150ms
                                     @click="abierto = false"></div>

                                <aside class="notificaciones__panel" x-show="abierto" x-cloak
                                       x-transition:enter="v-notif-entra" x-transition:enter-start="v-notif-entra-desde" x-transition:enter-end="v-notif-entra-hasta"
                                       x-transition:leave="v-notif-sale" x-transition:leave-start="v-notif-entra-hasta" x-transition:leave-end="v-notif-entra-desde">
                                    <header class="notificaciones__cabecera">
                                        <b>Notificaciones</b>
                                        <div class="notificaciones__cabecera-acciones">
                                            <button type="button" class="notificaciones__marcar-todas" @click="marcarTodas()"
                                                    x-show="total > 0" x-cloak>Marcar todas como leídas</button>
                                            <button type="button" class="modal__cerrar" @click="abierto = false" aria-label="Cerrar">
                                                <x-icono nombre="cerrar" />
                                            </button>
                                        </div>
                                    </header>

                                    <div class="notificaciones__lista">
                                        <template x-for="n in items" :key="n.id">
                                            <button type="button" class="notificaciones__item" :class="{ 'is-no-leida': !n.leida }" @click="irA(n)">
                                                {{-- El backend serializa la columna como "icon" (ver
                                                     NotificationService::serializar), no "icono" — con la clave en
                                                     español ningún x-show de abajo matcheaba nunca, así que el chip
                                                     salía siempre vacío ("cuadrado que no carga"), sin importar el
                                                     tipo de notificación. data-icono (el atributo, no la clave JS)
                                                     colorea el chip según el módulo que la originó. --}}
                                                <span class="notificaciones__item-icono" :data-icono="n.icon">
                                                    @foreach (['caja','chat','entrada','reloj','billetera','estrella','correo','usuarios','escudo','check','campana','objetivo','cerrar'] as $icono)
                                                        <x-icono nombre="{{ $icono }}" x-show="n.icon === '{{ $icono }}'" />
                                                    @endforeach
                                                </span>
                                                <span class="notificaciones__item-info">
                                                    <b x-text="n.title" :title="n.title"></b>
                                                    {{-- La hora vivía en una columna aparte a la derecha del todo:
                                                         en el cajón angosto de móvil eso le quitaba ancho al título
                                                         y lo truncaba casi de inmediato ("Nuevo m…"). Ahora comparte
                                                         línea con el cuerpo, debajo del título, que así aprovecha
                                                         todo el ancho disponible. --}}
                                                    <span class="notificaciones__item-meta">
                                                        <small x-text="n.body" :title="n.body"></small>
                                                        <span class="notificaciones__item-hora" x-text="n.hora"></span>
                                                    </span>
                                                    {{-- Solo llega poblado para el admin, que ve notificaciones de
                                                         todas sus sedes en un mismo cajón. --}}
                                                    <span class="notificaciones__item-sede" x-show="n.sede" x-text="n.sede"></span>
                                                </span>
                                            </button>
                                        </template>
                                        <div class="notificaciones__vacio" x-show="!cargando && items.length === 0">
                                            <span class="notificaciones__vacio-icono"><x-icono nombre="campana" /></span>
                                            Sin notificaciones nuevas.
                                        </div>
                                        <p class="notificaciones__vacio" x-show="cargando" x-cloak>Cargando…</p>
                                    </div>
                                </aside>
                            </div>
                        </template>
                    </div>

                    <button type="button" class="panel__tema" data-tema-boton
                            aria-label="Cambiar tema claro u oscuro" title="Cambiar tema">
                        <x-icono nombre="sol" class="icono-sol" />
                        <x-icono nombre="luna" class="icono-luna" />
                    </button>
                </div>
            </header>

            <main class="panel__contenido">
                {{-- Bienvenida de login/registro: a diferencia del toast de
                     abajo (usado para confirmaciones de acciones dentro del
                     panel), esta va arriba del todo, en el mismo lugar/estilo
                     que el aviso de error del formulario de login — es lo
                     primero que ves al entrar, no algo que hay que notar de
                     reojo en una esquina. --}}
                @if (session('bienvenida'))
                    <div class="aviso aviso--exito" role="status" data-revelar>{{ session('bienvenida') }}</div>
                @endif

                {{-- Recordatorio permanente (no un toast que se cierra solo):
                     con "Todas las sedes" activa, GymContext::id() es null a
                     propósito para que los reportes agreguen todo — pero
                     crear clientes/entrenadores/planes/productos exige una
                     sede real (ver Controller::exigirSedeEspecifica). Mejor
                     avisar antes de que el admin llene un formulario entero
                     y recién ahí se entere. --}}
                @if (auth()->user()->tienePermiso('sedes.ver-todas') && \App\Support\GymContext::id() === null)
                    @if (session('sede_todas_aviso'))
                        <div class="toast toast--baja" x-data x-init="$nextTick(() => { setTimeout(() => $el.remove(), 5000) })" role="status">
                            <b>Viendo "Todas las sedes".</b>
                            Para crear registros, cambia a una sede específica.
                        </div>
                    @endif
                @endif

                <section class="membrete" data-revelar>
                    <div class="membrete__texto">
                        <span class="membrete__sede">{{ \App\Support\GymContext::current()?->name ?? 'Todas las sedes' }}</span>
                        <h1 class="membrete__titulo">@yield('titulo', 'Panel')</h1>
                        @hasSection('subtitulo')<p class="membrete__subtitulo">@yield('subtitulo')</p>@endif
                    </div>

                    @hasSection('acciones')
                        <div class="membrete__acciones">@yield('acciones')</div>
                    @endif
                </section>

                @yield('contenido')
            </main>
        </div>
    </div>

    {{-- Fondo oscuro tras el menú lateral en móvil --}}
    <div x-show="menuAbierto" x-cloak
         style="position:fixed;inset:0;z-index:55;background:rgba(0,0,0,.5)"
         @click="menuAbierto = false"></div>

    {{-- Toasts: el contenedor vive SIEMPRE (los realtime los agrega
         $store.toasts desde notificaciones.js) y el flash de confirmación
         de sesión se pinta acá, en el mismo contenedor, para compartir
         posición y z-index. No empujan el layout: flotan sobre el contenido
         y se retiran solos. --}}
    <div class="toasts" aria-live="polite">
        @if (session('exito') || session('error'))
            <div class="toast toast--{{ session('exito') ? 'exito' : 'error' }}"
                 role="{{ session('exito') ? 'status' : 'alert' }}"
                 x-data="{ visible: true }" x-show="visible" x-cloak
                 x-init="setTimeout(() => visible = false, 5000)"
                 x-transition.duration.300ms>
                <x-icono nombre="{{ session('exito') ? 'check' : 'campana' }}" />
                <span>{{ session('exito') ?? session('error') }}</span>
                <button class="toast__cerrar" type="button" @click="visible = false" aria-label="Cerrar">
                    <x-icono nombre="cerrar" />
                </button>
            </div>
        @endif

        {{-- Toasts en tiempo real: los crea $store.toasts (notificaciones.js).
             Cada uno con su prioridad (alta/media/baja) que decide borde,
             icono y duración; el cursor pausa la cuenta regresiva. --}}
        <template x-for="t in $store.toasts.items" :key="t.id">
            <div class="toast" :class="'toast--' + t.prioridad" role="status"
                 @mouseenter="$store.toasts.pausar(t)" @mouseleave="$store.toasts.reanudar(t)"
                 x-transition.duration.300ms>
                <span class="toast__icono">
                    @foreach (['caja','chat','entrada','reloj','billetera','estrella','correo','usuarios','escudo','check','campana','objetivo','cerrar'] as $icono)
                        <x-icono nombre="{{ $icono }}" x-show="t.icono === '{{ $icono }}'" />
                    @endforeach
                </span>
                <span class="toast__texto">
                    <b x-text="t.titulo" :title="t.titulo"></b>
                    <small x-text="t.cuerpo" :title="t.cuerpo"></small>
                </span>
                <button class="toast__cerrar" type="button" @click="$store.toasts.cerrar(t.id)" aria-label="Cerrar">
                    <x-icono nombre="cerrar" />
                </button>
            </div>
        </template>
    </div>

    <x-modal-preview />

    @stack('scripts')
</body>
</html>
