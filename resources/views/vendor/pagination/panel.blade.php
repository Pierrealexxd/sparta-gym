{{-- Paginación propia del panel: sin Tailwind. El default de Laravel
     renderiza clases Tailwind que aquí no existen, por eso las flechas
     salían rotas y los números se ocultaban en móvil. --}}
@if ($paginator->hasPages())
    <p class="paginacion__info">Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}</p>
    <nav class="paginacion__nav" aria-label="Paginación">
        {{-- Anterior --}}
        @if ($paginator->onFirstPage())
            <span class="paginacion__enlace paginacion__flecha is-agotado" aria-disabled="true" aria-label="Anterior">
                <x-icono nombre="flecha-der" style="transform:scaleX(-1)" />
            </span>
        @else
            <a class="paginacion__enlace paginacion__flecha" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Anterior">
                <x-icono nombre="flecha-der" style="transform:scaleX(-1)" />
            </a>
        @endif

        {{-- Números y elipsis: $elements trae páginas (array) o "..." (string) --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="paginacion__puntos">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $pagina => $url)
                    @if ($pagina == $paginator->currentPage())
                        <span class="paginacion__enlace is-actual" aria-current="page">{{ $pagina }}</span>
                    @else
                        <a class="paginacion__enlace" href="{{ $url }}">{{ $pagina }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Siguiente --}}
        @if ($paginator->hasMorePages())
            <a class="paginacion__enlace paginacion__flecha" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Siguiente">
                <x-icono nombre="flecha-der" />
            </a>
        @else
            <span class="paginacion__enlace paginacion__flecha is-agotado" aria-disabled="true" aria-label="Siguiente">
                <x-icono nombre="flecha-der" />
            </span>
        @endif
    </nav>
@endif
