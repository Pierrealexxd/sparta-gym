@extends('layouts.panel')

@section('titulo', 'Mensajes')
@section('contenido_clase', 'panel__contenido--lleno')

{{-- El botón vive en el membrete del módulo (junto al título) y habla con
     el componente Alpine por evento de ventana — mismo patrón que el modal
     "Nuevo cliente" de Clientes, porque el membrete está fuera del scope
     x-data del chat. --}}
@section('acciones')
    <button type="button" class="btn btn--fuego"
            @click="window.dispatchEvent(new CustomEvent('abrir-nuevo-chat'))">
        <x-icono nombre="agregar" /> Nuevo chat
    </button>
@endsection

@push('scripts')
    @php
        // El array se arma en PHP porque @json con paréntesis anidados (los
        // de route()) y varias líneas se rompe en la compilación de Blade.
        $spartaMensajes = [
            'lista'      => route('mensajes.lista'),
            'noLeidas'   => route('mensajes.no-leidas'),
            'directorio' => route('mensajes.directorio'),
            'conversar'  => route('mensajes.conversar'),
            'enviar'     => route('mensajes.enviar', ['conversacion' => '__ID__']),
            'listaMensajes' => route('mensajes.listaMensajes', ['conversacion' => '__ID__']),
        ];
    @endphp
    <script>
        window.spartaMensajes = @json($spartaMensajes);
        @if ($abrir > 0)
            window.spartaAbrirConversacion = {{ $abrir }};
        @endif
    </script>
@endpush

