@extends('layouts.preview')
@section('contenido')
    <section class="seccion" id="recetas">
        <div class="contenedor">
            <div class="seccion__cabecera" data-revelar>
                <span class="eyebrow">Recetas</span>
                <h2>Comer bien<br>es entrenar</h2>
                <p class="lead">Porciones de mano, pasos claros y nada de pesas en la cocina.</p>
            </div>

            <div class="testimonios" data-revelar data-revelar-grupo>
                @forelse ($recetas as $receta)
                    <article class="tarjeta testimonio" style="text-align:left">
                        <span class="tarjeta__filo"></span>

                        <h3 style="font-family:var(--f-display);font-size:var(--t-lg);margin-bottom:var(--e-3)">{{ $receta->name }}</h3>

                        @if ($receta->description)
                            <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-3)">{{ \Illuminate\Support\Str::limit($receta->description, 120) }}</p>
                        @endif

                        <div style="display:flex;gap:var(--e-4);flex-wrap:wrap;font-family:var(--f-mono);font-size:var(--t-xs);color:var(--humo);text-transform:uppercase;letter-spacing:.08em;margin-bottom:var(--e-3)">
                            @if ($receta->prep_minutes)
                                <span>⏱ {{ $receta->prep_minutes }} min</span>
                            @endif
                            @if ($receta->servings)
                                <span>🍽 {{ $receta->servings }} porciones</span>
                            @endif
                        </div>

                        @php
                            $formas = ['palma' => 'palmas', 'puno' => 'puños', 'cuenco' => 'cuencos', 'pulgar' => 'pulgares'];
                            $resumen = collect($receta->conteo ?? [])->filter()->map(fn ($n, $k) => "{$n} " . ($formas[$k] ?? $k))->implode(' · ');
                        @endphp
                        @if ($resumen)
                            <p style="font-size:var(--t-sm);color:var(--brasa);margin-bottom:var(--e-3)">🤲 {{ $resumen }}</p>
                        @endif

                        @if ($receta->tags && count($receta->tags))
                            <div style="display:flex;gap:var(--e-2);flex-wrap:wrap">
                                @foreach ($receta->tags as $tag)
                                    <span class="etiqueta">{{ $tag }}</span>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @empty
                    <p style="color:var(--humo);text-align:center;grid-column:1/-1;padding:var(--e-8)">No hay recetas publicadas todavía.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
