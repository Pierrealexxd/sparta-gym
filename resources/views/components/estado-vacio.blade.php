@props(['icono' => 'lista', 'texto' => 'Sin resultados.'])

{{-- Reemplaza el texto suelto que había en cada tabla vacía: mismo mensaje,
     pero con el icono correspondiente en vez de una celda en blanco. --}}
<div class="estado-vacio">
    <span class="estado-vacio__icono"><x-icono :nombre="$icono" /></span>
    <p>{{ $texto }}</p>
</div>
