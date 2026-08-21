@extends('layouts.panel')

@section('titulo', 'Ventas')
@section('subtitulo', 'Todo lo que entra por dinero: productos y membresías — hoy: S/ ' . number_format($ventasHoy, 2))

@section('acciones')
    @if ($tipo === 'producto' && auth()->user()->tienePermiso('ventas.registrar'))
        <button class="btn btn--fuego" type="button" x-data @click="$dispatch('abrir-modal-venta')">
            <x-icono nombre="agregar" /> Registrar venta
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
                @if ($tipo === 'producto') Vendido en el rango
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

    {{-- ---------- Productos ---------- --}}
    @if ($tipo === 'producto')
        <div class="tabla-envoltorio" data-revelar>
            <table class="tabla tabla--tarjetas">
                <thead><tr><th>N°</th><th>Fecha</th><th>Productos</th><th class="tabla__oculta-movil">Método</th><th>Total</th><th>Vendido por</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($ventas as $venta)
                        <tr class="tarjeta--interactiva" style="cursor:pointer"
                            @click="$dispatch('abrir-detalle-venta', { url: '{{ route('admin.ventas.detalle', $venta) }}' })">
                            <td class="es-fuerte" style="font-family:var(--f-mono)" data-etiqueta="N°">{{ $venta->number }}</td>
                            <td data-etiqueta="Fecha">{{ $venta->sold_at->format('d/m/y H:i') }}</td>
                            <td data-etiqueta="Productos">{{ $venta->items->map(fn ($i) => $i->quantity . '× ' . $i->product_name)->join(', ') }}</td>
                            <td class="tabla__oculta-movil" data-etiqueta="Método">{{ config("sparta.metodos_pago.$venta->method", $venta->method) }}</td>
                            <td data-etiqueta="Total">S/ {{ number_format($venta->total, 2) }}</td>
                            <td data-etiqueta="Vendido por">{{ $venta->soldBy?->name ?? '—' }}</td>
                            <td data-etiqueta="Estado"><span class="estado estado--{{ $venta->status }}">{{ ucfirst($venta->status) }}</span></td>
                            <td data-etiqueta="nada">
                                @if ($venta->status === 'completada' && auth()->user()->tienePermiso('pagos.anular'))
                                    <button class="btn btn--desnudo" type="button"
                                            @click.stop="$store.confirmar.abrir({
                                                accion: '{{ route('admin.ventas.anular', $venta) }}',
                                                metodo: 'POST',
                                                titulo: 'Anular venta',
                                                mensaje: '¿Anular esta venta? El stock vendido se repone.',
                                                etiqueta: 'Anular'
                                            })">Anular</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="tabla__vacio" data-etiqueta=""><x-estado-vacio icono="caja" texto="Sin ventas en este rango." /></td></tr>
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
                <tbody>
                    @forelse ($ventas as $venta)
                        <tr class="tarjeta--interactiva" style="cursor:pointer"
                            @click="$dispatch('abrir-detalle-venta', { url: '{{ route('admin.ventas.detalle', $venta) }}' })">
                            <td class="es-fuerte" style="font-family:var(--f-mono)" data-etiqueta="N°">{{ $venta->number }}</td>
                            <td data-etiqueta="Fecha">{{ $venta->sold_at->format('d/m/y H:i') }}</td>
                            <td class="es-fuerte" data-etiqueta="Cliente">
                                @if ($venta->member)
                                    <a href="{{ route('admin.clientes.show', $venta->member) }}">{{ $venta->member->full_name }}</a>
                                @else — @endif
                            </td>
                            <td data-etiqueta="Concepto">{{ $venta->concept }}</td>
                            <td data-etiqueta="Total">S/ {{ number_format($venta->total, 2) }}</td>
                            <td class="tabla__oculta-movil" data-etiqueta="Método">{{ config("sparta.metodos_pago.$venta->method", $venta->method) }}</td>
                            <td data-etiqueta="Vendido por">{{ $venta->soldBy?->name ?? '—' }}</td>
                            <td data-etiqueta="nada">
                                @if ($venta->status === 'completada' && auth()->user()->tienePermiso('pagos.anular'))
                                    <button class="btn btn--desnudo" type="button"
                                            @click.stop="$store.confirmar.abrir({
                                                accion: '{{ route('admin.ventas.anular', $venta) }}',
                                                metodo: 'POST',
                                                titulo: 'Anular registro',
                                                mensaje: '¿Anular este registro? El monto de S/ {{ number_format($venta->total, 2) }} se descontará de la recaudación.',
                                                etiqueta: 'Anular'
                                            })">Anular</button>
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

    @include('admin.ventas._detalle-venta')

@endsection
