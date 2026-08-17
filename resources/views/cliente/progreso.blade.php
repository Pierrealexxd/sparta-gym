@extends('layouts.panel')

@section('titulo', 'Mi progreso')

@section('contenido')
    {{-- Fase 4 (PLAN-RUTINAS-PERSONALIZADAS.md): recordatorio si hoy
         todavía no se registró peso — enlaza directo al formulario de abajo. --}}
    @unless ($hoy)
        <div class="recordatorio">
            <x-icono nombre="reloj" />
            <span>¿Ya pesaste hoy? <b>Registra tu peso</b> para no perder la racha.</span>
            <a class="btn btn--vidrio" href="#registrar-hoy">Registrar ahora</a>
        </div>
    @endunless

    <div class="kpis">
        <article class="tarjeta kpi">
            <b class="kpi__valor">{{ $ultima?->weight_kg ?? '—' }} kg</b>
            <span class="kpi__etiqueta">Peso actual</span>
            @if ($primera && $ultima && $primera->id !== $ultima->id)
                @php $dif = round($ultima->weight_kg - $primera->weight_kg, 1); @endphp
                <span class="kpi__variacion {{ $dif <= 0 ? 'kpi__variacion--pos' : 'kpi__variacion--neg' }}">
                    {{ $dif <= 0 ? '↓' : '↑' }} {{ $dif > 0 ? '+' : '' }}{{ $dif }} kg desde el inicio
                </span>
            @endif
        </article>

        {{-- IMC como aro de progreso (Fase 4): mismo dato de siempre,
             leído de un vistazo en vez de sólo el número. 40 como tope del
             aro porque es el límite convencional de obesidad tipo III. --}}
        <article class="tarjeta kpi" style="justify-items:center;text-align:center">
            <div class="progreso-kpi__circulo" style="--progreso: {{ $ultima?->bmi ? min(100, round($ultima->bmi / 40 * 100)) : 0 }}%">
                <span>{{ $ultima?->bmi ?? '—' }}</span>
            </div>
            <span class="kpi__etiqueta">IMC</span>
            @if ($ultima?->bmi_category)
                @php
                    $claseIcm = match ($ultima->bmi_category) {
                        'Normal' => 'estado--activo',
                        'Bajo peso', 'Sobrepeso' => 'estado--pendiente',
                        default => 'estado--suspendido',
                    };
                @endphp
                <span class="estado {{ $claseIcm }}">{{ $ultima->bmi_category }}</span>
            @elseif ($ultima && ! $ultima->altura)
                {{-- Causa raíz (PROMPT-EJECUCION-MI-RUTINA.md, Parte 2): el
                     wizard de matrícula no pide altura, así que el IMC
                     queda mudo. En vez del aro al 0% sin explicación, se
                     dice qué falta y dónde se arregla. --}}
                <p class="aviso" style="font-size:var(--t-xs);text-align:left;padding:var(--e-2) var(--e-3);margin-top:var(--e-1)">
                    Falta tu altura para calcular el IMC — agrégala en <a href="{{ route('perfil') }}" style="color:var(--brasa)">Mi perfil</a>.
                </p>
            @endif
        </article>

        <article class="tarjeta kpi">
            <b class="kpi__valor">{{ $ultima?->body_fat_pct ?? '—' }}%</b>
            <span class="kpi__etiqueta">Grasa corporal</span>
            @if ($ultima?->body_fat_pct && $primera?->body_fat_pct && $primera->id !== $ultima->id)
                @php $difGrasa = round($ultima->body_fat_pct - $primera->body_fat_pct, 1); @endphp
                <span class="kpi__variacion {{ $difGrasa <= 0 ? 'kpi__variacion--pos' : 'kpi__variacion--neg' }}">
                    {{ $difGrasa <= 0 ? '↓' : '↑' }} {{ $difGrasa > 0 ? '+' : '' }}{{ $difGrasa }} pt desde el inicio
                </span>
            @endif
        </article>

        @if ($primera)
            <article class="tarjeta kpi">
                {{-- (int) es necesario: esta versión de Carbon (3.x) devuelve
                     float en diffInDays() — sin el cast se pintaba
                     "301.91316757009" en vez de "301" (QA post-implementación). --}}
                <b class="kpi__valor">{{ max(0, (int) $primera->measured_at->diffInDays(now())) }}</b>
                <span class="kpi__etiqueta">Días registrando</span>
                <span class="kpi__variacion kpi__variacion--pos">→ constancia</span>
            </article>
        @endif

        {{-- "¿Hace cuánto no me peso?" — un socio que no mide, no progresa
             (PROMPT-EJECUCION-MI-RUTINA.md, Parte 3). Distinto de "Días
             registrando": ese es la antigüedad, este es la última vez. --}}
        @if ($ultima)
            @php
                // Mismo cast que arriba: sin (int), $diasSinPesarse es float
                // y las comparaciones "=== 0" / "=== 1" de abajo (int) nunca
                // matchean contra un float, así que "Hoy"/"Ayer" nunca se
                // mostraban aunque el socio se acabara de pesar.
                $diasSinPesarse = max(0, (int) $ultima->measured_at->diffInDays(now()));
                $estadoConstancia = match (true) {
                    $diasSinPesarse <= 3  => 'estado--activo',
                    $diasSinPesarse <= 7  => 'estado--pendiente',
                    default                => 'estado--suspendido',
                };
                $textoConstancia = match (true) {
                    $diasSinPesarse === 0 => 'Hoy',
                    $diasSinPesarse === 1 => 'Ayer',
                    default                => "Hace {$diasSinPesarse} días",
                };
            @endphp
            <article class="tarjeta kpi">
                <b class="kpi__valor">{{ $textoConstancia }}</b>
                <span class="kpi__etiqueta">Última vez que te pesaste</span>
                <span class="estado {{ $estadoConstancia }}">
                    {{ match (true) {
                        $diasSinPesarse <= 3 => 'Al día',
                        $diasSinPesarse <= 7 => 'Toca pesarte',
                        default => 'Hace tiempo',
                    } }}
                </span>
            </article>
        @endif
    </div>

    <div class="g-2-1">
        <article class="tarjeta" id="registrar-hoy">
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
                    $meta = $item['meta']; $progreso = $item['progreso']; $restante = $item['restante'];
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
                    @if ($restante !== null)
                        {{-- Unidades reales, no sólo %: "cuánto me falta" es la
                             pregunta que el disco de arriba no contesta solo. --}}
                        <p style="font-size:var(--t-xs);margin-top:var(--e-2)">
                            @if ($restante > 0)
                                {{-- Neutro, no rojo: que falte camino no es una
                                     alarma, es sólo información de distancia. --}}
                                <span style="color:var(--ceniza)">Te faltan {{ $restante }} kg para tu meta.</span>
                            @else
                                <span style="color:var(--ok)">¡Meta alcanzada!{{ $restante < 0 ? ' Superada por ' . abs($restante) . ' kg.' : '' }}</span>
                            @endif
                        </p>
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

    {{-- Rediseño de progreso (PROMPT-EJECUCION-MI-RUTINA.md, Parte 1): la
         rutina completa se quitó de acá — Progreso es medición (peso, IMC,
         grasa, metas, historial), la rutina vive en su propio módulo
         canónico. Sólo queda este enlace discreto para quien la busque
         aquí por costumbre. --}}
    <p style="color:var(--humo);font-size:var(--t-sm);display:flex;align-items:center;gap:var(--e-2)">
        <x-icono nombre="pesa" style="width:1em;height:1em" />
        ¿Buscas tu rutina? Ahora vive en <a href="{{ route('cliente.rutina') }}" style="color:var(--brasa)">Mi rutina</a>.
    </p>

    {{-- Fase 4: un solo gráfico con peso y grasa en dos ejes en vez de dos
         tarjetas sueltas — así se ve si de verdad se correlacionan (ver
         graficos.js, soporte de "eje: 'y1'" añadido para esto). --}}
    <article class="tarjeta grafico">
        <h3 style="font-size:var(--t-lg)">Peso y grasa corporal</h3>
        <div class="grafico__lienzo">
            <canvas data-grafico="{{ json_encode($graficoCombinado) }}"></canvas>
        </div>
    </article>

    <div class="tabla-envoltorio">
        <table class="tabla tabla--tarjetas">
            <thead><tr><th>Fecha</th><th>Peso</th><th>% Grasa</th><th>IMC</th><th>Nota</th></tr></thead>
            <tbody>
                @forelse ($medidasPag as $m)
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

    <div class="paginacion">{{ $medidasPag->links() }}</div>
@endsection
