@props(['clave', 'defecto' => 'lista'])

{{-- Botón para elegir entre ver esta pantalla como lista o como calendario.
     Mismo componente en Personal (admin) y Mi marcación (entrenador) para
     que el diseño no cambie de un apartado a otro. Se recuerda por pantalla
     (localStorage, una clave por módulo) — cada quien ve lo que prefiere
     sin tener que elegir cada vez que entra. --}}
<div x-data="{ vista: localStorage.getItem('vista:{{ $clave }}') || '{{ $defecto }}' }"
     x-effect="localStorage.setItem('vista:{{ $clave }}', vista)">
    <nav class="pestanas__nav pestanas__nav--ancha" style="margin-bottom:var(--e-5)" aria-label="Elegir diseño">
        <button type="button" class="pestanas__enlace" :aria-current="vista === 'lista' ? 'true' : 'false'" @click="vista = 'lista'">
            <x-icono nombre="lista" style="width:1em;height:1em;vertical-align:-2px" /> Lista
        </button>
        <button type="button" class="pestanas__enlace" :aria-current="vista === 'calendario' ? 'true' : 'false'" @click="vista = 'calendario'">
            <x-icono nombre="panel" style="width:1em;height:1em;vertical-align:-2px" /> Calendario
        </button>
    </nav>

    <div x-show="vista === 'lista'" x-cloak>{{ $lista }}</div>
    <div x-show="vista === 'calendario'" x-cloak>{{ $calendario }}</div>
</div>