@section('contenido')
    <div class="chat" x-data="chat({{ Js::from($conversaciones) }})"
         :class="{ 'is-hilo-abierto': conversacion }"
         @abrir-nuevo-chat.window="abrirNuevo()"
         @keydown.escape.window="nuevoAbierto = false">

        {{-- Columna izquierda: lista de hilos. Sin cabecera propia: el
             título vive en el membrete y el único botón "+" es el suyo. --}}
        <aside class="chat__panel" x-cloak>
            {{-- Búsqueda rápida sobre los hilos ya abiertos: filtra en vivo,
                 sin ir al servidor — la lista completa ya está en memoria. --}}
            <div class="chat__buscar">
                <x-icono nombre="lupa" />
                <input class="campo__control" type="search" x-model="filtroLista"
                       placeholder="Buscar conversación…" autocomplete="off">
            </div>

            <div class="chat__cuerpo-lista">
                <div class="chat__lista" x-ref="lista">
                    <template x-for="c in conversacionesFiltradas" :key="c.id">
                        <button type="button" class="chat__conversacion"
                                :class="{ 'is-activa': c.id === conversacion }"
                                @click="abrir(c.id)">
                            <span class="chat__avatar">
                                <img x-show="c.avatar" :src="c.avatar" alt="">
                                <span x-show="!c.avatar" x-text="c.iniciales"></span>
                            </span>
                            <span class="chat__conversacion-info">
                                <b x-text="c.nombre"></b>
                                <small x-text="c.ultimo || 'Di hola'"></small>
                            </span>
                            <span class="chat__conversacion-meta">
                                <small x-text="c.hora"></small>
                                <em class="chat__no-leidas" x-show="c.no_leidas > 0" x-text="c.no_leidas"></em>
                            </span>
                        </button>
                    </template>
                    <p class="chat__vacio-lista" x-show="conversacionesFiltradas.length === 0 && filtroLista.trim()">
                        Ninguna conversación coincide con la búsqueda.
                    </p>
                    <p class="chat__vacio-lista" x-show="conversaciones.length === 0">
                        Sin conversaciones todavía. Toca Nuevo chat para escribir a alguien.
                    </p>
                </div>
            </div>
        </aside>

        {{-- Columna derecha: hilo --}}
        <section class="chat__hilo" x-cloak>
            {{-- Cabecera del hilo: solo retorno + avatar + nombre --}}
            <header class="chat__cabecera" x-show="conversacion" x-cloak>
                <button type="button" class="chat__volver" @click="conversacion = null" title="Volver a la lista">
                    <x-icono nombre="flecha-der" />
                </button>
                <span class="chat__avatar chat__avatar--cabecera">
                    <img x-show="contacto?.avatar" :src="contacto?.avatar" alt="">
                    <span x-show="!contacto?.avatar" x-text="contacto?.iniciales"></span>
                </span>
                <b class="chat__cabecera-nombre" x-text="contacto?.nombre"></b>
            </header>

            {{-- Cuerpo de mensajes --}}
            <div class="chat__mensajes" x-ref="mensajes" x-show="conversacion && !cargandoHilo" x-cloak>
                <template x-for="m in mensajes" :key="m.id">
                    <div class="chat__mensaje" :class="{ 'chat__mensaje--mio': m.mio }">
                        <div class="chat__burbuja" :class="m.mio ? 'chat__burbuja--mio' : 'chat__burbuja--otro'">
                            <template x-if="m.adjunto?.tipo === 'imagen'">
                                <a class="chat__adjunto-imagen" :href="m.adjunto.url" target="_blank" rel="noopener">
                                    <img :src="m.adjunto.url" :alt="m.adjunto.nombre" loading="lazy">
                                </a>
                            </template>
                            <template x-if="m.adjunto && m.adjunto.tipo !== 'imagen'">
                                <a class="chat__adjunto-archivo" :href="m.adjunto.url" target="_blank" rel="noopener" download>
                                    <x-icono nombre="clip" />
                                    <span x-text="m.adjunto.nombre"></span>
                                </a>
                            </template>
                            <span class="chat__texto" x-show="m.cuerpo" x-text="m.cuerpo"></span>
                            <small class="chat__hora">
                                <span x-text="m.hora"></span>
                                <span class="chat__leido" x-show="m.mio" x-text="m.leido ? '✓✓' : '✓'"></span>
                            </small>
                        </div>
                    </div>
                </template>
            </div>

            <div class="chat__cargando" x-show="cargandoHilo" x-cloak>Cargando…</div>

            {{-- Sin tarjeta de estado vacío: hasta que se elige conversación,
                 el hilo ni siquiera existe en pantalla (en escritorio el panel
                 ocupa todo el ancho; en móvil no hay nada detrás). --}}

            {{-- Componer: adjunto + texto + envío. Sin notas de voz a
                 propósito — el backend solo acepta imágenes y archivos. --}}
            <form class="chat__escribir" x-show="conversacion" x-cloak @submit.prevent="enviarMensaje()">
                <div class="chat__chip" x-show="adjunto" x-cloak>
                    <small class="chat__chip-nombre" x-text="adjunto?.nombre"></small>
                    <button type="button" class="chat__chip-quitar" @click="quitarAdjunto()" aria-label="Quitar adjunto">
                        <x-icono nombre="cerrar" />
                    </button>
                </div>
                <p class="chat__aviso" x-show="aviso" x-text="aviso"></p>

                <div class="chat__escribir-fila">
                    <input type="file" x-ref="archivo" class="chat__archivo-input" tabindex="-1" aria-hidden="true"
                           accept=".jpg,.jpeg,.png,.gif,.webp,.avif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip"
                           @change="elegirAdjunto($event)">
                    <button type="button" class="chat__accion" :class="{ 'is-activo': adjunto }"
                            @click="$refs.archivo.click()" title="Adjuntar archivo o imagen">
                        <x-icono nombre="clip" />
                    </button>
                    <input class="campo__control chat__texto-input" type="text" x-model="texto" maxlength="2000"
                           placeholder="Escribe un mensaje…" autocomplete="off">
                    <button class="btn btn--fuego chat__enviar" type="submit"
                            :disabled="(!texto.trim() && !adjunto) || enviando" title="Enviar">
                        <x-icono nombre="avion" />
                    </button>
                </div>
            </form>
        </section>

        {{-- Modal "Nuevo mensaje": mismo directorio de antes, ahora fuera del
             carril de la lista — así no compite por espacio con los chats.
             Teleport a <body>, mismo patrón que el cajón de notificaciones
             (layouts/panel.blade.php). --}}
        <template x-teleport="body">
            <div class="modal__fondo" x-show="nuevoAbierto" x-cloak>
                <div class="tarjeta modal__caja modal__caja--ancho chat-directorio"
                     role="dialog" aria-modal="true" aria-label="Nuevo mensaje"
                     @click.outside="nuevoAbierto = false">
                    <div class="modal__cabecera">
                        <h3>Nuevo mensaje</h3>
                        <button class="modal__cerrar" type="button" @click="nuevoAbierto = false" aria-label="Cerrar">
                            <x-icono nombre="cerrar" />
                        </button>
                    </div>

                    <div class="chat__filtros">
                        <div class="chat__filtros-roles">
                            <button type="button" :class="{ 'is-activo': filtroRol === '' }" @click="filtroRol = ''; cargarDirectorio()">Todos</button>
                            @unless (auth()->user()->esCliente())
                                {{-- Un cliente no puede escribirle a otro cliente
                                     (ver MensajeController::directorio/conversar);
                                     este chip le devolvería siempre vacío. --}}
                                <button type="button" :class="{ 'is-activo': filtroRol === 'cliente' }" @click="filtroRol = 'cliente'; cargarDirectorio()">Clientes</button>
                            @endunless
                            <button type="button" :class="{ 'is-activo': filtroRol === 'entrenador' }" @click="filtroRol = 'entrenador'; cargarDirectorio()">Entrenadores</button>
                            <button type="button" :class="{ 'is-activo': filtroRol === 'admin' }" @click="filtroRol = 'admin'; cargarDirectorio()">Admin</button>
                        </div>
                        <div class="panel__busqueda chat__busqueda">
                            <x-icono nombre="lupa" />
                            <input class="campo__control" type="search" placeholder="Buscar por nombre…"
                                   x-ref="busqueda"
                                   @input.debounce.250ms="busqueda = $el.value; cargarDirectorio()">
                        </div>
                    </div>

                    <div class="chat__lista">
                        <template x-for="u in directorio" :key="u.id">
                            <div class="chat__contacto">
                                <span class="chat__avatar">
                                    <img x-show="u.avatar" :src="u.avatar" alt="">
                                    <span x-show="!u.avatar" x-text="u.iniciales"></span>
                                </span>
                                <span class="chat__contacto-info">
                                    <b x-text="u.nombre"></b>
                                    <small x-text="u.rol"></small>
                                </span>
                                <a x-show="u.whatsapp" :href="u.whatsapp" target="_blank" rel="noopener"
                                   class="chat__wa" title="Escribir por WhatsApp">
                                    <x-icono nombre="telefono" />
                                </a>
                                <button type="button" class="btn btn--vidrio chat__hablar" @click="nuevaConversacion(u.id)">
                                    Hablar
                                </button>
                            </div>
                        </template>
                        <p class="chat__vacio-lista" x-show="cargandoDirectorio">Buscando…</p>
                        <p class="chat__vacio-lista" x-show="!cargandoDirectorio && directorio.length === 0">
                            Nadie con ese filtro en el gimnasio.
                        </p>
                    </div>
                </div>
            </div>
        </template>
    </div>
@endsection
