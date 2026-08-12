@extends('layouts.panel')

@section('titulo', 'Ventas')
@section('subtitulo', 'Todo lo que registras: productos y registros')

@section('acciones')
    @if ($tipo === 'producto')
        <button class="btn btn--fuego" type="button" x-data @click="$dispatch('abrir-modal-venta')">
            <x-icono nombre="agregar" /> Registrar venta
        </button>
    @elseif ($tipo === 'inscripcion')
        <a class="btn btn--fuego" href="{{ route('entrenador.inscripciones.index') }}">
            <x-icono nombre="agregar" /> Nueva inscripción
        </a>
    @endif
@endsection

@section('contenido')
    <nav class="pestanas__nav" style="margin-bottom:var(--e-5)">
        <a class="pestanas__enlace" href="{{ route('entrenador.ventas.index', ['tipo' => 'producto']) }}" aria-current="{{ $tipo === 'producto' ? 'true' : 'false' }}">Productos</a>
        <a class="pestanas__enlace" href="{{ route('entrenador.ventas.index', ['tipo' => 'inscripcion']) }}" aria-current="{{ $tipo === 'inscripcion' ? 'true' : 'false' }}">Registros</a>
    </nav>

    <form class="panel__toolbar" method="GET">
        <input type="hidden" name="tipo" value="{{ $tipo }}">
        <label class="campo"><input class="campo__control" type="date" name="desde" value="{{ $desde }}"></label>
        <label class="campo"><input class="campo__control" type="date" name="hasta" value="{{ $hasta }}"></label>
        <button class="btn btn--vidrio" type="submit">Filtrar</button>
    </form>

    {{-- ---------- Productos ---------- --}}
    @if ($tipo === 'producto')
        <div class="tabla-envoltorio" data-revelar>
            <table class="tabla tabla--tarjetas">
                <thead><tr><th>N°</th><th>Fecha</th><th>Productos</th><th class="tabla__oculta-movil">Método</th><th>Total</th></tr></thead>
                <tbody>
                    @forelse ($ventas as $venta)
                        <tr>
                            <td class="es-fuerte" style="font-family:var(--f-mono)" data-etiqueta="N°">{{ $venta->number }}</td>
                            <td data-etiqueta="Fecha">{{ $venta->sold_at->format('d/m/y H:i') }}</td>
                            <td data-etiqueta="Productos">{{ $venta->items->map(fn ($i) => $i->quantity . '× ' . $i->product_name)->join(', ') }}</td>
                            <td class="tabla__oculta-movil" data-etiqueta="Método">{{ config("sparta.metodos_pago.$venta->method", $venta->method) }}</td>
                            <td data-etiqueta="Total">S/ {{ number_format($venta->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="tabla__vacio" data-etiqueta=""><x-estado-vacio icono="caja" texto="Sin ventas en este rango." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- ---------- Inscripciones ---------- --}}
    @if ($tipo === 'inscripcion')
        <div class="tabla-envoltorio" data-revelar>
            <table class="tabla tabla--tarjetas">
                <thead><tr><th>Fecha</th><th>Cliente</th><th>Plan</th><th>Total</th><th class="tabla__oculta-movil">Vigencia</th></tr></thead>
                <tbody>
                    @forelse ($inscripciones as $mem)
                        <tr>
                            <td data-etiqueta="Fecha">{{ $mem->created_at->format('d/m/y H:i') }}</td>
                            <td class="es-fuerte" data-etiqueta="Cliente">{{ $mem->member?->full_name ?? '—' }}</td>
                            <td data-etiqueta="Plan">{{ $mem->plan_name }}</td>
                            <td data-etiqueta="Total">S/ {{ number_format($mem->total, 2) }}</td>
                            <td class="tabla__oculta-movil" data-etiqueta="Vigencia">{{ $mem->starts_at?->format('d/m/y') }} — {{ $mem->ends_at?->format('d/m/y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="tabla__vacio" data-etiqueta=""><x-estado-vacio icono="tarjetas" texto="Sin inscripciones en este rango." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    <div class="modal__fondo"
         x-data="{
            abierto: false,
            filas: [{ product_id: '', quantity: 1, buscarQ: '', filtrados: [] }],
            productos: {{ \Illuminate\Support\Js::from($productos->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'price' => (float) $p->sale_price, 'stock' => $p->stock])) }},
            nuevaFila() { return { product_id: '', quantity: 1, buscarQ: '', filtrados: [] }; },
            buscarProductos(termino) {
                const t = (termino || '').trim().toLowerCase();
                if (t.length < 1) return [];
                return this.productos.filter(p => p.name.toLowerCase().includes(t)).slice(0, 8);
            },
            elegirProducto(i, p) {
                this.filas[i].product_id = p.id;
                this.filas[i].buscarQ = '';
                this.filas[i].filtrados = [];
            },
            quitarProducto(i) { this.filas[i].product_id = ''; },
            nombreProducto(id) { return this.productos.find(p => p.id == id)?.name ?? ''; },
            stockProducto(id) { return this.productos.find(p => p.id == id)?.stock ?? 0; },
            precio(id) { return this.productos.find(p => p.id == id)?.price ?? 0; },
            get total() {
                return this.filas.reduce((s, f) => s + (this.precio(f.product_id) * (f.quantity || 0)), 0);
            },
            get completo() { return this.filas.every(f => f.product_id !== ''); }
         }"
         x-show="abierto" x-cloak
         @abrir-modal-venta.window="abierto = true" @keydown.escape.window="abierto = false">
        <div class="tarjeta modal__caja" @click.outside="abierto = false">
            <div class="modal__cabecera">
                <h3 style="font-size:var(--t-lg)">Registrar venta</h3>
                <button class="modal__cerrar" type="button" @click="abierto = false"><x-icono nombre="cerrar" /></button>
            </div>

            @if ($productos->isEmpty())
                <p style="color:var(--ceniza)">No hay productos activos en inventario todavía. Pídele al administrador que dé de alta lo que venden (agua, bebidas, ropa) en Inventario.</p>
            @else
                <form class="formulario-panel" method="POST" action="{{ route('entrenador.ventas.store') }}">
                    @csrf

                    <template x-for="(fila, i) in filas" :key="i">
                        <div class="fila-borrable">
                            <div class="formulario-panel__fila">
                                <div class="campo">
                                    <span class="campo__etiqueta">Producto</span>

                                    {{-- Mientras no se elige: buscador con lupa
                                         sobre la lista ya cargada (filtro local,
                                         mismo estilo que el buscador de cliente). --}}
                                    <div class="panel__busqueda" style="max-width:none" x-show="!fila.product_id">
                                        <x-icono nombre="lupa" />
                                        <input class="campo__control" type="search" x-model="fila.buscarQ"
                                               @input.debounce.150ms="fila.filtrados = buscarProductos(fila.buscarQ)"
                                               placeholder="Buscar producto…" autocomplete="off">
                                        <div class="buscador__lista" x-show="fila.filtrados.length" x-cloak @click.outside="fila.filtrados = []">
                                            <template x-for="p in fila.filtrados" :key="p.id">
                                                <button type="button" class="buscador__item" @click="elegirProducto(i, p)">
                                                    <span class="buscador__nombre" x-text="p.name"></span>
                                                    <span class="buscador__codigo" x-text="'stock ' + p.stock"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Elegido: se muestra y se puede cambiar. --}}
                                    <div class="aviso" x-show="fila.product_id" x-cloak
                                         style="display:flex;justify-content:space-between;align-items:center;gap:var(--e-3);margin:0">
                                        <span>
                                            <b x-text="nombreProducto(fila.product_id)"></b>
                                            <span style="color:var(--humo);font-size:var(--t-sm)" x-text="' · stock ' + stockProducto(fila.product_id)"></span>
                                        </span>
                                        <button type="button" class="btn btn--desnudo" @click="quitarProducto(i)">Cambiar</button>
                                    </div>
                                    <input type="hidden" :name="`items[${i}][product_id]`" :value="fila.product_id">
                                </div>

                                <label class="campo"><span class="campo__etiqueta">Cantidad</span>
                                    <input class="campo__control" type="number" min="1" :name="`items[${i}][quantity]`" x-model.number="fila.quantity" required></label>
                            </div>
                            <button class="btn btn--desnudo" type="button" @click="filas.length > 1 && filas.splice(i, 1)" aria-label="Quitar">
                                <x-icono nombre="papelera" />
                            </button>
                        </div>
                    </template>
                    <button class="btn btn--vidrio" type="button" @click="filas.push(nuevaFila())">
                        <x-icono nombre="agregar" /> Añadir producto
                    </button>

                    <label class="campo"><span class="campo__etiqueta">Método de pago</span>
                        <select class="campo__control" name="method" required>
                            @foreach (config('sparta.metodos_pago') as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                        </select></label>

                    <p style="font-family:var(--f-mono);font-size:var(--t-lg);text-align:right">
                        Total: S/ <span x-text="total.toFixed(2)"></span>
                    </p>

                    <div class="formulario-panel__acciones">
                        <button class="btn btn--fuego btn--bloque" type="submit" :disabled="!completo">Registrar venta</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection
