{{-- Navegación del panel de asistencia del entrenador: su fichaje laboral
     y el calendario de asistencias de clientes que él registró. --}}
<nav class="pestanas__nav pestanas__nav--ancha" style="margin-bottom:var(--e-5)">
    <a class="pestanas__enlace" href="{{ route('entrenador.asistencia.calendario') }}"
       aria-current="{{ request()->routeIs('entrenador.asistencia.calendario', 'entrenador.asistencia.registrar', 'entrenador.asistencia.salida', 'entrenador.asistencia.solicitar-correccion', 'entrenador.asistencia.clientes') ? 'true' : 'false' }}">Mis clientes</a>
    <a class="pestanas__enlace" href="{{ route('entrenador.asistencia.mi-marcacion') }}"
       aria-current="{{ request()->routeIs('entrenador.asistencia.mi-marcacion', 'entrenador.asistencia.marcar', 'entrenador.asistencia.solicitar-edicion', 'entrenador.asistencia.eliminar') ? 'true' : 'false' }}">Mi marcación</a>
</nav>
