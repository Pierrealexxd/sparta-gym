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
            <nav class="pestanas__nav" style="margin-bottom:var(--e-5)">
                <button class="pestanas__enlace" :aria-current="tab==='resumen'" @click="tab='resumen'" type="button">Resumen</button>
                <button class="pestanas__enlace" :aria-current="tab==='medidas'" @click="tab='medidas'" type="button">Medidas</button>
                <button class="pestanas__enlace" :aria-current="tab==='membresias'" @click="tab='membresias'" type="button">Membresías</button>
                <button class="pestanas__enlace" :aria-current="tab==='pagos'" @click="tab='pagos'" type="button">Pagos</button>
                <button class="pestanas__enlace" :aria-current="tab==='asistencia'" @click="tab='asistencia'" type="button">Asistencia</button>
            </nav>

            <div x-show="tab==='resumen'">
                <article class="tarjeta grafico">
                    <h3 style="font-size:var(--t-lg)">Evolución de peso</h3>
                    <div class="grafico__lienzo">
                        <canvas data-grafico="{{ json_encode(['tipo' => 'line', 'labels' => $grafico['labels'], 'label' => 'Peso (kg)', 'data' => $grafico['data']]) }}"></canvas>
                    </div>
                </article>
            </div>

            <div x-show="tab==='medidas'" x-cloak style="display:grid;gap:var(--e-5)">
                <form class="tarjeta formulario-panel" method="POST" action="{{ route('admin.clientes.medidas.store', $cliente) }}">
                    @csrf
                    <div class="formulario-panel__fila">
                        <label class="campo"><span class="campo__etiqueta">Fecha</span>
                            <input class="campo__control" type="date" name="measured_at" value="{{ now()->toDateString() }}" required></label>
                        <label class="campo"><span class="campo__etiqueta">Peso (kg)</span>
                            <input class="campo__control" type="number" step="0.1" name="weight_kg" required></label>
                        <label class="campo"><span class="campo__etiqueta">% Grasa</span>
                            <input class="campo__control" type="number" step="0.1" name="body_fat_pct"></label>
                    </div>
                    <div class="formulario-panel__acciones">
                        <button class="btn btn--fuego" type="submit">Registrar medida</button>
                    </div>
                </form>

                <div class="tabla-envoltorio">
                    <table class="tabla tabla--tarjetas">
                        <thead><tr><th>Fecha</th><th>Peso</th><th>% Grasa</th><th>IMC</th></tr></thead>
                        <tbody>
                            @forelse ($medidasPag as $m)
                                <tr>
                                    <td data-etiqueta="Fecha">{{ $m->measured_at->translatedFormat('d M Y') }}</td>
                                    <td class="es-fuerte" data-etiqueta="Peso">{{ $m->weight_kg }} kg</td>
                                    <td data-etiqueta="% Grasa">{{ $m->body_fat_pct ? $m->body_fat_pct . '%' : '—' }}</td>
                                    <td data-etiqueta="IMC">{{ $m->bmi ?? '—' }} <span style="color:var(--humo)">{{ $m->bmi_category }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="tabla__vacio" data-etiqueta=""><x-estado-vacio icono="grafico" texto="Sin medidas registradas." /></td></tr>
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

            <div x-show="tab==='membresias'" x-cloak style="display:grid;gap:var(--e-5)">
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
                        <label class="campo"><span class="campo__etiqueta">Descuento</span>
                            <input class="campo__control" type="number" step="0.01" name="discount" value="0"></label>
                        <label class="campo"><span class="campo__etiqueta">Método de pago</span>
                            <select class="campo__control" name="method" required>
                                @foreach (config('sparta.metodos_pago') as $v => $l)
                                    <option value="{{ $v }}">{{ $l }}</option>
                                @endforeach
                            </select></label>
                    </div>
                    <div class="formulario-panel__acciones">
                        <button class="btn btn--fuego" type="submit">Registrar membresía y pago</button>
                    </div>
                </form>

                <div class="tabla-envoltorio">
                    <table class="tabla tabla--tarjetas">
                        <thead><tr><th>Plan</th><th>Periodo</th><th>Precio</th><th>Estado</th></tr></thead>
                        <tbody>
                            @forelse ($cliente->memberships as $mem)
                                <tr>
                                    <td class="es-fuerte" data-etiqueta="Plan">{{ $mem->plan_name }}</td>
                                    <td data-etiqueta="Periodo">{{ $mem->starts_at->format('d/m/y') }} – {{ $mem->ends_at->format('d/m/y') }}</td>
                                    <td data-etiqueta="Precio">S/ {{ number_format($mem->total, 2) }}</td>
                                    <td data-etiqueta="Estado"><span class="estado estado--{{ $mem->status }}">{{ ucfirst($mem->status) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="tabla__vacio" data-etiqueta=""><x-estado-vacio icono="tarjetas" texto="Sin membresías todavía." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
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
