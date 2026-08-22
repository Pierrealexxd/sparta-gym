@extends('layouts.panel')

@section('titulo', 'Ventas')
@section('subtitulo', 'Todo lo que entra por dinero: productos y membresías — hoy: S/ ' . number_format($ventasHoy, 2))

@section('acciones')
    @if ($tipo === 'producto' && auth()->user()->tienePermiso('ventas.registrar'))
        <button class="btn btn--fuego" type="button" x-data @click="$dispatch('abrir-modal-venta')">
            <x-icono nombre="agregar" /> Registrar venta
        </button>
    @endif
    {{-- La matrícula vive acá desde ahora: se matricula desde la caja, no
         desde el listado de clientes. El trámite admite productos del
         inventario en el mismo ticket (ver MatriculaController::store). --}}
    @if ($tipo === 'membresia' && auth()->user()->tienePermiso('clientes.crear'))
        <button class="btn btn--fuego" type="button" x-data @click="$dispatch('abrir-modal-matricula')">
            <x-icono nombre="agregar" /> Nueva matrícula
        </button>
    @endif
    {{-- Importar en las dos pestañas, pero cada una crea lo suyo: desde
         Productos importa ventas de mostrador (descuenta stock), desde
         Registros importa registros genéricos sin tocar productos (ver
         SaleController::importar). El contexto viaja en un campo oculto. --}}
    @if (auth()->user()->tienePermiso('reportes.exportar'))
        <button class="btn btn--vidrio" type="button" x-data @click="$dispatch('abrir-modal-importar')">
            <x-icono nombre="subir" /> Importar Excel
        </button>
    @endif
@endsection

