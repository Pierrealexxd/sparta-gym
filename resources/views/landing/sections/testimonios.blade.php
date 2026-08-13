<section class="seccion">
    <div class="contenedor">
        <div class="seccion__cabecera" data-revelar>
            <span class="eyebrow">Clientes</span>
            <h2>Lo dicen ellos</h2>
        </div>

        {{-- Ver planes.blade.php: mismo carrusel automático en móvil.
             data-carrusel-3d: acá sí lleva coverflow + atenuación (P1+P2
             del plan de carruseles 3D) — la voz que se lee queda al
             frente, el resto en penumbra. --}}
        <div class="testimonios" data-carrusel="3600" data-carrusel-3d data-revelar data-revelar-grupo>
            @foreach ($testimonios as $testimonio)
                <blockquote class="tarjeta tarjeta--interactiva testimonio">
                    <span class="tarjeta__filo"></span>

                    <div class="testimonio__estrellas" aria-label="{{ $testimonio->rating }} de 5">
                        @for ($i = 0; $i < $testimonio->rating; $i++)
                            <x-icono nombre="estrella" />
                        @endfor
                    </div>

                    <p class="testimonio__texto">{{ $testimonio->content }}</p>

                    <footer class="testimonio__autor">
                        <span class="testimonio__avatar" aria-hidden="true">
                            {{ mb_substr($testimonio->author, 0, 1) }}
                        </span>
                        <span class="testimonio__nombre">
                            <b>{{ $testimonio->author }}</b>
                            <small>{{ $testimonio->role }}</small>
                        </span>
                    </footer>
                </blockquote>
            @endforeach
        </div>
    </div>
</section>
