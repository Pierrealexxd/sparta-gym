{{-- Compartida por las pantallas de "Contenido web": son recursos
     independientes, pero se navegan como si fueran pestañas de una sola
     pantalla para no saturar el sidebar — cualquier módulo nuevo que
     edite algo de la página pública entra aquí, no como enlace suelto. --}}
<nav class="pestanas__nav" style="margin-bottom:var(--e-5)">
    <a class="pestanas__enlace" href="{{ route('admin.faqs.index') }}" aria-current="{{ request()->routeIs('admin.faqs.*') ? 'true' : 'false' }}">FAQs</a>
    <a class="pestanas__enlace" href="{{ route('admin.testimonios.index') }}" aria-current="{{ request()->routeIs('admin.testimonios.*') ? 'true' : 'false' }}">Testimonios</a>
    <a class="pestanas__enlace" href="{{ route('admin.ejercicios.index') }}" aria-current="{{ request()->routeIs('admin.ejercicios.*') ? 'true' : 'false' }}">Biblioteca</a>
    @if (auth()->user()->tienePermiso('recetas.gestionar'))
        <a class="pestanas__enlace" href="{{ route('admin.recetas.index') }}" aria-current="{{ request()->routeIs('admin.recetas.*') ? 'true' : 'false' }}">Recetas</a>
    @endif
    {{-- Movido acá desde "Configuración" (13-08-2026): los planes son
         contenido que se muestra en la web pública (planes.blade.php de
         landing), tiene más sentido editarlos junto al resto de lo que se
         ve afuera que junto a Entrenadores/Sedes/Usuarios. --}}
    @if (auth()->user()->tienePermiso('planes.gestionar'))
        <a class="pestanas__enlace" href="{{ route('admin.planes.index') }}" aria-current="{{ request()->routeIs('admin.planes.*') ? 'true' : 'false' }}">Planes</a>
    @endif
    <a class="pestanas__enlace" href="{{ route('admin.contenido.contacto') }}" aria-current="{{ request()->routeIs('admin.contenido.contacto') ? 'true' : 'false' }}">Contacto</a>
</nav>
