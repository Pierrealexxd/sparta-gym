@extends('layouts.panel')

@section('titulo', 'Mi panel')
@section('subtitulo', 'Hola, ' . explode(' ', $socio->full_name)[0])

@section('contenido')
    <div class="kpis" data-revelar data-revelar-grupo>
        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="reloj" /></span>
            <b class="kpi__valor"><span data-contador="{{ $kpis['diasRestantes'] ?? 0 }}">0</span></b>
            <span class="kpi__etiqueta">Días de membresía</span>
        </article>
        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="entrada" /></span>
            <b class="kpi__valor"><span data-contador="{{ $kpis['asistenciasMes'] }}">0</span></b>
            <span class="kpi__etiqueta">Visitas este mes</span>
        </article>
        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="lista" /></span>
            <b class="kpi__valor"><span data-contador="{{ $kpis['rutinasActivas'] }}">0</span></b>
            <span class="kpi__etiqueta">Rutinas activas</span>
        </article>
        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="grafico" /></span>
            <b class="kpi__valor"><span data-contador="{{ $kpis['objetivosPendientes'] }}">0</span></b>
            <span class="kpi__etiqueta">Objetivos activos</span>
        </article>
    </div>

    <div class="g-2-1">
        <article class="tarjeta">
            <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-4)">Mi membresía</h3>
            @if ($socio->currentMembership)
                <div class="ficha__dato"><span>Plan</span><span>{{ $socio->currentMembership->plan_name }}</span></div>
                <div class="ficha__dato"><span>Vence</span><span>{{ $socio->currentMembership->ends_at->translatedFormat('d \d\e F, Y') }}</span></div>
                <div class="ficha__dato"><span>Estado</span><span class="estado estado--{{ $socio->currentMembership->status }}">{{ $socio->days_left }} días restantes</span></div>
            @else
                <p style="color:var(--humo)">No tienes una membresía activa. Pásate por recepción.</p>
            @endif
            <div class="ficha__dato"><span>Entrenador</span><span>{{ $socio->currentAssignment?->trainer?->nombre ?? 'Sin asignar' }}</span></div>
        </article>

        <article class="tarjeta">
            <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-4)">Mis objetivos</h3>
            @forelse ($socio->goals as $meta)
                <div class="ficha__dato"><span>{{ $meta->title }}</span><span>{{ $meta->target_value ? $meta->target_value . ' ' . $meta->unit : '' }}</span></div>
            @empty
                <p style="color:var(--humo);font-size:var(--t-sm)">Tu entrenador aún no definió objetivos.</p>
            @endforelse
        </article>
    </div>

    <article class="tarjeta">
        <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-4)">Mi rutina</h3>
        @forelse ($socio->routines as $rutina)
            <h4 style="font-size:var(--t-base);margin-bottom:var(--e-3)">{{ $rutina->name }}</h4>
            @foreach ($rutina->days as $dia)
                <div style="margin-bottom:var(--e-4)">
                    <p style="font-family:var(--f-mono);font-size:var(--t-xs);letter-spacing:.1em;text-transform:uppercase;color:var(--brasa);margin-bottom:var(--e-2)">
                        {{ $dia->name }} @if($dia->focus) · {{ $dia->focus }} @endif
                    </p>
                    <div class="tabla-envoltorio">
                        <table class="tabla tabla--tarjetas">
                            <tbody>
                                @foreach ($dia->exercises as $re)
                                    <tr><td class="es-fuerte" data-etiqueta="Ejercicio">{{ $re->exercise->name }}</td><td data-etiqueta="Prescripción">{{ $re->prescripcion }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        @empty
            <p style="color:var(--humo)">Todavía no tienes una rutina asignada.</p>
        @endforelse
    </article>

    <div class="g-1-1">
        <article class="tarjeta">
            <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-4)">Últimas ventas</h3>
            <div class="tabla-envoltorio">
                <table class="tabla tabla--tarjetas">
                    <tbody>
                        @forelse ($socio->sales as $pago)
                            <tr><td data-etiqueta="Fecha">{{ $pago->sold_at->format('d/m/y') }}</td><td class="es-fuerte" data-etiqueta="Concepto">{{ $pago->concept ?? ucfirst($pago->sale_type) }}</td><td data-etiqueta="Monto">S/ {{ number_format($pago->total, 2) }}</td></tr>
                        @empty
                            <tr><td class="tabla__vacio" data-etiqueta="">Sin ventas todavía.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="tarjeta">
            <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-4)">Mi asistencia reciente</h3>
            <div class="tabla-envoltorio">
                <table class="tabla tabla--tarjetas">
                    <tbody>
                        @forelse ($socio->attendances as $a)
                            <tr><td data-etiqueta="Fecha">{{ $a->checked_in_at->translatedFormat('d M') }}</td><td class="es-fuerte" data-etiqueta="Entrada">{{ $a->checked_in_at->format('H:i') }}</td><td data-etiqueta="Salida">{{ $a->checked_out_at?->format('H:i') ?? 'En curso' }}</td></tr>
                        @empty
                            <tr><td class="tabla__vacio" data-etiqueta="">Sin visitas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </div>

    <article class="tarjeta">
        <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-4)">Tu reseña</h3>
        @if ($socio->testimonial)
            <p style="color:var(--ceniza)">
                @if ($socio->testimonial->is_published)
                    Tu reseña ya está publicada en la web. ¡Gracias!
                @else
                    Enviaste tu reseña, está a la espera de aprobación.
                @endif
            </p>
            <blockquote style="margin-top:var(--e-3);color:var(--humo);font-style:italic">
                “{{ $socio->testimonial->content }}” — {{ str_repeat('★', $socio->testimonial->rating) }}
            </blockquote>
        @else
            <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-4)">
                Cuenta tu experiencia en Sparta Gym. Tras enviarla, el admin la revisa antes de publicarla en la web.
            </p>
            <form class="formulario-panel" method="POST" action="{{ route('cliente.resena.store') }}">
                @csrf
                <label class="campo"><span class="campo__etiqueta">Tu reseña</span>
                    <textarea class="campo__control" name="content" required maxlength="1000" style="min-height:6rem">{{ old('content') }}</textarea></label>
                <label class="campo"><span class="campo__etiqueta">Calificación</span>
                    <select class="campo__control" name="rating" required>
                        @for ($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}" @selected(old('rating', 5) == $i)>{{ str_repeat('★', $i) }}</option>
                        @endfor
                    </select></label>
                <div class="formulario-panel__acciones">
                    <button class="btn btn--fuego" type="submit">Enviar reseña</button>
                </div>
            </form>
        @endif
    </article>
@endsection
