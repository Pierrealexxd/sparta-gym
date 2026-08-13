@extends('layouts.panel')

@section('titulo', $cliente->full_name)
@section('subtitulo', $cliente->code)

@section('contenido')
    <div class="g-2-1">
        <article class="tarjeta grafico">
            <h3 style="font-size:var(--t-lg)">Evolución de peso</h3>
            <div class="grafico__lienzo">
                <canvas data-grafico="{{ json_encode(['tipo' => 'line', 'labels' => $grafico['labels'], 'label' => 'Peso (kg)', 'data' => $grafico['data']]) }}"></canvas>
            </div>
        </article>

        <form class="tarjeta formulario-panel" method="POST" action="{{ route('entrenador.clientes.medidas.store', $cliente) }}">
            @csrf
            <h3 style="font-size:var(--t-lg)">Registrar medida</h3>
            <label class="campo"><span class="campo__etiqueta">Fecha</span>
                <input class="campo__control" type="date" name="measured_at" value="{{ now()->toDateString() }}" required></label>
            <label class="campo"><span class="campo__etiqueta">Peso (kg)</span>
                <input class="campo__control" type="number" step="0.1" name="weight_kg" required></label>
            <label class="campo"><span class="campo__etiqueta">% Grasa</span>
                <input class="campo__control" type="number" step="0.1" name="body_fat_pct"></label>
            <label class="campo"><span class="campo__etiqueta">Notas</span>
                <textarea class="campo__control" name="notes"></textarea></label>
            <button class="btn btn--fuego" type="submit">Guardar</button>
        </form>
    </div>

    <article class="tarjeta">
        {{-- Fase 2 del plan de nutrición: solo lectura — el diario lo llena
             el propio socio desde Mi progreso. Fila propia a ancho completo:
             .g-1-1 es de 2 columnas fijas, y esta tarjeta va mejor como
             franja horizontal (una comida por columna) que apretada junto a
             Objetivos/Rutinas. --}}
        <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-4)">Comidas de hoy</h3>
        @php
            $ordenComidas = ['desayuno' => 'Desayuno', 'almuerzo' => 'Almuerzo', 'cena' => 'Cena', 'merienda' => 'Merienda'];
        @endphp
        <div style="display:grid;gap:var(--e-4);grid-template-columns:repeat(auto-fit, minmax(12rem, 1fr))">
            @foreach ($ordenComidas as $tipo => $etiqueta)
                @php
                    $comida = $cliente->mealLogs->firstWhere('meal_type', $tipo);
                    $formasCortas = ['palma' => 'palmas', 'puno' => 'puños', 'cuenco' => 'cuencos', 'pulgar' => 'pulgares'];
                    $resumen = $comida ? collect($comida->conteo)->filter()->map(fn ($n, $k) => "{$n} {$formasCortas[$k]}")->implode(' · ') : null;
                @endphp
                <div>
                    <p style="font-size:var(--t-sm);color:var(--humo)">{{ $etiqueta }}</p>
                    <p style="font-family:var(--f-mono);font-size:var(--t-sm)">{{ $resumen ?: 'Sin registrar' }}</p>
                </div>
            @endforeach
        </div>
    </article>

    <div class="g-1-1">
        <article class="tarjeta">
            <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-4)">Objetivos</h3>
            @forelse ($cliente->goals as $meta)
                @php
                    // Fase 1 del plan de nutrición: misma referencia de
                    // porciones que ve el socio en Mi progreso, para que el
                    // entrenador la use al hablar de alimentación con él.
                    $formas = [
                        'palma'  => ['palma', 'palmas'],
                        'puno'   => ['puño', 'puños'],
                        'cuenco' => ['cuenco', 'cuencos'],
                        'pulgar' => ['pulgar', 'pulgares'],
                    ];
                    $porciones = collect($cliente->porcionesPara($meta))
                        ->map(fn ($n, $k) => $n . ' ' . $formas[$k][$n === 1 ? 0 : 1])
                        ->implode(' · ');
                @endphp
                <div style="margin-bottom:var(--e-3)">
                    <div class="ficha__dato"><span>{{ $meta->title }}</span><span>{{ $meta->target_value ? $meta->target_value . ' ' . $meta->unit : '' }}</span></div>
                    <p style="color:var(--brasa);font-size:var(--t-xs);font-family:var(--f-mono)">{{ $porciones }} por comida</p>
                </div>
            @empty
                <p style="color:var(--humo);font-size:var(--t-sm)">Sin objetivos definidos.</p>
            @endforelse
        </article>

        <article class="tarjeta">
            <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-4)">Rutinas activas</h3>
            @forelse ($cliente->routines as $rutina)
                <div class="ficha__dato">
                    <span><a href="{{ route('entrenador.entrenamientos.show', $rutina) }}">{{ $rutina->name }}</a></span>
                    <span>{{ $rutina->days->count() }} días</span>
                </div>
            @empty
                <p style="color:var(--humo);font-size:var(--t-sm)">Sin rutina activa. <a class="btn btn--desnudo" href="{{ route('entrenador.entrenamientos.create') }}">Crear una</a></p>
            @endforelse
        </article>
    </div>
@endsection
