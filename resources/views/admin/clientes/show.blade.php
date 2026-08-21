@extends('layouts.panel')

@section('titulo', $cliente->full_name)
@section('subtitulo', $cliente->code . ' · Cliente desde ' . $cliente->joined_at->translatedFormat('F Y'))

@section('acciones')
    <div style="display:flex;gap:var(--e-3)">
        <a class="btn btn--vidrio" href="{{ route('admin.clientes.edit', $cliente) }}"><x-icono nombre="lapiz" /> Editar</a>
        @if (auth()->user()->tienePermiso('clientes.eliminar'))
            <button class="btn btn--vidrio" type="button"
                    @click="$store.confirmar.abrir({
                        accion: '{{ route('admin.clientes.destroy', $cliente) }}',
                        titulo: 'Eliminar cliente',
                        mensaje: '¿Eliminar a {{ $cliente->full_name }}? Su ficha, pagos y asistencias saldrán del listado.',
                        etiqueta: 'Eliminar'
                    })">
                <x-icono nombre="papelera" />
            </button>
        @endif
    </div>
@endsection

@section('contenido')
    {{-- $tabActiva viene del controlador ya validado contra una lista blanca
         de pestañas reales — nunca se interpola request('tab') crudo acá:
         x-data es una cadena que Alpine evalúa como JS, así que un valor
         con comillas sin sanear sería inyección. --}}
    {{-- Membresía vigente: se calcula una sola vez acá porque la usan tanto
         Resumen (línea de tiempo) como Membresías (recordatorio WhatsApp). --}}
    @php
        $membresiaActual = $cliente->currentMembership;
        $diasRestantes = $membresiaActual?->dias_restantes;
        $umbralWhatsApp = config('sparta.aviso_vencimiento_dias', 7);
        $mostrarWhatsApp = $membresiaActual && $diasRestantes !== null && $diasRestantes <= $umbralWhatsApp;
        $etiquetasEstadoMem = ['activa' => 'Activa', 'por-vencer' => 'Por vencer', 'vencida' => 'Vencida', 'cancelada' => 'Cancelada', 'congelada' => 'Congelada'];
    @endphp

    <div class="ficha" x-data="{ tab: '{{ $tabActiva }}' }">
        <div class="ficha__resumen">
            <div class="tarjeta" style="padding:var(--e-5)">
                <div class="ficha__foto" style="margin-bottom:var(--e-4)">
                    @if ($cliente->photo_path)
                        <img src="{{ asset('storage/' . $cliente->photo_path) }}" alt="{{ $cliente->full_name }}">
                    @else
                        <span class="ficha__iniciales">{{ $cliente->initials }}</span>
                    @endif
                </div>

                <div class="ficha__dato"><span>Estado</span><span class="estado estado--{{ $cliente->status }}">{{ ucfirst($cliente->status) }}</span></div>
                <div class="ficha__dato"><span>Edad</span><span>{{ $cliente->age ?? '—' }}</span></div>
                <div class="ficha__dato"><span>Teléfono</span><span>{{ $cliente->phone ?? '—' }}</span></div>
                <div class="ficha__dato"><span>Documento</span><span>{{ $cliente->document ?? '—' }}</span></div>
                <div class="ficha__dato"><span>Altura</span><span>{{ $cliente->height_cm ? $cliente->height_cm . ' cm' : '—' }}</span></div>
                <div class="ficha__dato">
                    <span>Membresía</span>
                    <span>
                        @if ($cliente->currentMembership)
                            <span class="estado estado--{{ $cliente->currentMembership->status }}">{{ $cliente->days_left }} días</span>
                        @else Sin membresía @endif
                    </span>
                </div>
                <div class="ficha__dato"><span>Entrenador</span><span>{{ $cliente->currentAssignment?->trainer?->nombre ?? '—' }}</span></div>
            </div>

            @if ($cliente->qr_token)
                <div class="tarjeta" style="padding:var(--e-5);text-align:center">
                    <canvas data-qr="{{ $cliente->qr_token }}" style="max-width:100%;height:auto;border-radius:var(--r-2)"></canvas>
                    <p style="font-family:var(--f-mono);font-size:var(--t-xs);color:var(--humo);word-break:break-all;margin-top:var(--e-3)">
                        {{ $cliente->qr_token }}
                    </p>
                    <a class="btn btn--vidrio" style="margin-top:var(--e-3)" target="_blank"
                       href="{{ route('admin.clientes.carnet', $cliente) }}">
                        <x-icono nombre="descargar" /> Imprimir carnet
                    </a>
                </div>
            @endif
        </div>

        <div>
            <nav class="pestanas__nav pestanas__nav--ancha" style="margin-bottom:var(--e-5)">
                <button class="pestanas__enlace" :aria-current="tab==='resumen'" @click="tab='resumen'" type="button">Resumen</button>
                <button class="pestanas__enlace" :aria-current="tab==='medidas'" @click="tab='medidas'" type="button">Medidas</button>
                <button class="pestanas__enlace" :aria-current="tab==='membresias'" @click="tab='membresias'" type="button">Membresías</button>
                <button class="pestanas__enlace" :aria-current="tab==='pagos'" @click="tab='pagos'" type="button">Pagos</button>
                <button class="pestanas__enlace" :aria-current="tab==='asistencia'" @click="tab='asistencia'" type="button">Asistencia</button>
            </nav>

            <div x-show="tab==='resumen'" class="pestana-panel">
                {{-- Línea de tiempo de la membresía vigente: cuenta regresiva
                     + riel de periodo, mismo componente que la tarjeta de
                     Membresías (--progreso = % transcurrido). Sin membresía
                     vigente, queda el estado vacío con su mensaje. --}}
                <article class="tarjeta membresia-resumen">
                    <h3 style="font-size:var(--t-lg)">Membresía vigente</h3>
                    @if ($membresiaActual)
                        <div class="membresia-resumen__cuerpo">
                            <div class="progreso-kpi__circulo membresia__circulo" style="--progreso: {{ $membresiaActual->porcentaje_transcurrido }}%">
                                <span>{{ $membresiaActual->dias_restantes }}</span>
                            </div>
                            <div class="membresia-resumen__datos">
                                <div class="membresia__cabecera">
                                    <h4 class="membresia__plan">{{ $membresiaActual->plan_name }}</h4>
                                    <span class="estado estado--{{ $membresiaActual->estado_visual }}">{{ $etiquetasEstadoMem[$membresiaActual->estado_visual] ?? ucfirst($membresiaActual->estado_visual) }}</span>
                                </div>
                                <div class="membresia-rail" data-estado="{{ $membresiaActual->estado_visual }}" style="--progreso: {{ $membresiaActual->porcentaje_transcurrido }}%">
                                    <span class="membresia-rail__relleno"></span>
                                    <span class="membresia-rail__hoy"></span>
                                </div>
                                <div class="membresia__fechas">
                                    <span>{{ $membresiaActual->starts_at->format('d/m/y') }}</span>
                                    <span>{{ $membresiaActual->ends_at->format('d/m/y') }}</span>
                                </div>
                            </div>
                        </div>
                    @else
                        <x-estado-vacio icono="tarjetas" texto="Sin membresía vigente." />
                    @endif
                </article>

                {{-- Resumen del progreso físico: mismas tarjetas que el
                     cliente ve en Mi progreso (cliente/progreso.blade.php),
                     para que el admin tenga el mismo vistazo rápido sin
                     tener que interpretar el gráfico de abajo. $primera/
                     $ultima salen de $cliente->measurements, ya cargada
                     ordenada por fecha (ver MemberController::show). --}}
                @php
                    $primeraMedida = $cliente->measurements->first();
                    $ultimaMedida  = $cliente->measurements->last();
                @endphp
                @if ($ultimaMedida)
                    <div class="kpis kpis--3">
                        <article class="tarjeta kpi">
                            <b class="kpi__valor">{{ $ultimaMedida->weight_kg }} kg</b>
                            <span class="kpi__etiqueta">Peso actual</span>
                            @if ($primeraMedida && $primeraMedida->id !== $ultimaMedida->id)
                                @php $difPeso = round($ultimaMedida->weight_kg - $primeraMedida->weight_kg, 1); @endphp
                                <span class="kpi__variacion {{ $difPeso <= 0 ? 'kpi__variacion--pos' : 'kpi__variacion--neg' }}">
                                    {{ $difPeso <= 0 ? '↓' : '↑' }} {{ $difPeso > 0 ? '+' : '' }}{{ $difPeso }} kg desde el inicio
                                </span>
                            @endif
                        </article>

                        <article class="tarjeta kpi" style="justify-items:center;text-align:center">
                            <div class="progreso-kpi__circulo" style="--progreso: {{ $ultimaMedida->bmi ? min(100, round($ultimaMedida->bmi / 40 * 100)) : 0 }}%">
                                <span>{{ $ultimaMedida->bmi ?? '—' }}</span>
                            </div>
                            <span class="kpi__etiqueta">IMC</span>
                            @if ($ultimaMedida->bmi_category)
                                @php
                                    $claseImc = match ($ultimaMedida->bmi_category) {
                                        'Normal' => 'estado--activo',
                                        'Bajo peso', 'Sobrepeso' => 'estado--pendiente',
                                        default => 'estado--inactivo',
                                    };
                                @endphp
                                <span class="estado {{ $claseImc }}">{{ $ultimaMedida->bmi_category }}</span>
                            @elseif (! $ultimaMedida->altura)
                                <span style="color:var(--humo);font-size:var(--t-xs)">Sin altura registrada</span>
                            @endif
                        </article>

                        <article class="tarjeta kpi">
                            <b class="kpi__valor">{{ $ultimaMedida->body_fat_pct ?? '—' }}{{ $ultimaMedida->body_fat_pct ? '%' : '' }}</b>
                            <span class="kpi__etiqueta">Grasa corporal</span>
                            @if ($ultimaMedida->body_fat_pct && $primeraMedida?->body_fat_pct && $primeraMedida->id !== $ultimaMedida->id)
                                @php $difGrasa = round($ultimaMedida->body_fat_pct - $primeraMedida->body_fat_pct, 1); @endphp
                                <span class="kpi__variacion {{ $difGrasa <= 0 ? 'kpi__variacion--pos' : 'kpi__variacion--neg' }}">
                                    {{ $difGrasa <= 0 ? '↓' : '↑' }} {{ $difGrasa > 0 ? '+' : '' }}{{ $difGrasa }} pt desde el inicio
                                </span>
                            @endif
                        </article>
                    </div>
                @endif

                <article class="tarjeta grafico">
                    <h3 style="font-size:var(--t-lg)">Evolución de peso</h3>
                    <div class="grafico__lienzo">
                        <canvas data-grafico="{{ json_encode(['tipo' => 'line', 'labels' => $grafico['labels'], 'label' => 'Peso (kg)', 'data' => $grafico['data']]) }}"></canvas>
                    </div>
                </article>
            </div>

            <div x-show="tab==='medidas'" x-cloak class="pestana-panel">
                <form class="tarjeta formulario-panel" method="POST" action="{{ route('admin.clientes.medidas.store', $cliente) }}">
                    @csrf
                    <div class="formulario-panel__fila">
                        <label class="campo"><span class="campo__etiqueta">Fecha</span>
                            <input class="campo__control" type="date" name="measured_at" value="{{ now()->toDateString() }}" required></label>
                        <label class="campo"><span class="campo__etiqueta">Peso (kg)</span>
                            <input class="campo__control" type="number" step="0.1" name="weight_kg" required></label>
                        <label class="campo">
                            <span class="campo__etiqueta">
                                Altura (cm)
                                <span class="campo__ayuda" tabindex="0"
                                      aria-label="Se usa para calcular el IMC de esta toma. En blanco: se usa la altura de la ficha del cliente ({{ $cliente->height_cm ? $cliente->height_cm . ' cm' : 'sin registrar' }})."
                                      x-data="{ abierto: false, x: 0, y: 0 }"
                                      @mouseenter="abierto = true; const r = $el.getBoundingClientRect(); x = r.left + r.width / 2; y = r.top"
                                      @mouseleave="abierto = false"
                                      @focus="abierto = true; const r = $el.getBoundingClientRect(); x = r.left + r.width / 2; y = r.top"
                                      @blur="abierto = false">?
                                    <template x-teleport="body">
                                        {{-- Sin x-transition: combinado con x-teleport, Alpine
                                             espera un transitionend que a veces no llega y el
                                             tooltip se queda pegado abierto (visto en pantalla).
                                             x-show solo (display:none ↔ '') es instantáneo y
                                             no depende de ese evento. --}}
                                        <div class="tooltip-flotante" x-show="abierto" x-cloak
                                             :style="`left:${x}px;top:${y}px`">
                                            Se usa para calcular el IMC de esta toma. En blanco: se usa la altura de la ficha del cliente ({{ $cliente->height_cm ? $cliente->height_cm . ' cm' : 'sin registrar' }}).
                                        </div>
                                    </template>
                                </span>
                            </span>
                            <input class="campo__control" type="number" name="height_cm" value="{{ $cliente->height_cm }}" min="100" max="250"></label>
                        <label class="campo"><span class="campo__etiqueta">% Grasa</span>
                            <input class="campo__control" type="number" step="0.1" name="body_fat_pct"></label>
                    </div>
                    <div class="formulario-panel__acciones">
                        <button class="btn btn--fuego" type="submit">Registrar medida</button>
                    </div>
                </form>

                <div class="tabla-envoltorio">
                    <table class="tabla tabla--tarjetas">
                        <thead><tr><th>Fecha</th><th>Peso</th><th>Altura</th><th>% Grasa</th><th>IMC</th></tr></thead>
                        <tbody>
                            @forelse ($medidasPag as $m)
                                <tr>
                                    <td data-etiqueta="Fecha">{{ $m->measured_at->translatedFormat('d M Y') }}</td>
                                    <td class="es-fuerte" data-etiqueta="Peso">{{ $m->weight_kg }} kg</td>
                                    <td data-etiqueta="Altura">
                                        @if ($m->altura)
                                            {{ $m->altura }} cm
                                            @unless ($m->height_cm)
                                                <span style="color:var(--humo)" title="Heredada de la ficha del cliente">(ficha)</span>
                                            @endunless
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td data-etiqueta="% Grasa">{{ $m->body_fat_pct ? $m->body_fat_pct . '%' : '—' }}</td>
                                    <td data-etiqueta="IMC">{{ $m->bmi ?? '—' }} <span style="color:var(--humo)">{{ $m->bmi_category }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="tabla__vacio" data-etiqueta=""><x-estado-vacio icono="grafico" texto="Sin medidas registradas." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- appends(['tab'=>'medidas']) ya viaja en cada link (ver
                     controlador): al cambiar de página, el ?tab=medidas de
                     la URL es lo que hace que $tabActiva vuelva a abrir esta
                     pestaña en vez de resetear a "Resumen". --}}
                <div class="paginacion">{{ $medidasPag->links() }}</div>
            </div>

            <div x-show="tab==='membresias'" x-cloak class="pestana-panel">
                <form class="tarjeta formulario-panel" method="POST" action="{{ route('admin.clientes.membresias.store', $cliente) }}">
                    @csrf
                    <div class="formulario-panel__fila">
                        <label class="campo"><span class="campo__etiqueta">Plan</span>
                            <select class="campo__control" name="plan_id" required>
                                @foreach ($cliente->gym->plans()->activos()->get() ?? [] as $plan)
                                    <option value="{{ $plan->id }}">{{ $plan->name }} — S/ {{ number_format($plan->price, 0) }}</option>
                                @endforeach
                            </select></label>
                        <label class="campo"><span class="campo__etiqueta">Inicio</span>
                            <input class="campo__control" type="date" name="starts_at" value="{{ now()->toDateString() }}" required></label>
                        <label class="campo">
                            <span class="campo__etiqueta">
                                Fin (opcional)
                                {{-- Tooltip en vez de texto fijo: la ayuda no
                                     empuja el layout ni desalinea la fila.
                                     x-teleport lo saca del DOM de .tarjeta —
                                     ver el porqué en components.css junto a
                                     .tooltip-flotante. --}}
                                <span class="campo__ayuda" tabindex="0"
                                      aria-label="En blanco: inicio + duración del plan. Úsalo si el cliente pagó un periodo distinto (p. ej. inscripción fuera del sistema)."
                                      x-data="{ abierto: false, x: 0, y: 0 }"
                                      @mouseenter="abierto = true; const r = $el.getBoundingClientRect(); x = r.left + r.width / 2; y = r.top"
                                      @mouseleave="abierto = false"
                                      @focus="abierto = true; const r = $el.getBoundingClientRect(); x = r.left + r.width / 2; y = r.top"
                                      @blur="abierto = false">?
                                    <template x-teleport="body">
                                        {{-- Sin x-transition: combinado con x-teleport, Alpine
                                             espera un transitionend que a veces no llega y el
                                             tooltip se queda pegado abierto (visto en pantalla).
                                             x-show solo (display:none ↔ '') es instantáneo y
                                             no depende de ese evento. --}}
                                        <div class="tooltip-flotante" x-show="abierto" x-cloak
                                             :style="`left:${x}px;top:${y}px`">
                                            En blanco: inicio + duración del plan. Úsalo si el cliente pagó un periodo distinto (p. ej. inscripción fuera del sistema).
                                        </div>
                                    </template>
                                </span>
                            </span>
                            <input class="campo__control" type="date" name="ends_at" value="{{ old('ends_at') }}">
                            @error('ends_at')
                                <span class="campo__error">{{ $message }}</span>
                            @enderror
                        </label>
                        <label class="campo"><span class="campo__etiqueta">Descuento</span>
                            <input class="campo__control" type="number" step="0.01" name="discount" value="0"></label>
                        <label class="campo"><span class="campo__etiqueta">Método de pago</span>
                            <select class="campo__control" name="method" required>
                                @foreach (config('sparta.metodos_pago') as $v => $l)
                                    <option value="{{ $v }}">{{ $l }}</option>
                                @endforeach
                            </select></label>
                    </div>
                </form>

                {{-- Recordatorio de WhatsApp: botón persistente (no se
                     autoculta) que aparece cuando la membresía vigente está
                     a punto de vencer o ya venció (mismo umbral que se usa
                     para "socios por vencer" en el dashboard) y el cliente
                     tiene teléfono. Es un enlace wa.me plano — no hay
                     integración con la API de WhatsApp Business, el admin
                     revisa y presiona Enviar desde su propio WhatsApp.
                     Diseño original: PLAN-VENTAS-CLIENTES.md, Parte 3.
                     $membresiaActual/$diasRestantes/$mostrarWhatsApp se
                     calculan arriba, antes de las pestañas. --}}
                @if ($mostrarWhatsApp && $cliente->phone)
                    @php
                        $nombreCliente = $cliente->first_name;
                        $planNombre = $membresiaActual->plan_name;
                        $fechaVencimiento = $membresiaActual->ends_at->translatedFormat('d \\d\\e F');

                        $mensajeWhatsApp = match (true) {
                            $diasRestantes > 4 => "Hola {$nombreCliente}, tu membresía de {$planNombre} vence en {$diasRestantes} días ({$fechaVencimiento}). ¿Deseas renovarla? 💪",
                            $diasRestantes > 2 => "Hola {$nombreCliente}, te recordamos que tu membresía de {$planNombre} vence en {$diasRestantes} días ({$fechaVencimiento}). ¡No la dejes pasar! 💪",
                            $diasRestantes > 0 => "Hola {$nombreCliente}, tu membresía de {$planNombre} vence MAÑANA ({$fechaVencimiento}). ¡Agenda tu renovación! 💪",
                            $diasRestantes === 0 => "Hola {$nombreCliente}, tu membresía de {$planNombre} vence HOY. ¡Ven a renovarla! 💪",
                            default => "Hola {$nombreCliente}, tu membresía de {$planNombre} venció hace " . abs($diasRestantes) . " días ({$fechaVencimiento}). ¡Te esperamos para renovar! 💪",
                        };

                        // preg_replace deja solo dígitos: quita +, espacios y
                        // guiones que la recepción pudo teclear al registrar
                        // el teléfono — wa.me exige el número "pelado".
                        $urlWhatsApp = 'https://wa.me/' . preg_replace('/\D+/', '', $cliente->phone)
                                      . '?text=' . urlencode($mensajeWhatsApp);
                    @endphp

                    {{-- Sin x-data/x-init de autocierre: el recepcionista
                         puede tardar en mirar la pantalla, así que el botón
                         se queda hasta que la membresía deje de estar en
                         ventana de aviso. --}}
                    <div class="aviso aviso--whatsapp">
                        <x-icono nombre="whatsapp" />
                        <div class="aviso__texto">
                            @if ($diasRestantes > 0)
                                <b>Faltan {{ $diasRestantes }} días</b> para que venza la membresía
                            @elseif ($diasRestantes === 0)
                                <b>La membresía vence hoy</b>
                            @else
                                <b>La membresía venció hace {{ abs($diasRestantes) }} días</b>
                            @endif
                        </div>
                        <a class="btn btn--whatsapp" href="{{ $urlWhatsApp }}" target="_blank" rel="noopener">
                            <x-icono nombre="whatsapp" /> Enviar recordatorio
                        </a>
                    </div>
                @endif

                {{-- Tarjetas de membresía, en lista (una por fila, más
                     reciente primero — igual orden que la tabla anterior).
                     La cuenta regresiva y el riel de tiempo viven en
                     Resumen; acá solo queda el dato: plan, periodo, total,
                     estado. --}}
                @if ($cliente->memberships->isEmpty())
                    <div class="tarjeta">
                        <x-estado-vacio icono="tarjetas" texto="Sin membresías todavía." />
                    </div>
                @else
                    <div class="membresias">
                        @foreach ($cliente->memberships as $mem)
                            <article class="tarjeta membresia">
                                <div class="membresia__cabecera">
                                    <h4 class="membresia__plan">{{ $mem->plan_name }}</h4>
                                    <span class="estado estado--{{ $mem->estado_visual }}">{{ $etiquetasEstadoMem[$mem->estado_visual] ?? ucfirst($mem->estado_visual) }}</span>
                                </div>
                                <div class="membresia__fila">
                                    <span class="membresia__periodo">{{ $mem->starts_at->format('d/m/y') }} – {{ $mem->ends_at->format('d/m/y') }}</span>
                                    <span class="membresia__total-inline">S/ {{ number_format($mem->total, 2) }}</span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>

            <div x-show="tab==='pagos'" x-cloak class="tabla-envoltorio">
                <table class="tabla tabla--tarjetas">
                    <thead><tr><th>Fecha</th><th>Concepto</th><th>Monto</th><th>Método</th></tr></thead>
                    <tbody>
                        @forelse ($cliente->sales as $pago)
                            <tr>
                                <td data-etiqueta="Fecha">{{ $pago->sold_at->format('d/m/y H:i') }}</td>
                                <td class="es-fuerte" data-etiqueta="Concepto">{{ $pago->concept ?? ($pago->sale_type === 'producto' ? 'Venta de producto' : ucfirst($pago->sale_type)) }}</td>
                                <td data-etiqueta="Monto">S/ {{ number_format($pago->total, 2) }}</td>
                                <td data-etiqueta="Método">{{ $pago->metodo_legible }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="tabla__vacio" data-etiqueta=""><x-estado-vacio icono="billetera" texto="Sin ventas todavía." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div x-show="tab==='asistencia'" x-cloak class="tabla-envoltorio">
                <table class="tabla tabla--tarjetas">
                    <thead><tr><th>Fecha</th><th>Entrada</th><th>Salida</th><th>Método</th></tr></thead>
                    <tbody>
                        @forelse ($cliente->attendances as $a)
                            <tr>
                                <td data-etiqueta="Fecha">{{ $a->checked_in_at->translatedFormat('d M Y') }}</td>
                                <td class="es-fuerte" data-etiqueta="Entrada">{{ $a->checked_in_at->format('H:i') }}</td>
                                <td data-etiqueta="Salida">{{ $a->checked_out_at?->format('H:i') ?? '—' }}</td>
                                <td data-etiqueta="Método">{{ ucfirst($a->method) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="tabla__vacio" data-etiqueta=""><x-estado-vacio icono="entrada" texto="Sin asistencias todavía." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <x-modal-confirmar />
@endsection
