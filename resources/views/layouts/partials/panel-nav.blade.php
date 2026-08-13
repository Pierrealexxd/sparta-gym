{{-- Navegación lateral, distinta por rol. Tres roles fijos: una lista de
     @if es más clara aquí que una capa de configuración para algo que no
     va a crecer más allá de estos tres paneles. --}}
@php $nombreSedeActiva = \App\Support\GymContext::current()?->name ?? 'Todas las sedes'; @endphp
@if (auth()->user()->sedesDisponibles()->count() > 1)
    <div class="panel__sede" x-data="{ abierto: false }">
        {{-- data-title: mismo mecanismo de tooltip que un enlace del nav —
             comprimido, este botón queda como icono solo y el nombre de la
             sede sale al pasar el cursor, en vez de nunca mostrarse. --}}
        <button type="button" class="panel__sede-boton" data-title="{{ $nombreSedeActiva }}" @click="abierto = !abierto">
            <x-icono nombre="ubicacion" />
            <span class="panel__sede-texto">{{ $nombreSedeActiva }}</span>
        </button>
        <div class="panel__sede-menu" x-show="abierto" x-cloak @click.outside="abierto = false">
            @foreach (auth()->user()->sedesDisponibles() as $sede)
                <form method="POST" action="{{ route('sede.activar') }}">
                    @csrf
                    <button type="submit" name="sede_id" value="{{ $sede->id }}">{{ $sede->name }}</button>
                </form>
            @endforeach
            @if (auth()->user()->tienePermiso('sedes.ver-todas'))
                <form method="POST" action="{{ route('sede.activar') }}">
                    @csrf
                    <button type="submit" name="sede_id" value="todas">Todas las sedes</button>
                </form>
            @endif
        </div>
    </div>
@else
    {{-- Una sola sede: no hay nada que elegir, pero cualquiera (sobre todo
         un entrenador que solo ve la suya) necesita saber en cuál está
         parado — mismo lugar y mismo look que el selector de arriba, sin
         el desplegable. --}}
    <div class="panel__sede">
        <div class="panel__sede-boton panel__sede-boton--fija" data-title="{{ auth()->user()->gym?->name ?? $nombreSedeActiva }}">
            <x-icono nombre="ubicacion" />
            <span class="panel__sede-texto">{{ auth()->user()->gym?->name ?? $nombreSedeActiva }}</span>
        </div>
    </div>
