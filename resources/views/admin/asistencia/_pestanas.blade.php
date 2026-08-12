{{-- Único módulo "Asistencia": el calendario del mes y las solicitudes de
     corrección que envían los entrenadores, como las dos pestañas. Marcar
     la entrada de un socio ya no es una pestaña aparte — es el botón
     "Marcar entrada" del propio Calendario (ver admin/asistencia/calendario.blade.php). --}}
<nav class="pestanas__nav" style="margin-bottom:var(--e-5)">
    <a class="pestanas__enlace" href="{{ route('admin.asistencia.calendario') }}" aria-current="{{ request()->routeIs('admin.asistencia.calendario') ? 'true' : 'false' }}">Calendario</a>
    <a class="pestanas__enlace" href="{{ route('admin.asistencia.personal') }}" aria-current="{{ request()->routeIs('admin.asistencia.personal') ? 'true' : 'false' }}">Personal</a>
    @if (auth()->user()->tienePermiso('asistencia.aprobar'))
        <a class="pestanas__enlace" href="{{ route('admin.asistencia.solicitudes.index') }}" aria-current="{{ request()->routeIs('admin.asistencia.solicitudes.*') ? 'true' : 'false' }}">Solicitudes de corrección</a>
    @endif
</nav>
