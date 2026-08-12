@extends('layouts.panel')

@section('titulo', 'Mi progreso')

@section('contenido')
    <div class="kpis">
        <article class="tarjeta kpi">
            <b class="kpi__valor">{{ $ultima?->weight_kg ?? '—' }} kg</b>
            <span class="kpi__etiqueta">Peso actual</span>
            @if ($primera && $ultima && $primera->id !== $ultima->id)
                @php $dif = round($ultima->weight_kg - $primera->weight_kg, 1); @endphp
                <span class="kpi__variacion {{ $dif <= 0 ? 'kpi__variacion--pos' : 'kpi__variacion--neg' }}">
                    {{ $dif > 0 ? '+' : '' }}{{ $dif }} kg desde el inicio
                </span>
            @endif
        </article>

        <article class="tarjeta kpi">
            <b class="kpi__valor">{{ $ultima?->bmi ?? '—' }}</b>
            <span class="kpi__etiqueta">IMC</span>
            @if ($ultima?->bmi_category)
                @php
                    $claseIcm = match ($ultima->bmi_category) {
                        'Normal' => 'estado--activo',
                        'Bajo peso', 'Sobrepeso' => 'estado--pendiente',
                        default => 'estado--suspendido',
                    };
                @endphp
                <span class="estado {{ $claseIcm }}" style="justify-self:start">{{ $ultima->bmi_category }}</span>
            @endif
        </article>

        <article class="tarjeta kpi">
            <b class="kpi__valor">{{ $ultima?->body_fat_pct ?? '—' }}%</b>
            <span class="kpi__etiqueta">Grasa corporal</span>
            @if ($ultima?->body_fat_pct && $primera?->body_fat_pct && $primera->id !== $ultima->id)
                @php $difGrasa = round($ultima->body_fat_pct - $primera->body_fat_pct, 1); @endphp
                <span class="kpi__variacion {{ $difGrasa <= 0 ? 'kpi__variacion--pos' : 'kpi__variacion--neg' }}">
                    {{ $difGrasa > 0 ? '+' : '' }}{{ $difGrasa }} pt desde el inicio
                </span>
            @endif
        </article>

        @if ($primera)
            <article class="tarjeta kpi">
                <b class="kpi__valor">{{ max(0, $primera->measured_at->diffInDays(now())) }}</b>
                <span class="kpi__etiqueta">Días registrando</span>
                <span class="kpi__variacion kpi__variacion--pos">constancia</span>
            </article>
        @endif
    </div>

    <div class="g-2-1">
        <article class="tarjeta">
            <div class="grafico__cabecera">
                <h3 style="font-size:var(--t-lg)">Registrar hoy</h3>
                @if ($hoy)
                    <span class="estado estado--activo">Registrado hoy</span>
                @endif
            </div>
            <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-4)">
                @if ($hoy)
                    Ya registraste tu peso de hoy ({{ $hoy->weight_kg }} kg). Actualízalo si cambió.
                @else
                    Pésate y anota: un registro diario dibuja tu curva.
                @endif
            </p>

            <form method="POST" action="{{ route('cliente.progreso.guardar') }}" class="formulario-panel">
                @csrf
                <input type="hidden" name="measured_at"
                       value="{{ $hoy?->measured_at->toDateString() ?? now()->toDateString() }}">

                <div class="formulario-panel__fila">
                    <label class="campo">
                        <span class="campo__etiqueta">Peso (kg)</span>
                        <input class="campo__control" type="number" name="weight_kg" step="0.1" min="20" max="400"
                               required value="{{ $hoy?->weight_kg ?? old('weight_kg') }}">
                    </label>
                    <label class="campo">
                        <span class="campo__etiqueta">% Grasa (opcional)</span>
                        <input class="campo__control" type="number" name="body_fat_pct" step="0.1" min="2" max="70"
                               value="{{ $hoy?->body_fat_pct ?? old('body_fat_pct') }}">
                    </label>
                </div>

                <label class="campo">
                    <span class="campo__etiqueta">Nota (opcional)</span>
                    <input class="campo__control" type="text" name="notes" maxlength="500"
                           placeholder="Ej. bien de energía, entrené fuerza"
                           value="{{ $hoy?->notes ?? old('notes') }}">
                </label>

                @error('measured_at') <p class="campo__error">{{ $message }}</p> @enderror
                @error('weight_kg') <p class="campo__error">{{ $message }}</p> @enderror

                <div class="formulario-panel__acciones">
                    <button class="btn btn--fuego" type="submit">{{ $hoy ? 'Actualizar' : 'Registrar' }}</button>
                </div>
            </form>
        </article>

        <article class="tarjeta">
            <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-4)">Mis metas</h3>
            @forelse ($metas as $item)
                @php
                    $meta = $item['meta']; $progreso = $item['progreso'];
                    // Fase 1 del plan de nutrición: referencia de porciones
                    // de mano derivada de género + tipo de meta, no guardada
                    // (ver Member::porcionesPara).
                    $formas = [
                        'palma'  => ['palma', 'palmas'],
                        'puno'   => ['puño', 'puños'],
                        'cuenco' => ['cuenco', 'cuencos'],
                        'pulgar' => ['pulgar', 'pulgares'],
                    ];
                    $porciones = collect($socio->porcionesPara($meta))
                        ->map(fn ($n, $k) => $n . ' ' . $formas[$k][$n === 1 ? 0 : 1])
                        ->implode(' · ');
                @endphp
                <div style="margin-bottom:var(--e-5)">
                    <div class="ficha__dato">
                        <span>{{ $meta->title }}</span>
                        <span>{{ $progreso !== null ? round($progreso * 100) . '%' : ($meta->target_value ? $meta->target_value . ' ' . $meta->unit : '—') }}</span>
                    </div>
                    @if ($progreso !== null)
                        <div style="margin-top:var(--e-2)">
                            <x-discos :valor="(int) round($progreso * 8)" :total="8" />
                        </div>
                    @endif
                    @if ($meta->description)
                        <p style="color:var(--humo);font-size:var(--t-xs);margin-top:var(--e-2)">{{ $meta->description }}</p>
                    @endif
                    <p style="color:var(--brasa);font-size:var(--t-xs);margin-top:var(--e-2);font-family:var(--f-mono)">
                        {{ $porciones }} por comida
                    </p>
                </div>
            @empty
                <p style="color:var(--humo);font-size:var(--t-sm)">Tu entrenador aún no definió objetivos.</p>
            @endforelse
        </article>
    </div>

    {{-- Fase 0 del plan de nutrición (ver PLAN_NUTRICION_PROGRESO.md): pura
         guía, sin datos — mide el plato con la mano, sin balanza. --}}
    <div x-data="{ guiaAbierta: false }">
    <article class="tarjeta" data-revelar>
        <div class="grafico__cabecera">
            <h3 style="font-size:var(--t-lg)">Tu balanza la llevas puesta</h3>
            <button class="btn btn--vidrio" type="button" @click="guiaAbierta = true">Ver guía completa</button>
        </div>
        <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-5)">
            El peso es la salida. Lo que comés es la entrada — y se mide sin balanza, con la mano.
        </p>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(11rem, 1fr));gap:var(--e-4)">
            @foreach ([
                ['mano-palma', 'Palma', 'Proteína', '1 palma ≈ 100 g carne o 2 huevos'],
                ['mano-puno', 'Puño', 'Verduras', '1 puño ≈ 1 taza de brócoli o espinaca'],
                ['mano-cuenco', 'Cuenco', 'Carbohidratos', '1 cuenco ≈ ½ taza de arroz o 1 fruta'],
                ['mano-pulgar', 'Pulgar', 'Grasas', '1 pulgar ≈ 1 cda de aceite o mantequilla de maní'],
            ] as [$icono, $nombre, $tipo, $ejemplo])
                <div style="display:flex;align-items:flex-start;gap:var(--e-3)">
                    <span style="flex:none;width:2.5em;height:2.5em;display:grid;place-items:center;border-radius:var(--r-full);background:var(--relleno-suave);color:var(--brasa)">
                        <x-icono :nombre="$icono" style="width:1.3em;height:1.3em" />
                    </span>
                    <div style="min-width:0">
                        <b style="display:block;color:var(--hueso)">{{ $nombre }} <span style="color:var(--ceniza);font-weight:normal;font-size:var(--t-xs)">· {{ $tipo }}</span></b>
                        <span style="color:var(--ceniza);font-size:var(--t-xs)">{{ $ejemplo }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </article>

    <div class="modal__fondo" x-show="guiaAbierta" x-cloak @keydown.escape.window="guiaAbierta = false">
            <div class="tarjeta modal__caja modal__caja--ancho" @click.outside="guiaAbierta = false">
                <div class="modal__cabecera">
                    <h3>Tu balanza la llevas puesta</h3>
                    <button class="modal__cerrar" type="button" @click="guiaAbierta = false" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
                </div>

                <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-5)">
                    Cuatro porciones, una por mano, sin balanza. Es ~95% tan preciso como pesar la comida — lo que importa es medir siempre igual, no medir exacto.
                </p>

                <div class="tabla-envoltorio" style="margin-bottom:var(--e-5)">
                    <table class="tabla tabla--tarjetas">
                        <thead><tr><th>Porción</th><th>Mide</th><th>Equivale a</th><th>Aprox.</th></tr></thead>
                        <tbody>
                            <tr><td class="es-fuerte" data-etiqueta="Porción">Palma</td><td data-etiqueta="Mide">Proteína</td><td data-etiqueta="Equivale a">~100 g carne/tofu, 1 yogurt griego, 2 huevos</td><td data-etiqueta="Aprox.">~24 g prot · 145 kcal</td></tr>
                            <tr><td class="es-fuerte" data-etiqueta="Porción">Puño</td><td data-etiqueta="Mide">Verduras</td><td data-etiqueta="Equivale a">~1 taza (brócoli, espinaca, zanahoria)</td><td data-etiqueta="Aprox.">~25 kcal</td></tr>
                            <tr><td class="es-fuerte" data-etiqueta="Porción">Cuenco</td><td data-etiqueta="Mide">Carbohidratos</td><td data-etiqueta="Equivale a">~½-⅔ taza arroz/quinoa, 1 fruta, 1 tubérculo</td><td data-etiqueta="Aprox.">~25 g carbos · 120 kcal</td></tr>
                            <tr><td class="es-fuerte" data-etiqueta="Porción">Pulgar</td><td data-etiqueta="Mide">Grasas</td><td data-etiqueta="Equivale a">~1 cda aceite, mantequilla de maní, queso, frutos secos</td><td data-etiqueta="Aprox.">~9 g grasa · 100 kcal</td></tr>
                        </tbody>
                    </table>
                </div>

                <div style="display:grid;gap:var(--e-4)">
                    <div class="aviso">
                        <b style="display:block;color:var(--hueso);margin-bottom:var(--e-1)">Referencia diaria (por comida, ~4 al día)</b>
                        Hombre: 2 palmas · 2 puños · 2 cuencos · 2 pulgares.<br>
                        Mujer: 1 palma · 1 puño · 1 cuenco · 1 pulgar.
                    </div>
                    <div class="aviso">
                        <b style="display:block;color:var(--hueso);margin-bottom:var(--e-1)">Ajuste según tu meta</b>
                        Perder grasa: quita 1-2 cuencos + 1-2 pulgares (≈ -250 kcal/día).<br>
                        Ganar músculo: agrega 1-2 cuencos + 1-2 pulgares (≈ +250 kcal/día).
                    </div>
                    <div class="aviso">
                        <b style="display:block;color:var(--hueso);margin-bottom:var(--e-1)">Regla de oro</b>
                        Ancla cada comida a tu palma de proteína, siempre primero. Y mide siempre igual (p. ej. siempre cocida) — la constancia importa más que la precisión.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Fase 4 del plan de nutrición: equivalencias con objetos cotidianos,
         para quien todavía no tiene calibrado el ojo con su propia mano. --}}
    <article class="tarjeta" data-revelar>
        <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-2)">Equivalencias sin balanza</h3>
        <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-4)">
            Si aún no le agarras la medida a tu mano, estos objetos ayudan a calibrar el ojo.
        </p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(11rem, 1fr));gap:var(--e-3)">
            @foreach ([
                ['Mazo de cartas' => '1 palma de proteína (~100 g de carne)'],
                ['Pelota de tenis' => '1 cuenco de carbohidratos (~½ taza)'],
                ['Ratón de computadora' => '1 puño de verduras'],
                ['Dado grande' => '1 pulgar de grasa (~1 cda de aceite)'],
            ] as $par)
                @foreach ($par as $objeto => $equivale)
                    <div style="display:flex;align-items:flex-start;gap:var(--e-2);font-size:var(--t-xs)">
                        <span style="color:var(--brasa)">≈</span>
                        <span><b style="color:var(--hueso);font-weight:normal">{{ $objeto }}</b><br><span style="color:var(--ceniza)">{{ $equivale }}</span></span>
                    </div>
                @endforeach
            @endforeach
        </div>
    </article>

    {{-- Fase 2 del plan de nutrición: diario de comidas por porciones, sin
         calorías ni gramos — un registro por comida por día. --}}
    <article class="tarjeta" data-revelar>
        <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-2)">Hoy comiste</h3>
        <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-5)">
            Anota tus comidas del día por porciones — nada de pesar ni contar calorías.
        </p>

        @php
            $formasCortas = ['palma' => 'Palma', 'puno' => 'Puño', 'cuenco' => 'Cuenco', 'pulgar' => 'Pulgar'];
            $faltantes = collect($objetivoDiario)
                ->filter(fn ($objetivo, $tipo) => $objetivo > ($totalHoy[$tipo] ?? 0))
                ->map(fn ($objetivo, $tipo) => ($objetivo - $totalHoy[$tipo]) . ' ' . mb_strtolower($formasCortas[$tipo]) . ($objetivo - $totalHoy[$tipo] === 1 ? '' : 's'));
        @endphp

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(9rem, 1fr));gap:var(--e-4);margin-bottom:var(--e-5)">
            @foreach ($totalHoy as $tipo => $cantidad)
                <div>
                    <div style="display:flex;justify-content:space-between;font-size:var(--t-xs);color:var(--ceniza);margin-bottom:var(--e-2)">
                        <span>{{ $formasCortas[$tipo] }}s</span>
                        <span style="font-family:var(--f-mono)">{{ $cantidad }}/{{ $objetivoDiario[$tipo] }}</span>
                    </div>
                    <x-discos :valor="$cantidad" :total="max($cantidad, $objetivoDiario[$tipo])" />
                </div>
            @endforeach
        </div>

        @if ($faltantes->isNotEmpty())
            <p style="color:var(--brasa);font-size:var(--t-xs);font-family:var(--f-mono);margin-bottom:var(--e-5)">
                Te faltan: {{ $faltantes->implode(' · ') }}
            </p>
        @endif

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(16rem, 1fr));gap:var(--e-4)">
            @foreach ($tiposComida as $tipo)
                @php $registro = $comidasHoy->get($tipo); $conteo = $registro?->conteo ?? ['palma' => '', 'puno' => '', 'cuenco' => '', 'pulgar' => '']; @endphp
                <form method="POST" action="{{ route('cliente.progreso.comidas.guardar') }}"
                      class="tarjeta" style="padding:var(--e-4);background:var(--relleno-suave)">
                    @csrf
                    <input type="hidden" name="meal_type" value="{{ $tipo }}">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--e-3)">
                        <b style="font-size:var(--t-sm)">{{ ucfirst($tipo) }}</b>
                        @if ($registro)
                            <span class="estado estado--activo">Registrado</span>
                        @endif
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:var(--e-2)">
                        @foreach ($formasCortas as $clave => $etiqueta)
                            <label class="campo">
                                <span class="campo__etiqueta" style="font-size:.6rem">{{ $etiqueta }}</span>
                                <input class="campo__control" type="number" name="{{ $clave }}" min="0" max="20"
                                       value="{{ $conteo[$clave] }}" style="padding:.5rem">
                            </label>
                        @endforeach
                    </div>
                    {{-- Fase 4: guardar estos mismos números como plato
                         habitual — reusa los inputs de arriba, el botón
                         solo cambia a dónde apunta el POST (formaction). --}}
                    <label class="campo" style="margin-top:var(--e-3)">
                        <span class="campo__etiqueta" style="font-size:.6rem">Nombre para guardar como plato (opcional)</span>
                        <input class="campo__control" type="text" name="nombre_plato" maxlength="60"
                               placeholder="Ej. Mi almuerzo de siempre" style="padding:.5rem;font-size:var(--t-xs)">
                    </label>

                    <div class="formulario-panel__acciones" style="margin-top:var(--e-3);padding-top:0;border-top:none">
                        <button class="btn btn--vidrio" type="submit" formaction="{{ route('cliente.platos.guardar') }}"
                                title="Guarda estos números como plato habitual, sin registrar la comida de hoy">Guardar como plato</button>
                        <button class="btn btn--fuego" type="submit">{{ $registro ? 'Actualizar' : 'Guardar' }}</button>
                    </div>
                </form>
            @endforeach
        </div>
    </article>

    {{-- Fase 4 del plan de nutrición: "Mis platos habituales" — registrar
         con un tap en vez de escribir los cuatro números de nuevo. --}}
    <article class="tarjeta" data-revelar>
        <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-2)">Mis platos habituales</h3>
        <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-4)">
            Los platos que guardaste desde "Hoy comiste". Un tap los registra como tu comida de hoy.
        </p>

        @forelse ($platosHabituales as $plato)
            @php
                $resumenPlato = collect($plato->conteo)->filter()->map(fn ($n, $k) => "{$n} {$formasCortas[$k]}" . ($n === 1 ? '' : 's'))->implode(' · ');
            @endphp
            <div style="display:flex;justify-content:space-between;align-items:center;gap:var(--e-3);padding:var(--e-3) 0;border-bottom:1px solid var(--borde-suave)">
                <div style="min-width:0">
                    <b style="display:block;color:var(--hueso)">{{ $plato->name }} <span style="color:var(--ceniza);font-weight:normal;font-size:var(--t-xs)">· {{ $plato->meal_type_legible }}</span></b>
                    <span style="color:var(--ceniza);font-size:var(--t-xs);font-family:var(--f-mono)">{{ $resumenPlato ?: '—' }}</span>
                </div>
                <div style="display:flex;gap:var(--e-2);flex:none">
                    <form method="POST" action="{{ route('cliente.platos.usar', $plato) }}">
                        @csrf
                        <button class="btn btn--vidrio" type="submit">Usar hoy</button>
                    </form>
                    <form method="POST" action="{{ route('cliente.platos.destroy', $plato) }}">
                        @csrf @method('DELETE')
                        <button class="btn btn--desnudo" type="submit" aria-label="Eliminar plato"><x-icono nombre="papelera" /></button>
                    </form>
                </div>
            </div>
        @empty
            <p style="color:var(--humo);font-size:var(--t-sm)">Todavía no guardaste ningún plato. Arriba, en "Hoy comiste", puedes guardar los números de una comida como plato.</p>
        @endforelse
    </article>

    <div class="g-1-1">
        <article class="tarjeta grafico">
            <h3 style="font-size:var(--t-lg)">Peso</h3>
            <div class="grafico__lienzo">
                <canvas data-grafico="{{ json_encode(['tipo' => 'line', 'labels' => $graficoPeso['labels'], 'label' => 'Peso (kg)', 'data' => $graficoPeso['data']]) }}"></canvas>
            </div>
        </article>
        <article class="tarjeta grafico">
            <h3 style="font-size:var(--t-lg)">% Grasa corporal</h3>
            <div class="grafico__lienzo">
                <canvas data-grafico="{{ json_encode(['tipo' => 'line', 'labels' => $graficoGrasa['labels'], 'label' => '% Grasa', 'data' => $graficoGrasa['data']]) }}"></canvas>
            </div>
        </article>
    </div>

    <div class="tabla-envoltorio">
        <table class="tabla tabla--tarjetas">
            <thead><tr><th>Fecha</th><th>Peso</th><th>% Grasa</th><th>IMC</th><th>Nota</th></tr></thead>
            <tbody>
                @forelse ($socio->measurements->sortByDesc('measured_at') as $m)
                    <tr>
                        <td data-etiqueta="Fecha">{{ $m->measured_at->translatedFormat('d M Y') }}</td>
                        <td class="es-fuerte" data-etiqueta="Peso">{{ $m->weight_kg }} kg</td>
                        <td data-etiqueta="% Grasa">{{ $m->body_fat_pct ? $m->body_fat_pct . '%' : '—' }}</td>
                        <td data-etiqueta="IMC">{{ $m->bmi ?? '—' }}</td>
                        <td class="tabla__nota" data-etiqueta="Nota">{{ $m->notes ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="tabla__vacio" data-etiqueta="">Todavía no tienes medidas registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
