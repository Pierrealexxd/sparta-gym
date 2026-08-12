@props([
    'etiqueta'        => '¿Ya es cliente? Buscarlo (opcional)',
    'placeholder'     => 'Nombre, código o documento…',
    'bloqueadoCuando' => null,
])

{{-- Buscador de cliente con desplegable, unificado: antes había tres
     variantes distintas (tarjeta con botones, filas de tabla, <select>)
     repitiendo la misma idea con clases distintas. Este componente es solo
     el input + la lista flotante — qué pasa al elegir (autorrellenar un
     wizard, marcar un chip, etc.) sigue siendo cosa de cada pantalla.

     Contrato con el x-data que lo envuelve: debe exponer
     `buscarQ` (texto), `resultados` (array), `buscarCliente()` (dispara la
     búsqueda) y `elegirCliente(m)` (qué hacer al elegir uno). Cada
     resultado trae al menos `id`, `full_name` y `code`. --}}
<div class="buscador" style="position:relative" @click.outside="resultados = []">
    <label class="campo">
        <span class="campo__etiqueta">{{ $etiqueta }}</span>
        <input class="campo__control" type="search" x-model="buscarQ"
               @input.debounce.300ms="buscarCliente()"
               @if ($bloqueadoCuando) :disabled="{{ $bloqueadoCuando }}" @endif
               placeholder="{{ $placeholder }}" autocomplete="off">
    </label>

    <div class="buscador__lista" x-show="resultados.length" x-cloak>
        <template x-for="m in resultados" :key="m.id">
            <button type="button" class="buscador__item" @click="elegirCliente(m)">
                <span class="buscador__nombre" x-text="m.full_name"></span>
                <span class="buscador__codigo" x-text="m.code ?? ''"></span>
            </button>
        </template>
    </div>
</div>
