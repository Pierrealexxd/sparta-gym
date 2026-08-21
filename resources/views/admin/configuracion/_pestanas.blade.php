{{-- Compartida por las pantallas de "Configuración" — igual patrón que
     "Contenido web": recursos independientes navegados como pestañas de
     una sola pantalla para no saturar el sidebar con 4 enlaces sueltos
     que casi nadie visita seguido. A diferencia de Contenido web, aquí
     cada pestaña tiene SU PROPIO permiso (no uno compartido), así que se
     ocultan las que el usuario no puede abrir en vez de dejarlo entrar a
     un 403. --}}
<nav class="pestanas__nav pestanas__nav--ancha" style="margin-bottom:var(--e-5)">
    @if (auth()->user()->tienePermiso('entrenadores.gestionar'))
        <a class="pestanas__enlace" href="{{ route('admin.entrenadores.index') }}" aria-current="{{ request()->routeIs('admin.entrenadores.*') ? 'true' : 'false' }}">Entrenadores</a>
    @endif
    {{-- "Planes" se movió a Contenido web (ver admin/contenido/_pestanas.blade.php)
         — la ruta admin.planes.* no cambió, solo dónde vive el enlace. --}}
    @if (auth()->user()->tienePermiso('sedes.gestionar'))
        <a class="pestanas__enlace" href="{{ route('admin.sedes.index') }}" aria-current="{{ request()->routeIs('admin.sedes.*') ? 'true' : 'false' }}">Sedes</a>
    @endif
    @if (auth()->user()->tienePermiso('usuarios.gestionar'))
        <a class="pestanas__enlace" href="{{ route('admin.usuarios.index') }}" aria-current="{{ request()->routeIs('admin.usuarios.*') ? 'true' : 'false' }}">Usuarios</a>
    @endif
</nav>
