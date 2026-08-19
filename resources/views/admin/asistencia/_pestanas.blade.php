{{-- Único módulo "Asistencia": el calendario de marcaciones laborales del
     staff (antes "Personal", ahora la única vista — el calendario de
     clientes y el registro por torno se dieron de baja) y las solicitudes
     de corrección que envían los entrenadores. --}}
<nav class="pestanas__nav pestanas__nav--ancha" style="margin-bottom:var(--e-5)">
    <a class="pestanas__enlace" href="{{ route('admin.asistencia.calendario') }}" aria-current="{{ request()->routeIs('admin.asistencia.calendario') ? 'true' : 'false' }}">Calendario</a>
    @if (auth()->user()->tienePermiso('asistencia.aprobar'))
        <a class="pestanas__enlace" href="{{ route('admin.asistencia.solicitudes.index') }}" aria-current="{{ request()->routeIs('admin.asistencia.solicitudes.*') ? 'true' : 'false' }}">Solicitudes de corrección</a>
    @endif
</nav>