@endif
<nav class="panel__nav" aria-label="Panel">
    @if (auth()->user()->tieneRol('admin', 'recepcion'))
        <a class="panel__enlace" href="{{ route('admin.dashboard') }}" data-title="Dashboard" aria-current="{{ request()->routeIs('admin.dashboard') ? 'true' : 'false' }}">
            <x-icono nombre="panel" /> <span class="panel__texto">Dashboard</span>
        </a>
        <a class="panel__enlace" href="{{ route('perfil') }}" data-title="Mi perfil" aria-current="{{ request()->routeIs('perfil') ? 'true' : 'false' }}">
            <x-icono nombre="perfil" /> <span class="panel__texto">Mi perfil</span>
        </a>
        @include('layouts.partials.enlace-mensajes')

        <span class="panel__grupo-titulo">Clientes</span>
        <a class="panel__enlace" href="{{ route('admin.clientes.index') }}" data-title="Clientes" aria-current="{{ request()->routeIs('admin.clientes.*') ? 'true' : 'false' }}">
            <x-icono nombre="usuarios" /> <span class="panel__texto">Clientes</span>
        </a>
        {{-- Un solo módulo "Asistencia": calendario del mes + solicitudes de
             corrección, navegadas como pestañas adentro (ver
             admin/asistencia/_pestanas.blade.php). --}}
        <a class="panel__enlace" href="{{ route('admin.asistencia.calendario') }}" data-title="Asistencia" aria-current="{{ request()->routeIs('admin.asistencia.*') ? 'true' : 'false' }}">
            <x-icono nombre="entrada" /> <span class="panel__texto">Asistencia</span>
        </a>

        <span class="panel__grupo-titulo">Dinero</span>
        @if (auth()->user()->tienePermiso('inventario.ver'))
            <a class="panel__enlace" href="{{ route('admin.inventario.index') }}" data-title="Inventario" aria-current="{{ request()->routeIs('admin.inventario.*') ? 'true' : 'false' }}">
                <x-icono nombre="caja" /> <span class="panel__texto">Inventario</span>
            </a>
            <a class="panel__enlace" href="{{ route('admin.ventas.index') }}" data-title="Ventas" aria-current="{{ request()->routeIs('admin.ventas.*') ? 'true' : 'false' }}">
                <x-icono nombre="tarjetas" /> <span class="panel__texto">Ventas</span>
            </a>
        @endif

        @if (auth()->user()->tienePermiso('entrenadores.gestionar') || auth()->user()->tienePermiso('sedes.gestionar') || auth()->user()->tienePermiso('usuarios.gestionar') || auth()->user()->tienePermiso('web.editar') || auth()->user()->tienePermiso('planes.gestionar') || auth()->user()->tienePermiso('ejercicios.gestionar') || auth()->user()->tienePermiso('recetas.gestionar'))
            <span class="panel__grupo-titulo">Configuraciones</span>
        @endif
        {{-- Entrenadores + Sedes + Usuarios comparten un solo enlace:
             adentro se navegan como pestañas (ver
             admin/configuracion/_pestanas.blade.php), igual que ya
             hacíamos con Contenido web. Aterriza en la primera pestaña a
             la que el usuario tenga permiso. "Planes" vivía acá — se
             movió a Contenido web (13-08-2026): son planes que se
             muestran en la web pública, tiene más sentido editarlos junto
             al resto de lo que se ve afuera. --}}
        @if (auth()->user()->tienePermiso('entrenadores.gestionar') || auth()->user()->tienePermiso('sedes.gestionar') || auth()->user()->tienePermiso('usuarios.gestionar'))
            @php
                $configuracionRuta = match (true) {
                    auth()->user()->tienePermiso('entrenadores.gestionar') => route('admin.entrenadores.index'),
                    auth()->user()->tienePermiso('sedes.gestionar')        => route('admin.sedes.index'),
                    default                                                => route('admin.usuarios.index'),
                };
            @endphp
            <a class="panel__enlace" href="{{ $configuracionRuta }}" data-title="Configuraciones"
               aria-current="{{ request()->routeIs('admin.entrenadores.*', 'admin.sedes.*', 'admin.usuarios.*') ? 'true' : 'false' }}">
                <x-icono nombre="entrenador" /> <span class="panel__texto">Configuraciones</span>
            </a>
        @endif
        @if (auth()->user()->tienePermiso('web.editar') || auth()->user()->tienePermiso('planes.gestionar') || auth()->user()->tienePermiso('ejercicios.gestionar') || auth()->user()->tienePermiso('recetas.gestionar'))
            {{-- Igual que el combo de arriba: aterriza en la primera pestaña
                 a la que el usuario tenga permiso, no siempre FAQs — si no,
                 alguien con solo "planes.gestionar" (sin web.editar) caía
                 en admin.faqs.index y se topaba con un 403. --}}
            @php
                $contenidoRuta = match (true) {
                    auth()->user()->tienePermiso('web.editar')       => route('admin.faqs.index'),
                    auth()->user()->tienePermiso('planes.gestionar') => route('admin.planes.index'),
                    auth()->user()->tienePermiso('ejercicios.gestionar') => route('admin.ejercicios.index'),
                    default                                           => route('admin.recetas.index'),
                };
            @endphp
            <a class="panel__enlace" href="{{ $contenidoRuta }}" data-title="Contenido web" aria-current="{{ request()->routeIs('admin.faqs.*', 'admin.testimonios.*', 'admin.ejercicios.*', 'admin.recetas.*', 'admin.contenido.contacto', 'admin.planes.*') ? 'true' : 'false' }}">
                <x-icono nombre="campana" /> <span class="panel__texto">Contenido web</span>
            </a>
        @endif
    @endif

    @if (auth()->user()->esEntrenador())
        {{-- El "Resumen" con KPIs generales se dio de baja: los indicadores
             ahora viven dentro de cada módulo (Rutinas, Ventas, Asistencia). --}}
        <a class="panel__enlace" href="{{ route('perfil') }}" data-title="Mi perfil" aria-current="{{ request()->routeIs('perfil') ? 'true' : 'false' }}">
            <x-icono nombre="perfil" /> <span class="panel__texto">Mi perfil</span>
        </a>
        @include('layouts.partials.enlace-mensajes')
        @if (auth()->user()->tienePermiso('clientes.crear'))
            {{-- "Rutinas" es Inscripciones (matricular clientes) y vive en
                 /entrenador/rutinas. El constructor de rutinas de ejercicio
                 vive en /entrenador/entrenamientos (nombres de ruta
                 entrenador.entrenamientos.*, ya sin el choque de nombres que
                 tenían antes con entrenador.inscripciones.* — ver /investigate
                 del 14-08-2026) y se llega a él desde la ficha del cliente. --}}
            <a class="panel__enlace" href="{{ route('entrenador.inscripciones.index') }}" data-title="Rutinas" aria-current="{{ request()->routeIs('entrenador.inscripciones.*') ? 'true' : 'false' }}">
                <x-icono nombre="lista" /> <span class="panel__texto">Rutinas</span>
            </a>
        @endif
        @if (auth()->user()->tienePermiso('ventas.registrar'))
            <a class="panel__enlace" href="{{ route('entrenador.ventas.index') }}" data-title="Ventas" aria-current="{{ request()->routeIs('entrenador.ventas.*') ? 'true' : 'false' }}">
                <x-icono nombre="caja" /> <span class="panel__texto">Ventas</span>
            </a>
        @endif
        @if (auth()->user()->tienePermiso('asistencia.registrar'))
            {{-- Aterriza directo en su propia marcación (entrada/salida) —
                 el calendario de asistencias de clientes que registra vive
                 aparte, no acá; esto es solo lo suyo. --}}
            <a class="panel__enlace" href="{{ route('entrenador.asistencia.mi-marcacion') }}" data-title="Asistencia" aria-current="{{ request()->routeIs('entrenador.asistencia.mi-marcacion', 'entrenador.asistencia.marcar', 'entrenador.asistencia.solicitar-edicion', 'entrenador.asistencia.eliminar') ? 'true' : 'false' }}">
                <x-icono nombre="entrada" /> <span class="panel__texto">Asistencia</span>
            </a>
        @endif
    @endif

    @if (auth()->user()->esCliente())
        <a class="panel__enlace" href="{{ route('cliente.dashboard') }}" data-title="Mi panel" aria-current="{{ request()->routeIs('cliente.dashboard') ? 'true' : 'false' }}">
            <x-icono nombre="panel" /> <span class="panel__texto">Mi panel</span>
        </a>
        <a class="panel__enlace" href="{{ route('perfil') }}" data-title="Mi perfil" aria-current="{{ request()->routeIs('perfil') ? 'true' : 'false' }}">
            <x-icono nombre="perfil" /> <span class="panel__texto">Mi perfil</span>
        </a>
        @include('layouts.partials.enlace-mensajes')
        <a class="panel__enlace" href="{{ route('cliente.progreso') }}" data-title="Mi progreso" aria-current="{{ request()->routeIs('cliente.progreso') ? 'true' : 'false' }}">
            <x-icono nombre="grafico" /> <span class="panel__texto">Mi progreso</span>
        </a>
    @endif

    <a class="panel__enlace panel__enlace--pie" href="{{ route('landing') }}" data-title="Ver web pública">
        <x-icono nombre="ubicacion" /> <span class="panel__texto">Ver web pública</span>
    </a>
</nav>