@section('contenido')
    {{-- Pestañas por tipo: un solo listado en vez de pantallas separadas
         que preguntaban lo mismo ("¿qué se vendió?"). --}}
    <nav class="pestanas__nav pestanas__nav--ancha" style="margin-bottom:var(--e-5)">
        <a class="pestanas__enlace" href="{{ route('admin.ventas.index', ['tipo' => 'producto']) }}" aria-current="{{ $tipo === 'producto' ? 'true' : 'false' }}">Productos</a>
        <a class="pestanas__enlace" href="{{ route('admin.ventas.index', ['tipo' => 'membresia']) }}" aria-current="{{ $tipo === 'membresia' ? 'true' : 'false' }}">Registros</a>
    </nav>

    <div class="kpis kpis--4" data-revelar data-revelar-grupo>
        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="billetera" /></span>
            <b class="kpi__valor">S/ <span data-contador="{{ $totalRango }}">0</span></b>
            <span class="kpi__etiqueta">
                @if ($tipo === 'producto') Bruto de productos
                @else Cobrado en el rango @endif
            </span>
        </article>

        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="reloj" /></span>
            <b class="kpi__valor">S/ <span data-contador="{{ $ventasHoy }}">0</span></b>
            <span class="kpi__etiqueta">Recaudado hoy</span>
        </article>

        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="lista" /></span>
            <b class="kpi__valor"><span data-contador="{{ $conteoRango }}">0</span></b>
            <span class="kpi__etiqueta">
                @if ($tipo === 'producto') Operaciones en el rango
                @else Registros en el rango @endif
            </span>
        </article>

        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="grafico" /></span>
            <b class="kpi__valor">S/ <span data-contador="{{ $ticketPromedio }}">0</span></b>
            <span class="kpi__etiqueta">Ticket promedio</span>
        </article>
    </div>

    <form class="panel__toolbar toolbar-ventas" method="GET">
        <input type="hidden" name="tipo" value="{{ $tipo }}">
        @if ($tipo === 'membresia')
            <input type="hidden" name="asistencia" value="{{ $asistieronHoy ? 'hoy' : '' }}">
        @endif
        <div class="toolbar-ventas__fechas">
            <label class="campo"><input class="campo__control" type="date" name="desde" value="{{ $desde }}"></label>
            <label class="campo"><input class="campo__control" type="date" name="hasta" value="{{ $hasta }}"></label>
        </div>
        <div class="toolbar-ventas__acciones">
            <button class="btn btn--vidrio" type="submit">Filtrar</button>
            @if ($tipo === 'membresia')
                <button class="btn btn--vidrio" type="button"
                        @if ($planDiario ?? null) data-title="Pase diario: {{ $planDiario->name }}" @endif
                        onclick="this.form.elements.asistencia.value=this.form.elements.asistencia.value==='hoy'?'':'hoy';this.form.submit()">
                    <x-icono nombre="entrada" /> {{ $asistieronHoy ? 'Ver todas' : 'Asistieron hoy' }}
                </button>
            @endif
        </div>
    </form>

    {{-- Exportar: respeta el filtro de fechas y tipo activo (van en la
         query string). Mismo permiso que importar: quien puede sacar el
         Excel de referencia también puede volver a subirlo. --}}
    @if (auth()->user()->tienePermiso('reportes.exportar'))
        <div class="toolbar-acciones toolbar-exportar" data-revelar>
            <span class="toolbar-acciones__label">Exportar:</span>
            <a class="btn btn--vidrio btn--sm"
               href="{{ route('admin.ventas.exportar', ['tipo' => $tipo, 'desde' => $desde, 'hasta' => $hasta, 'formato' => 'excel']) }}">
                <x-icono nombre="descargar" /> Excel
            </a>
            <a class="btn btn--vidrio btn--sm"
               href="{{ route('admin.ventas.exportar', ['tipo' => $tipo, 'desde' => $desde, 'hasta' => $hasta, 'formato' => 'pdf']) }}">
                <x-icono nombre="descargar" /> PDF
            </a>
        </div>
    @endif

    {{-- ---------- Productos ----------
         Una fila por venta con líneas de producto: mostrador puro o
         matrícula mixta. La columna Bruto suma SOLO las líneas — el plan
         de una venta mixta se cobra en Registros, no acá (doble vista,
         sin doble conteo). --}}
    @if ($tipo === 'producto')
        <div class="tabla-envoltorio" data-revelar>
            <table class="tabla tabla--tarjetas">
                <thead><tr><th>N°</th><th>Fecha</th><th>Cliente</th><th>Productos</th><th class="tabla__oculta-movil">Método</th><th>Bruto productos</th><th class="tabla__oculta-movil">Vendido por</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($ventas as $venta)
                        @php $anulada = $venta->status === 'anulada'; @endphp
                        <tr class="tarjeta--interactiva"
                            @if (! $anulada) style="cursor:pointer"
                            @click="$dispatch('abrir-detalle-venta', { url: '{{ route('admin.ventas.detalle', $venta) }}' })"
                            @endif
                            @if ($anulada) style="opacity:.5" @endif>
                            <td class="es-fuerte {{ $anulada ? 'line-through' : '' }}" style="font-family:var(--f-mono)" data-etiqueta="N°">{{ $venta->number }}</td>
                            <td class="{{ $anulada ? 'line-through' : '' }}" data-etiqueta="Fecha">{{ $venta->sold_at->format('d/m/y H:i') }}</td>
                            <td class="es-fuerte {{ $anulada ? 'line-through' : '' }}" data-etiqueta="Cliente">
                                @if ($venta->member)
                                    <a href="{{ route('admin.clientes.show', $venta->member) }}">{{ $venta->member->full_name }}</a>
                                @else — @endif
                            </td>
                            <td class="{{ $anulada ? 'line-through' : '' }}" data-etiqueta="Productos">{{ $venta->items->map(fn ($i) => $i->quantity . '× ' . $i->product_name)->join(', ') }}</td>
                            <td class="tabla__oculta-movil {{ $anulada ? 'line-through' : '' }}" data-etiqueta="Método">{{ config("sparta.metodos_pago.$venta->method", $venta->method) }}</td>
                            <td class="{{ $anulada ? 'line-through' : '' }}" data-etiqueta="Bruto productos">S/ {{ number_format($venta->bruto_items ?? 0, 2) }}</td>
                            <td class="tabla__oculta-movil {{ $anulada ? 'line-through' : '' }}" data-etiqueta="Vendido por">{{ $venta->soldBy?->name ?? '—' }}</td>
                            <td data-etiqueta="Estado"><span class="estado estado--{{ $venta->status }}">{{ ucfirst($venta->status) }}</span></td>
                            <td data-etiqueta="nada">
                                @if ($anulada)
                                    <span class="badge badge--anulado">Anulado</span>
                                @else
                                    {{-- Acciones por icono: el modal de edición trae
                                         los datos del endpoint de detalle y conserva
                                         el registro original; la papelera pasa por la
                                         confirmación antes de anular. --}}
                                    <div class="acciones-icono">
                                        @if ($venta->status === 'completada' && auth()->user()->tienePermiso('ventas.registrar'))
                                            <button class="accion-icono accion-icono--editar" type="button"
                                                    title="Editar venta" aria-label="Editar venta {{ $venta->number }}"
                                                    @click.stop="$dispatch('abrir-editar-venta', { url: '{{ route('admin.ventas.detalle', $venta) }}' })">
                                                <x-icono nombre="lapiz" />
                                            </button>
                                        @endif
                                        @if ($venta->status === 'completada' && auth()->user()->tienePermiso('pagos.anular'))
                                            <button class="accion-icono accion-icono--peligro" type="button"
                                                    title="Anular venta" aria-label="Anular venta {{ $venta->number }}"
                                                    @click.stop="$store.confirmar.abrir({
                                                        accion: '{{ route('admin.ventas.anular', $venta) }}',
                                                        metodo: 'POST',
                                                        titulo: 'Anular venta',
                                                        mensaje: '¿Anular esta venta? El stock vendido se repone.',
                                                        etiqueta: 'Anular'
                                                    })">
                                                <x-icono nombre="papelera" />
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="tabla__vacio" data-etiqueta=""><x-estado-vacio icono="caja" texto="Sin ventas en este rango." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="paginacion">{{ $ventas->links() }}</div>
        <x-modal-confirmar />
    @endif

    {{-- ---------- Membresías ---------- --}}
    @if ($tipo === 'membresia')
        <div class="tabla-envoltorio" data-revelar>
            <table class="tabla tabla--tarjetas">
                <thead><tr><th>N°</th><th>Fecha</th><th>Cliente</th><th>Concepto</th><th>Total</th><th class="tabla__oculta-movil">Método</th><th>Vendido por</th><th></th></tr></thead>
                <tbody>                    @forelse ($ventas as $venta)
                        @php $anulada = $venta->status === 'anulada'; @endphp
                        <tr class="tarjeta--interactiva"
                            @if (! $anulada) style="cursor:pointer"
                            @click="$dispatch('abrir-detalle-venta', { url: '{{ route('admin.ventas.detalle', $venta) }}' })"
                            @endif
                            @if ($anulada) style="opacity:.5" @endif>
                            <td class="es-fuerte {{ $anulada ? 'line-through' : '' }}" style="font-family:var(--f-mono)" data-etiqueta="N°">{{ $venta->number }}</td>
                            <td class="{{ $anulada ? 'line-through' : '' }}" data-etiqueta="Fecha">{{ $venta->sold_at->format('d/m/y H:i') }}</td>
                            <td class="es-fuerte {{ $anulada ? 'line-through' : '' }}" data-etiqueta="Cliente">
                                @if ($venta->member)
                                    <a href="{{ route('admin.clientes.show', $venta->member) }}">{{ $venta->member->full_name }}</a>
                                @else — @endif
                            </td>
                            <td class="{{ $anulada ? 'line-through' : '' }}" data-etiqueta="Concepto">
                                {{ $venta->concept }}
                                @if ($venta->items->isNotEmpty())
                                    <span style="color:var(--bronce);font-family:var(--f-mono);font-size:var(--t-xs)"> + {{ $venta->items->count() }} producto(s)</span>
                                @endif
                            </td>
                            <td class="{{ $anulada ? 'line-through' : '' }}" data-etiqueta="Total">S/ {{ number_format($venta->total, 2) }}</td>
                            <td class="tabla__oculta-movil {{ $anulada ? 'line-through' : '' }}" data-etiqueta="Método">{{ config("sparta.metodos_pago.$venta->method", $venta->method) }}</td>
                            <td class="{{ $anulada ? 'line-through' : '' }}" data-etiqueta="Vendido por">{{ $venta->soldBy?->name ?? '—' }}</td>
                            <td data-etiqueta="nada">
                                @if ($anulada)
                                    <span class="badge badge--anulado">Anulado</span>
                                @else
                                    {{-- Iconos, no texto: lápiz abre el editor (método,
                                         descuento y consumo; el backend sigue dueño de la
                                         regla de caja) y papelera pasa por confirmar. --}}
                                    <div class="acciones-icono">
                                        @if ($venta->status === 'completada' && auth()->user()->tienePermiso('ventas.registrar'))
                                            <button class="accion-icono accion-icono--editar" type="button"
                                                    title="Editar venta" aria-label="Editar venta {{ $venta->number }}"
                                                    @click.stop="$dispatch('abrir-editar-venta', { url: '{{ route('admin.ventas.detalle', $venta) }}' })">
                                                <x-icono nombre="lapiz" />
                                            </button>
                                        @endif
                                        @if ($venta->status === 'completada' && auth()->user()->tienePermiso('pagos.anular'))
                                            <button class="accion-icono accion-icono--peligro" type="button"
                                                    title="Anular venta" aria-label="Anular venta {{ $venta->number }}"
                                                    @click.stop="$store.confirmar.abrir({
                                                        accion: '{{ route('admin.ventas.anular', $venta) }}',
                                                        metodo: 'POST',
                                                        titulo: 'Anular registro',
                                                        mensaje: '¿Anular este registro? El monto de S/ {{ number_format($venta->total, 2) }} se descontará de la recaudación.',
                                                        etiqueta: 'Anular'
                                                    })">
                                                <x-icono nombre="papelera" />
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="tabla__vacio" data-etiqueta=""><x-estado-vacio icono="tarjetas" texto="Sin membresías vendidas en este rango." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="paginacion">{{ $ventas->links() }}</div>
        <x-modal-confirmar />
    @endif

    {{-- ---------- Hoja de matrícula (pestaña Registros) ----------
         Una sola pantalla matricial, no un wizard: cliente arriba (nuevo
         o buscado), plan en desplegable, productos de consumo inmediato
         con stepper y el pago cerrando el cuadro. El total se recalcula
         al contar hierro — plan + productos − descuento. --}}
    @if ($tipo === 'membresia' && auth()->user()->tienePermiso('clientes.crear') && $planes->isNotEmpty())
        <div class="modal__fondo"
             x-data="{ abierta: {{ $errors->any() && old('plan_id') !== null ? 'true' : 'false' }} }"
             x-show="abierta" x-cloak
             @abrir-modal-matricula.window="abierta = true"
             @keydown.escape.window="abierta = false">
            <div class="tarjeta modal__caja wizard" x-data="matriculaVentas()" @click.outside="abierta = false">
                <div class="modal__cabecera">
                    <h3>Nueva matrícula</h3>
                    <button class="modal__cerrar" type="button" @click="abierta = false" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
                </div>

                @if ($errors->any())
                    <div class="aviso aviso--error" role="alert" style="margin-bottom:var(--e-5)">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.matricula.store') }}" class="hoja-matricula" @submit="enviar($event)">
                    @csrf
                    <input type="hidden" name="member_id" :value="clienteExistenteId ?? ''">
                    {{-- En caja el trámite siempre cobra: sin casilla de "pago después". --}}
                    <input type="hidden" name="registrar_pago" value="1">

                    {{-- CLIENTE: nuevo a mano o existente vía buscador. --}}
                    <x-buscador-cliente etiqueta="¿Ya es cliente? Buscarlo (opcional)" bloqueado-cuando="clienteExistenteId" />

                    <div x-show="clienteExistenteId" x-cloak class="aviso aviso--accion">
                        <span>Cliente existente — se renueva su plan, no se crea otro registro.</span>
                        <button type="button" class="btn btn--desnudo" @click="quitarClienteExistente()">Quitar</button>
                    </div>

                    <div class="matriz">
                        <div class="matriz__fila matriz__fila--dos">
                            <label class="campo"><span class="campo__etiqueta">Nombres *</span>
                                <input class="campo__control" type="text" name="first_name" required
                                       x-model="nuevo.first_name" @input="verificarDuplicado()"
                                       :disabled="clienteExistenteId"></label>
                            <label class="campo"><span class="campo__etiqueta">Apellidos *</span>
                                <input class="campo__control" type="text" name="last_name" required
                                       x-model="nuevo.last_name" @input="verificarDuplicado()"
                                       :disabled="clienteExistenteId"></label>
                        </div>
                        <div class="matriz__fila matriz__fila--ancha">
                            <label class="campo"><span class="campo__etiqueta">Plan *</span>
                                <select class="campo__control" name="plan_id" x-model.number="planId" required>
                                    <option value="">— Elegir plan —</option>
                                    <template x-for="p in planes" :key="p.id">
                                        <option :value="p.id" x-text="`${p.name} · ${p.duracion} · S/ ${Number(p.price).toFixed(2)}`"></option>
                                    </template>
                                </select></label>
                        </div>
                    </div>

                    {{-- Anti-duplicados en vivo, misma regla que frena el backend. --}}
                    <div x-show="duplicado && !clienteExistenteId" x-cloak class="aviso aviso--error aviso--accion">
                        <div>
                            <b>Este cliente ya está registrado.</b>
                            <b x-text="duplicado?.cliente.full_name"></b><span x-text="duplicado?.cliente.code ? ' · código ' + duplicado.cliente.code : ''"></span>.
                        </div>
                        <div style="display:flex;gap:var(--e-2)">
                            <button type="button" class="btn btn--fuego" @click="usarClienteExistente()">Usar este cliente</button>
                            <button type="button" class="btn btn--desnudo" @click="descartarDuplicado()">No es él / ella</button>
                        </div>
                    </div>
                    <p x-show="verificando" x-cloak style="color:var(--humo);font-size:var(--t-xs);margin-top:var(--e-2)">Verificando si ya existe…</p>

                    {{-- PRODUCTOS de consumo inmediato: buscador arriba,
                          fila por producto con stepper; cero = no lleva.
                          El filtro solo OCULTA filas (x-show): las líneas
                          elegidas siguen enviándose aunque el buscador las
                          tape. Los pares product_id/quantity solo viajan
                          para lo elegido. --}}
                    @if ($productos->isNotEmpty())
                        <div style="margin-top:var(--e-4)">
                            <span class="campo__etiqueta">Consumo inmediato (agua, suplementos…)
                                <b class="consumo__conteo" x-show="enTicket() > 0" x-cloak
                                   x-text="'· ' + enTicket() + ' en el ticket'"></b>
                            </span>
                            <div class="consumo">
                                <label class="consumo__busca">
                                    <x-icono nombre="lupa" />
                                    <input type="search" x-model="buscarProd"
                                           placeholder="Buscar agua, proteína, accesorio…"
                                           aria-label="Buscar producto">
                                </label>
                                <div class="consumo__lista">
                                    <div class="consumo__cabecera" aria-hidden="true">
                                        <span>Producto</span>
                                        <span class="consumo__precio">Precio</span>
                                        <span class="consumo__cant">Cant.</span>
                                        <span class="consumo__importe">Importe</span>
                                    </div>
                                    @foreach ($productos as $producto)
                                        <div class="consumo__fila"
                                             x-show="coincide({{ $producto->id }})"
                                             :class="{ 'is-seleccionada': (cantidades[{{ $producto->id }}] || 0) > 0 }">
                                            {{-- Las entradas viven dentro del template: si
                                                 cantidad es 0 no viajan (ver MatriculaController). --}}
                                            <template x-if="(cantidades[{{ $producto->id }}] || 0) > 0">
                                                <span hidden>
                                                    <input type="hidden" name="productos[{{ $loop->index }}][product_id]" value="{{ $producto->id }}">
                                                    <input type="hidden" :name="'productos[{{ $loop->index }}][quantity]'" :value="cantidades[{{ $producto->id }}]">
                                                </span>
                                            </template>
                                            <span class="consumo__nombre">{{ $producto->name }}</span>
                                            <span class="consumo__precio">S/ {{ number_format($producto->sale_price, 2) }}</span>
                                            <span class="consumo__cant">
                                                <span class="stepper stepper--chico">
                                                    <button class="stepper__btn" type="button" aria-label="Quitar uno de {{ $producto->name }}"
                                                            :disabled="(cantidades[{{ $producto->id }}] || 0) <= 0"
                                                            @click="cantidades[{{ $producto->id }}] = Math.max((cantidades[{{ $producto->id }}] || 0) - 1, 0)">−</button>
                                                    <span class="stepper__cifra" x-text="cantidades[{{ $producto->id }}] || 0"></span>
                                                    <button class="stepper__btn" type="button" aria-label="Agregar uno de {{ $producto->name }}"
                                                            :disabled="(cantidades[{{ $producto->id }}] || 0) >= {{ $producto->stock }}"
                                                            @click="cantidades[{{ $producto->id }}] = Math.min((cantidades[{{ $producto->id }}] || 0) + 1, {{ $producto->stock }})">+</button>
                                                </span>
                                            </span>
                                            <span class="consumo__importe"
                                                  x-text="'S/ ' + ({{ (float) $producto->sale_price }} * (cantidades[{{ $producto->id }}] || 0)).toFixed(2)"></span>
                                        </div>
                                    @endforeach
                                    <div class="consumo__vacio" x-show="sinResultados()" x-cloak>
                                        Ningún producto coincide con la búsqueda.
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- PAGO: lo editable se cambia en su celda. --}}
                    <div class="matriz" style="margin-top:var(--e-4)">
                        <div class="matriz__fila matriz__fila--dos">
                            <label class="campo"><span class="campo__etiqueta">Inicio del plan *</span>
                                <input class="campo__control" type="date" name="starts_at" x-model="startsAt" required></label>
                            <label class="campo"><span class="campo__etiqueta">Descuento (S/)</span>
                                <input class="campo__control" type="number" step="0.01" min="0" name="discount" x-model="discount"></label>
                        </div>
                        <div class="matriz__fila matriz__fila--dos">
                            <label class="campo"><span class="campo__etiqueta">Método de pago *</span>
                                <select class="campo__control" name="method" x-model="method" required>
                                    @foreach (config('sparta.metodos_pago') as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                                </select></label>
                            <label class="campo"><span class="campo__etiqueta">Referencia / N° operación</span>
                                <input class="campo__control" type="text" name="reference" x-model="reference"></label>
                        </div>
                        <div class="matriz__fila matriz__fila--ancha">
                            <label class="campo"><span class="campo__etiqueta">
                                <input type="checkbox" name="crear_login" value="1" x-model="crearLogin"> Crear login a "Mi cuenta"
                            </span></label>
                        </div>
                        <div class="matriz__fila" x-show="crearLogin" x-cloak>
                            <label class="campo"><span class="campo__etiqueta">Correo del login *</span>
                                <input class="campo__control" type="email" name="access_email" x-model="accessEmail" placeholder="correo@ejemplo.com"></label>
                        </div>
                        <div class="matriz__total">
                            <span>A pagar hoy</span>
                            <b x-text="'S/ ' + montoFinal()"></b>
                        </div>
                    </div>

                    <div class="formulario-panel__acciones">
                        <button class="btn btn--fuego btn--bloque" type="submit" :disabled="enviando || !planId">
                            <span x-show="!enviando">Registrar matrícula y cobro</span>
                            <span x-show="enviando" x-cloak>Guardando…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @php
            // Fuera del @json: Blade no parsea bien los closures con
            // corchetes dentro de una directiva.
            $planesJson = $planes->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name,
                'price' => (float) $p->price, 'duracion' => $p->duracion_legible,
            ]);
            $preciosJson = $productos->mapWithKeys(fn ($p) => [(string) $p->id => (float) $p->sale_price]);
            $nombresJson = $productos->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values();
        @endphp
        @push('scripts')
        <script>
        function matriculaVentas() {
            return {
                enviando: false,
                nuevo: { first_name: '', last_name: '' },
                planId: '', startsAt: new Date().toISOString().slice(0, 10),
                discount: '0', method: 'efectivo', reference: '',
                crearLogin: false, accessEmail: '',
                // Cantidad por producto id; ausente o 0 = no lleva.
                cantidades: {},
                // Mapa id → precio para recalcular el total sin tocar el DOM.
                productos: @json($preciosJson),
                planes: @json($planesJson),
                // Filtro del buscador de consumo: estado + lista id/nombre.
                buscarProd: '',
                nombres: @json($nombresJson),

                buscarQ: '', resultados: [], clienteExistenteId: null,
                duplicado: null, verificando: false, _verifTimer: null,

                verificarDuplicado() {
                    if (this.clienteExistenteId) { this.duplicado = null; return; }
                    clearTimeout(this._verifTimer);

                    const nombres   = this.nuevo.first_name.trim();
                    const apellidos = this.nuevo.last_name.trim();
                    if (nombres.length < 3 || apellidos.length < 3) {
                        this.duplicado = null; this.verificando = false; return;
                    }

                    this.verificando = true;
                    this._verifTimer = setTimeout(() => {
                        const p = new URLSearchParams({ first_name: nombres, last_name: apellidos });
                        fetch('{{ route('admin.clientes.verificar') }}?' + p)
                            .then(r => r.json())
                            .then(d => { this.duplicado = d.coincide ? d : null; })
                            .catch(() => {})
                            .finally(() => { this.verificando = false; });
                    }, 400);
                },
                usarClienteExistente() {
                    if (!this.duplicado) return;
                    const c = this.duplicado.cliente;
                    this.duplicado = null;
                    this.elegirCliente(c);
                },
                descartarDuplicado() { this.duplicado = null; },

                buscarCliente() {
                    if (this.buscarQ.trim().length < 2) { this.resultados = []; return; }
                    fetch('{{ route('admin.clientes.buscar') }}?q=' + encodeURIComponent(this.buscarQ))
                        .then(r => r.json())
                        .then(d => this.resultados = d)
                        .catch(() => this.resultados = []);
                },
                elegirCliente(m) {
                    this.clienteExistenteId = m.id;
                    this.nuevo = { first_name: m.first_name, last_name: m.last_name };
                    this.buscarQ = m.first_name + ' ' + m.last_name + (m.code ? ' (' + m.code + ')' : '');
                    this.resultados = [];
                    this.duplicado = null;
                },
                quitarClienteExistente() {
                    this.clienteExistenteId = null;
                    this.buscarQ = '';
                    this.nuevo = { first_name: '', last_name: '' };
                    this.duplicado = null;
                },

                get precioPlan() {
                    return Number(this.planes.find(p => p.id == this.planId)?.price ?? 0);
                },
                get brutoProductos() {
                    return Object.entries(this.cantidades)
                        .reduce((s, [id, q]) => s + (this.productos[id] ?? 0) * q, 0);
                },
                montoFinal() {
                    return Math.max(this.precioPlan + this.brutoProductos - (Number(this.discount) || 0), 0).toFixed(2);
                },
                // El buscador solo oculta filas: una línea elegida sigue
                // enviándose aunque deje de verse.
                coincide(id) {
                    const q = this.buscarProd.trim().toLowerCase();
                    if (!q) return true;
                    const p = this.nombres.find(x => String(x.id) === String(id));
                    return !!p && p.name.toLowerCase().includes(q);
                },
                sinResultados() {
                    return this.buscarProd.trim() !== '' && !this.nombres.some(p => this.coincide(p.id));
                },
                enTicket() {
                    return Object.values(this.cantidades).reduce((s, q) => s + Number(q), 0);
                },
                enviar($event) {
                    if (this.duplicado && !this.clienteExistenteId) {
                        $event.preventDefault();
                        return;
                    }
                    this.enviando = true;
                },
            };
        }
        </script>
        @endpush
    @endif

    @if ($tipo === 'producto' && auth()->user()->tienePermiso('ventas.registrar'))
        <div class="modal__fondo"
             x-data="{
                abierto: false,
                filas: [{ product_id: '', quantity: 1 }],
                productos: {{ \Illuminate\Support\Js::from($productos->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'price' => (float) $p->sale_price, 'stock' => $p->stock])) }},
                cliente: null, buscarQ: '', resultados: [],
                buscarCliente() {
                    if (this.buscarQ.trim().length < 2) { this.resultados = []; return; }
                    fetch(`{{ route('admin.ventas.buscar-cliente') }}?q=${encodeURIComponent(this.buscarQ)}`)
                        .then(r => r.json())
                        .then(d => this.resultados = d)
                        .catch(() => this.resultados = []);
                },
                elegirCliente(c) { this.cliente = c; this.resultados = []; this.buscarQ = ''; },
                precio(id) { return this.productos.find(p => p.id == id)?.price ?? 0; },
                get total() {
                    return this.filas.reduce((s, f) => s + (this.precio(f.product_id) * (f.quantity || 0)), 0);
                }
             }"
             x-show="abierto" x-cloak
             @abrir-modal-venta.window="abierto = true" @keydown.escape.window="abierto = false">
            <div class="tarjeta modal__caja" @click.outside="abierto = false">
                <div class="modal__cabecera">
                    <h3 style="font-size:var(--t-lg)">Registrar venta</h3>
                    <button class="modal__cerrar" type="button" @click="abierto = false"><x-icono nombre="cerrar" /></button>
                </div>

                @if ($productos->isEmpty())
                    <p style="color:var(--ceniza)">No hay productos activos. Crea uno primero en Inventario.</p>
                @else
                    <form class="formulario-panel" method="POST" action="{{ route('admin.ventas.store') }}">
                        @csrf
                        <input type="hidden" name="member_id" :value="cliente?.id ?? ''">

                        <div x-show="!cliente">
                            <x-buscador-cliente etiqueta="Cliente (opcional)" placeholder="Buscar por nombre o código…" />
                        </div>
                        <div class="aviso" x-show="cliente" x-cloak>
                            Cliente: <b x-text="cliente?.full_name"></b>
                            <button type="button" class="btn btn--desnudo" @click="cliente = null" style="margin-left:var(--e-3)">Quitar</button>
                        </div>

                        <template x-for="(fila, i) in filas" :key="i">
                            <div class="fila-borrable">
                                <div class="formulario-panel__fila">
                                    <label class="campo"><span class="campo__etiqueta">Producto</span>
                                        <select class="campo__control" :name="`items[${i}][product_id]`" x-model="fila.product_id" required>
                                            <option value="">— Elegir —</option>
                                            <template x-for="p in productos" :key="p.id">
                                                <option :value="p.id" x-text="`${p.name} (stock ${p.stock})`"></option>
                                            </template>
                                        </select></label>
                                    <label class="campo"><span class="campo__etiqueta">Cantidad</span>
                                        <input class="campo__control" type="number" min="1" :name="`items[${i}][quantity]`" x-model.number="fila.quantity" required></label>
                                </div>
                                <button class="btn btn--desnudo" type="button" @click="filas.length > 1 && filas.splice(i, 1)" aria-label="Quitar">
                                    <x-icono nombre="papelera" />
                                </button>
                            </div>
                        </template>
                        <button class="btn btn--vidrio" type="button" @click="filas.push({ product_id: '', quantity: 1 })">
                            <x-icono nombre="agregar" /> Añadir producto
                        </button>

                        <div class="formulario-panel__fila">
                            <label class="campo"><span class="campo__etiqueta">Método de pago</span>
                                <select class="campo__control" name="method" required>
                                    @foreach (config('sparta.metodos_pago') as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                                </select></label>
                            <label class="campo"><span class="campo__etiqueta">Descuento (S/)</span>
                                <input class="campo__control" type="number" step="0.01" min="0" name="discount" value="0"></label>
                        </div>

                        <p style="font-family:var(--f-mono);font-size:var(--t-lg);text-align:right">
                            Total: S/ <span x-text="total.toFixed(2)"></span>
                        </p>

                        <div class="formulario-panel__acciones">
                            <button class="btn btn--fuego btn--bloque" type="submit">Registrar venta</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif

    @if (auth()->user()->tienePermiso('reportes.exportar'))
        {{-- Mismo patrón modal__fondo del modal de venta de arriba. El tipo
             activo viaja en un campo oculto: es el contexto que decide si la
             importación crea ventas de producto o registros genéricos. --}}
        <div class="modal__fondo"
             x-data="{ abierto: false }"
             x-show="abierto" x-cloak
             @abrir-modal-importar.window="abierto = true"
             @keydown.escape.window="abierto = false">
            <div class="tarjeta modal__caja" @click.outside="abierto = false">
                <div class="modal__cabecera">
                    <h3 style="font-size:var(--t-lg)">Importar {{ $tipo === 'producto' ? 'ventas' : 'registros' }} desde Excel</h3>
                    <button class="modal__cerrar" type="button" @click="abierto = false">
                        <x-icono nombre="cerrar" />
                    </button>
                </div>

                <form class="formulario-panel" method="POST"
                      action="{{ route('admin.ventas.importar') }}"
                      enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="tipo" value="{{ $tipo }}">

                    <div class="aviso aviso--info">
                        <p><b>Formato esperado del archivo:</b></p>
                        @if ($tipo === 'producto')
                            <p style="font-size:var(--t-xs);color:var(--humo);margin-top:var(--e-2)">
                                fecha (d/m/Y H:i) · cliente_codigo (opcional) · producto_nombre · cantidad · precio_unitario · metodo_pago · descuento (opcional) · notas (opcional)
                            </p>
                            <p style="font-size:var(--t-xs);color:var(--humo);margin-top:var(--e-2)">
                                Crea ventas de mostrador y descuenta stock. Las filas con producto inexistente o sin stock se saltan.
                            </p>
                        @else
                            <p style="font-size:var(--t-xs);color:var(--humo);margin-top:var(--e-2)">
                                fecha (d/m/Y H:i) · cliente_codigo (opcional) · concepto · total · metodo_pago · tipo (opcional: servicio/otro) · notas (opcional)
                            </p>
                            <p style="font-size:var(--t-xs);color:var(--humo);margin-top:var(--e-2)">
                                Crea solo registros genéricos: no toca productos ni stock.
                            </p>
                        @endif
                    </div>

                    <label class="campo">
                        <span class="campo__etiqueta">Archivo Excel (.xlsx, .xls, .csv — máx. 10&nbsp;MB)</span>
                        <input class="campo__control" type="file" name="archivo"
                               accept=".xlsx,.xls,.csv" required>
                    </label>

                    <div class="formulario-panel__acciones">
                        <button class="btn btn--fuego btn--bloque" type="submit">
                            <x-icono nombre="subir" /> Importar {{ $tipo === 'producto' ? 'ventas' : 'registros' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <x-editor-venta :productos="$productos" />

@include('admin.ventas._detalle-venta')

@endsection
