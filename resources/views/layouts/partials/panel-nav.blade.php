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

        @if (auth()->user()->tienePermiso('entrenadores.gestionar') || auth()->user()->tienePermiso('planes.gestionar') || auth()->user()->tienePermiso('sedes.gestionar') || auth()->user()->tienePermiso('usuarios.gestionar') || auth()->user()->tienePermiso('web.editar') || auth()->user()->tienePermiso('ejercicios.gestionar'))
            <span class="panel__grupo-titulo">Configuraciones</span>
        @endif
        {{-- Entrenadores + Planes + Sedes + Usuarios comparten un solo
             enlace: adentro se navegan como pestañas (ver
             admin/configuracion/_pestanas.blade.php), igual que ya
             hacíamos con Contenido web. Aterriza en la primera pestaña a
             la que el usuario tenga permiso. --}}
        @if (auth()->user()->tienePermiso('entrenadores.gestionar') || auth()->user()->tienePermiso('planes.gestionar') || auth()->user()->tienePermiso('sedes.gestionar') || auth()->user()->tienePermiso('usuarios.gestionar'))
            @php
                $configuracionRuta = match (true) {
                    auth()->user()->tienePermiso('entrenadores.gestionar') => route('admin.entrenadores.index'),
                    auth()->user()->tienePermiso('planes.gestionar')       => route('admin.planes.index'),
                    auth()->user()->tienePermiso('sedes.gestionar')        => route('admin.sedes.index'),
                    default                                                => route('admin.usuarios.index'),
                };
            @endphp
            <a class="panel__enlace" href="{{ $configuracionRuta }}" data-title="Configuraciones"
               aria-current="{{ request()->routeIs('admin.entrenadores.*', 'admin.planes.*', 'admin.sedes.*', 'admin.usuarios.*') ? 'true' : 'false' }}">
                <x-icono nombre="entrenador" /> <span class="panel__texto">Configuraciones</span>
            </a>
        @endif
        @if (auth()->user()->tienePermiso('web.editar') || auth()->user()->tienePermiso('ejercicios.gestionar') || auth()->user()->tienePermiso('recetas.gestionar'))
            <a class="panel__enlace" href="{{ route('admin.faqs.index') }}" data-title="Contenido web" aria-current="{{ request()->routeIs('admin.faqs.*', 'admin.testimonios.*', 'admin.ejercicios.*', 'admin.recetas.*') ? 'true' : 'false' }}">
                <x-icono nombre="campana" /> <span class="panel__texto">Contenido web</span>
            </a>
        @endif
    @endif

    @if (auth()->user()->esEntrenador())
        <a class="panel__enlace" href="{{ route('entrenador.dashboard') }}" data-title="Resumen" aria-current="{{ request()->routeIs('entrenador.dashboard') ? 'true' : 'false' }}">
            <x-icono nombre="panel" /> <span class="panel__texto">Resumen</span>
        </a>
        <a class="panel__enlace" href="{{ route('perfil') }}" data-title="Mi perfil" aria-current="{{ request()->routeIs('perfil') ? 'true' : 'false' }}">
            <x-icono nombre="perfil" /> <span class="panel__texto">Mi perfil</span>
        </a>
        @include('layouts.partials.enlace-mensajes')
        @if (auth()->user()->tienePermiso('clientes.crear'))
            {{-- "Rutinas" es Inscripciones (matricular clientes) y vive en
                 /entrenador/rutinas. El constructor de rutinas de ejercicio
                 se movió a /entrenador/entrenamientos (entrenador.rutinas.*,
                 intacto) y se llega a él desde la ficha del cliente. --}}
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
