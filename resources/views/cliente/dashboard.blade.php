@extends('layouts.panel')

@section('titulo', 'Mi panel')
@section('subtitulo', 'Hola, ' . explode(' ', $socio->full_name)[0])

@section('contenido')
    {{-- Rediseño del dashboard (PLAN-DASHBOARD-CLIENTE.md): 4 KPIs con
         tendencia, 4 gráficas que responden "¿cuán constante soy? ¿qué días
         entreno? ¿cómo va mi cuerpo? ¿cuánto he invertido?", rutina en
         cards colapsables y metas con disco de progreso — reemplaza las
         tablas de texto plano de antes. --}}
    <div class="kpis" data-revelar data-revelar-grupo>
        <article class="tarjeta kpi tarjeta--interactiva" style="justify-items:center;text-align:center">
            <div class="progreso-kpi__circulo" style="--progreso: {{ $kpis['diasRestantesPct'] }}%">
                <span>{{ $kpis['diasRestantes'] ?? '—' }}</span>
            </div>
            <span class="kpi__etiqueta">Días de membresía</span>
        </article>

        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="entrada" /></span>
            <b class="kpi__valor"><span data-contador="{{ $kpis['asistenciasMes'] }}">0</span></b>
            <span class="kpi__etiqueta">Visitas este mes</span>
            @if ($kpis['deltaAsistencia'] !== null)
                <span class="kpi__variacion {{ $kpis['deltaAsistencia'] >= 0 ? 'kpi__variacion--pos' : 'kpi__variacion--neg' }}">
                    {{ $kpis['deltaAsistencia'] >= 0 ? '↑' : '↓' }} {{ abs($kpis['deltaAsistencia']) }}% vs mes anterior
                </span>
            @endif
        </article>

        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="llama" /></span>
            <b class="kpi__valor"><span data-contador="{{ $kpis['racha'] }}">0</span></b>
            <span class="kpi__etiqueta">{{ $kpis['racha'] === 1 ? 'Semana seguida' : 'Semanas seguidas' }}</span>
        </article>

        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="balanza" /></span>
            <b class="kpi__valor">{{ $kpis['pesoActual'] ?? '—' }} {{ $kpis['pesoActual'] ? 'kg' : '' }}</b>
            <span class="kpi__etiqueta">Peso actual</span>
            @if ($kpis['deltaPeso'] !== null)
                <span class="kpi__variacion {{ $kpis['deltaPeso'] <= 0 ? 'kpi__variacion--pos' : 'kpi__variacion--neg' }}">
                    {{ $kpis['deltaPeso'] <= 0 ? '↓' : '↑' }} {{ $kpis['deltaPeso'] > 0 ? '+' : '' }}{{ $kpis['deltaPeso'] }} kg desde el inicio
                </span>
            @endif
        </article>
    </div>

    <div class="g-1-1" data-revelar data-revelar-grupo>
        <article class="tarjeta grafico">
            <div class="grafico__cabecera">
                <h3 style="font-size:var(--t-lg)">Asistencia semanal</h3>
            </div>
            @if ($hayAsistenciaSemanal)
                <div class="grafico__lienzo">
                    <canvas data-grafico="{{ json_encode($graficoAsistencia) }}"></canvas>
                </div>
            @else
                <x-estado-vacio icono="entrada" texto="Aún no tienes visitas registradas." />
            @endif
        </article>

        <article class="tarjeta grafico">
            <div class="grafico__cabecera">
                <h3 style="font-size:var(--t-lg)">Tu progreso corporal</h3>
            </div>
            @if ($hayMedidas)
                <div class="grafico__lienzo">
                    <canvas data-grafico="{{ json_encode($graficoProgreso) }}"></canvas>
                </div>
            @else
                <x-estado-vacio icono="balanza" texto="Registra tu peso en Progreso para ver tu curva." />
                <a href="{{ route('cliente.progreso') }}" class="btn btn--vidrio btn--sm" style="justify-self:center">Registrar peso</a>
            @endif
        </article>
    </div>

    <div class="g-1-1" data-revelar data-revelar-grupo>
        <article class="tarjeta grafico">
            <div class="grafico__cabecera">
                <h3 style="font-size:var(--t-lg)">Tus días de entrenamiento</h3>
            </div>
            @if ($hayFrecuenciaDias)
                <div class="grafico__lienzo">
                    <canvas data-grafico="{{ json_encode($graficoFrecuencia) }}"></canvas>
                </div>
                @if ($diaMaxFrecuencia)
                    <p style="color:var(--ceniza);font-size:var(--t-xs);text-align:center">{{ $diaMaxFrecuencia }} es tu día favorito</p>
                @endif
            @else
                <x-estado-vacio icono="reloj" texto="Aún no tienes suficientes visitas para mostrar el patrón." />
            @endif
        </article>

        <article class="tarjeta grafico">
            <div class="grafico__cabecera">
                <h3 style="font-size:var(--t-lg)">Tu inversión en salud</h3>
            </div>
            @if ($hayInversion)
                <div class="grafico__lienzo">
                    <canvas data-grafico="{{ json_encode($graficoInversion) }}"></canvas>
                </div>
            @else
                <x-estado-vacio icono="billetera" texto="Sin registros de pago aún." />
            @endif
        </article>
    </div>

    <article class="tarjeta" data-revelar>
        <div class="grafico__cabecera" style="margin-bottom:var(--e-4)">
            <h3 style="font-size:var(--t-lg)">
                Mi rutina
                @if ($rutinaActiva?->program)
                    <span style="color:var(--humo);font-weight:400"> — {{ $rutinaActiva->program->name }}</span>
                @endif
            </h3>
            {{-- Este bloque es sólo el preview: el detalle completo (con
                 series, reps, peso, descanso y notas) vive en el módulo
                 canónico — ver PROMPT-EJECUCION-MI-RUTINA.md. --}}
            @if ($rutinaActiva)
                <a href="{{ route('cliente.rutina') }}" class="btn btn--vidrio btn--sm">Ver rutina completa</a>
            @endif
        </div>

        @if ($rutinaActiva)
            <div class="rutina-dias">
                @foreach ($rutinaActiva->days as $dia)
                    <div class="rutina-dia" x-data="{ abierta: {{ $loop->first ? 'true' : 'false' }} }">
                        <button type="button" class="rutina-dia__cabecera" @click="abierta = !abierta" :aria-expanded="abierta.toString()">
                            <span class="rutina-dia__titulo">{{ $dia->name }} @if ($dia->focus) <span class="rutina-dia__foco">· {{ $dia->focus }}</span> @endif</span>
                            <span class="rutina-dia__flecha" :style="'transform:rotate(' + (abierta ? '180deg' : '0deg') + ')'">
                                <x-icono nombre="flecha-der" style="width:1.1em;height:1.1em;transform:rotate(90deg)" />
                            </span>
                        </button>
                        <div class="rutina-dia__cuerpo" x-show="abierta" x-cloak>
                            <div class="tabla-envoltorio">
                                <table class="tabla tabla--tarjetas">
                                    <thead><tr><th>Ejercicio</th><th>Prescripción</th></tr></thead>
                                    <tbody>
                                        @forelse ($dia->exercises as $re)
                                            <tr>
                                                <td class="es-fuerte" data-etiqueta="Ejercicio">{{ $re->exercise->name }}</td>
                                                <td data-etiqueta="Prescripción">{{ $re->prescripcion }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="2" class="tabla__vacio" data-etiqueta="">Sin ejercicios en este día.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <x-estado-vacio icono="lista" texto="Todavía no tienes una rutina. Selecciona un programa en la landing o pásate por recepción." />
        @endif
    </article>

    <article class="tarjeta" data-revelar>
        <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-4)">Mis metas</h3>
        @forelse ($metas as $item)
            @php $meta = $item['meta']; $progreso = $item['progreso']; @endphp
            <div class="meta-tarjeta">
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
            </div>
        @empty
            <x-estado-vacio icono="objetivo" texto="Tu entrenador aún no definió objetivos." />
        @endforelse
    </article>

    <article class="tarjeta">
        <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-4)">Últimas visitas</h3>
        <div class="tabla-envoltorio">
            <table class="tabla tabla--tarjetas">
                <thead><tr><th>Fecha</th><th>Entrada</th><th>Salida</th></tr></thead>
                <tbody>
                    @forelse ($socio->attendances as $a)
                        <tr>
                            <td data-etiqueta="Fecha">{{ $a->checked_in_at->translatedFormat('d M') }}</td>
                            <td class="es-fuerte" data-etiqueta="Entrada">{{ $a->checked_in_at->format('H:i') }}</td>
                            <td data-etiqueta="Salida">{{ $a->checked_out_at?->format('H:i') ?? 'En curso' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="tabla__vacio" data-etiqueta=""><x-estado-vacio icono="entrada" texto="Sin visitas registradas." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>

    {{-- Fase 5 (PLAN-RUTINAS-PERSONALIZADAS.md): tres estados claros —
         publicada, en revisión, o el formulario para escribirla. No hay
         edición todavía: TestimonialController@store no tiene ruta de
         actualización (una reseña por socio, ver el propio controlador),
         así que agregar "editar" aquí sería prometer algo que el backend
         no cumple — se deja fuera a propósito. --}}
    <article class="tarjeta">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:var(--e-3);margin-bottom:var(--e-4)">
            <h3 style="font-size:var(--t-lg)">Tu reseña</h3>
            @if ($socio->testimonial)
                <span class="estado {{ $socio->testimonial->is_published ? 'estado--activo' : 'estado--pendiente' }}">
                    {{ $socio->testimonial->is_published ? 'Publicada' : 'En revisión' }}
                </span>
            @endif
        </div>

        @if ($socio->testimonial)
            <div class="testimonio__estrellas" aria-label="{{ $socio->testimonial->rating }} de 5" style="margin-bottom:var(--e-3)">
                @for ($i = 0; $i < $socio->testimonial->rating; $i++)
                    <x-icono nombre="estrella" />
                @endfor
            </div>
            <blockquote style="color:var(--ceniza);font-style:italic">“{{ $socio->testimonial->content }}”</blockquote>
            @unless ($socio->testimonial->is_published)
                <p style="color:var(--humo);font-size:var(--t-xs);margin-top:var(--e-3)">El equipo la revisa antes de publicarla en la web.</p>
            @endunless
        @else
            <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-4)">
                Cuenta tu experiencia en Sparta Gym. Tras enviarla, el admin la revisa antes de publicarla en la web.
            </p>
            <form class="formulario-panel" method="POST" action="{{ route('cliente.resena.store') }}" x-data="{ rating: {{ old('rating', 5) }} }">
                @csrf
                <label class="campo"><span class="campo__etiqueta">Tu reseña</span>
                    <textarea class="campo__control" name="content" required maxlength="1000" style="min-height:6rem" placeholder="¿Qué cambió desde que entrenas en Sparta?">{{ old('content') }}</textarea></label>

                <div class="campo">
                    <span class="campo__etiqueta">Calificación</span>
                    <div class="testimonio__estrellas" style="cursor:pointer" role="radiogroup" aria-label="Calificación de 1 a 5 estrellas">
                        <template x-for="i in 5" :key="i">
                            <button type="button" @click="rating = i" :aria-label="i + ' estrellas'" :aria-pressed="rating === i"
                                    style="background:none;border:0;padding:0;line-height:0;color:inherit">
                                <span :style="'display:inline-flex;opacity:' + (i <= rating ? 1 : .3)">
                                    <x-icono nombre="estrella" />
                                </span>
                            </button>
                        </template>
                    </div>
                    <input type="hidden" name="rating" :value="rating">
                </div>

                <div class="formulario-panel__acciones">
                    <button class="btn btn--fuego" type="submit">Enviar reseña</button>
                </div>
                <p style="color:var(--humo);font-size:var(--t-xs)">Tu reseña será publicada tras revisión por el equipo.</p>
            </form>
        @endif
    </article>
@endsection
