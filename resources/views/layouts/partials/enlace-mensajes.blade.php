{{-- Un solo lugar para el enlace "Mensajes", incluido cerca del inicio de
     los tres paneles (justo después del enlace de entrada de cada rol) —
     antes vivía solo hasta abajo, bajo "Cuenta", donde nadie lo veía. --}}
<a class="panel__enlace" href="{{ route('mensajes') }}" data-title="Mensajes" aria-current="{{ request()->routeIs('mensajes*') ? 'true' : 'false' }}">
    <x-icono nombre="chat" /> <span class="panel__texto">Mensajes</span>
    <span class="panel__contador" data-mensajes-no-leidas
          data-url="{{ route('mensajes.no-leidas') }}" hidden>0</span>
</a>
