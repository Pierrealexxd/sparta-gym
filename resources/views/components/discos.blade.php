@props(['valor' => 0, 'total' => 8])

{{-- La pila de discos: sustituye a la barra de progreso genérica. Cada disco
     es una unidad, como en una barra cargada. Los cargados van en fuego. --}}
<div class="discos" data-discos="{{ $valor }}"
     role="img" aria-label="{{ $valor }} de {{ $total }}">
    @for ($i = 0; $i < $total; $i++)
        @php
            // Los discos centrales son más altos: una barra cargada es simétrica.
            $distancia = abs($i - ($total - 1) / 2) / max(1, ($total - 1) / 2);
            $alto = round(46 - $distancia * 22);
        @endphp
        <span class="discos__disco"
              style="height:0"
              data-alto="{{ $alto }}px"
              @if ($i < $valor) data-cargado="no" @endif></span>
    @endfor
</div>
