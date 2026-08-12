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
    @vite(['resources/css/app.css', 'resources/js/app-panel.js'])

    {{-- Disponible en TODas las páginas del panel (no solo /mensajes): la
         campanita vive en la cabecera compartida, así que necesita sus
         rutas aquí y no en el @push('scripts') de una vista concreta. --}}
    <script>
        window.spartaNotificaciones = {
            lista: @json(route('mensajes.lista')),
            noLeidas: @json(route('mensajes.no-leidas')),
            abrirConversacion: @json(route('mensajes')) + '?con=',
            {{-- Solo quien puede aprobar (admin) recibe esta ruta — si no
                 viene, el bell simplemente no pregunta por solicitudes. --}}
            @if (auth()->user()->tienePermiso('asistencia.aprobar'))
                solicitudesAsistencia: @json(route('admin.asistencia.solicitudes.pendientes-json')),
                abrirSolicitudes: @json(route('admin.asistencia.solicitudes.index')),
            @endif
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
                    <span>Sparta</span><em>Gym</em>
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
                <form method="POST" action="{{ route('logout') }}">
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
                    <div class="notificaciones" x-data="notificaciones()" @keydown.escape.window="abierto = false">
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
                                        <button type="button" class="modal__cerrar" @click="abierto = false" aria-label="Cerrar">
                                            <x-icono nombre="cerrar" />
                                        </button>
                                    </header>

                                    <div class="notificaciones__lista">
                                        <template x-for="n in items" :key="n.tipo + '-' + n.id">
                                            <button type="button" class="notificaciones__item" @click="irA(n)">
                                                <span class="chat__avatar">
                                                    <img x-show="n.avatar" :src="n.avatar" alt="">
                                                    <span x-show="!n.avatar" x-text="n.iniciales"></span>
                                                </span>
                                                <span class="notificaciones__item-info">
                                                    <b x-text="n.nombre"></b>
                                                    <small x-text="n.ultimo || 'Nuevo mensaje'"></small>
                                                    {{-- Solo llega poblado para el admin, que ve notificaciones de
                                                         todas sus sedes en un mismo cajón (ver MensajeController). --}}
                                                    <span class="notificaciones__item-sede" x-show="n.sede" x-text="n.sede"></span>
                                                </span>
                                                <em class="chat__no-leidas" x-text="n.no_leidas"></em>
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

    {{-- Confirmaciones de sesión: flotan sobre el contenido y se retiran
         solas, en vez de empujar el layout como el .aviso fijo de antes. --}}
    @if (session('exito') || session('error'))
        <div class="toasts">
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
        </div>
    @endif

    @stack('scripts')
</body>
</html>
