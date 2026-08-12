@extends('layouts.panel')

@section('titulo', 'Inventario')
@section('subtitulo', $productos->total() . ' productos')

@section('acciones')
    @if (auth()->user()->tienePermiso('inventario.gestionar'))
        <button class="btn btn--fuego" type="button" @click="window.dispatchEvent(new CustomEvent('abrir-inventario'))">
            <x-icono nombre="agregar" /> Nuevo producto
        </button>
    @endif
@endsection

@section('contenido')
    @php
        // El modal nuevo/editar (abajo) se reabre solo si la validación falló
        // en su propio formulario — ver _origen dentro del modal.
        $errorEditor = $errors->any() && old('_origen') === 'inventario';
        $vacios = [
            'name' => '', 'sku' => '', 'category' => 'suplemento', 'description' => '',
            'cost_price' => '', 'sale_price' => '', 'min_stock' => '0',
            'stock_inicial' => '', 'is_active' => true,
        ];
    @endphp

    <form class="panel__toolbar" method="GET">
        <div class="panel__busqueda">
            <x-icono nombre="lupa" />
            <input class="campo__control" type="search" name="q" value="{{ request('q') }}"
                   placeholder="Buscar por nombre, SKU o categoría…">
        </div>
    </form>

    <div class="tabla-envoltorio" data-revelar>
        <table class="tabla tabla--tarjetas">
            <thead><tr><th>Producto</th><th class="tabla__oculta-movil">Categoría</th><th>Precio</th><th>Stock</th><th></th></tr></thead>
            <tbody>
                @forelse ($productos as $producto)
                    <tr>
                        <td class="es-fuerte" data-etiqueta="Producto">
                            {{ $producto->name }}
                            @if ($producto->sku)<br><span style="color:var(--ceniza);font-size:var(--t-xs);font-family:var(--f-mono)">{{ $producto->sku }}</span>@endif
                        </td>
                        <td class="tabla__oculta-movil" data-etiqueta="Categoría">{{ \App\Http\Controllers\Admin\ProductController::CATEGORIAS[$producto->category] ?? $producto->category }}</td>
                        <td data-etiqueta="Precio">S/ {{ number_format($producto->sale_price, 2) }}</td>
                        <td data-etiqueta="Stock">
                            {{ $producto->stock }}
                            @if ($producto->necesita_reposicion)
                                <span class="etiqueta etiqueta--fuego">Reponer</span>
                            @endif
                        </td>
                        <td data-etiqueta="nada">
                            @if (auth()->user()->tienePermiso('inventario.gestionar'))
                                <button class="btn btn--desnudo" type="button"
                                        @click="window.dispatchEvent(new CustomEvent('abrir-inventario', { detail: @js([
                                            'id' => $producto->id,
                                            'name' => $producto->name,
                                            'sku' => $producto->sku,
                                            'category' => $producto->category,
                                            'description' => $producto->description,
                                            'cost_price' => (float) $producto->cost_price,
                                            'sale_price' => (float) $producto->sale_price,
                                            'min_stock' => $producto->min_stock,
                                            'stock' => $producto->stock,
                                            'is_active' => (bool) $producto->is_active,
                                        ]) }))">
                                    <x-icono nombre="lapiz" />
                                </button>
                                <button class="btn btn--desnudo" type="button"
                                        @click="$store.confirmar.abrir({
                                            accion: '{{ route('admin.inventario.destroy', $producto) }}',
                                            titulo: 'Desactivar producto',
                                            mensaje: '¿Desactivar {{ $producto->name }}? Dejará de poder venderse.',
                                            etiqueta: 'Desactivar'
                                        })">
                                    <x-icono nombre="papelera" />
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="tabla__vacio"><x-estado-vacio icono="caja" texto="Sin productos todavía." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="paginacion">{{ $productos->links() }}</div>

    <x-modal-confirmar />

    {{-- Nuevo / editar producto como modal (antes: admin.inventario.form con
         sus dos pantallas). El botón de la fila despacha el registro por el
         evento abrir-inventario; tras un error de validación, _origen vuelve
         a abrir este modal con lo tecleado. El cuadro de movimiento de stock
         (que vivía solo en la página de edición) se conserva aquí, visible
         solo al editar. --}}
    <div class="modal__fondo"
         x-data="editorGenerico(@js([
            'abierta'   => $errorEditor,
            'editando'  => (bool) old('id'),
            'crearUrl'  => route('admin.inventario.store'),
            'editarUrl' => route('admin.inventario.update', '__ID__'),
            'base'      => $vacios,
            'fila'      => $errorEditor ? old() : $vacios,
            'extras'    => ['movimientoUrl' => route('admin.inventario.movimiento', '__ID__')],
         ]))"
         x-show="abierta" x-cloak
         @abrir-inventario.window="abrir($event.detail)"
         @keydown.escape.window="cerrar()">
        <div class="tarjeta modal__caja formulario-panel" @click.outside="cerrar()">
            <div class="modal__cabecera">
                <h3 x-text="editando ? 'Editar producto' : 'Nuevo producto'"></h3>
                <button class="modal__cerrar" type="button" @click="cerrar()" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
            </div>

            <form class="formulario-panel" method="POST" :action="accion" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_method" :value="editando ? 'PUT' : ''">
                <input type="hidden" name="_origen" value="inventario">
                <input type="hidden" name="id" :value="fila.id">

                @if ($errors->any() && old('_origen') === 'inventario')
                    <div class="aviso aviso--error" role="alert">{{ $errors->first() }}</div>
                @endif

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Nombre</span>
                        <input class="campo__control" type="text" name="name" required x-model="fila.name"></label>
                    <label class="campo"><span class="campo__etiqueta">SKU (opcional)</span>
                        <input class="campo__control" type="text" name="sku" x-model="fila.sku"></label>
                </div>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Categoría</span>
                        <select class="campo__control" name="category" required x-model="fila.category">
                            @foreach (\App\Http\Controllers\Admin\ProductController::CATEGORIAS as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select></label>
                    <label class="campo"><span class="campo__etiqueta">Foto</span>
                        <input class="campo__control" type="file" name="imagen" accept="image/*"></label>
                </div>

                <label class="campo"><span class="campo__etiqueta">Descripción</span>
                    <textarea class="campo__control" name="description" style="min-height:6rem" x-model="fila.description"></textarea></label>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Costo (S/)</span>
                        <input class="campo__control" type="number" step="0.01" min="0" name="cost_price" required x-model="fila.cost_price"></label>
                    <label class="campo"><span class="campo__etiqueta">Precio de venta (S/)</span>
                        <input class="campo__control" type="number" step="0.01" min="0" name="sale_price" required x-model="fila.sale_price"></label>
                    <label class="campo"><span class="campo__etiqueta">Stock mínimo</span>
                        <input class="campo__control" type="number" min="0" name="min_stock" x-model="fila.min_stock"></label>
                </div>

                <label class="campo" x-show="!editando" x-cloak>
                    <span class="campo__etiqueta">Stock inicial</span>
                    <input class="campo__control" type="number" min="0" name="stock_inicial" x-model="fila.stock_inicial">
                    <span style="color:var(--ceniza);font-size:var(--t-xs)">Queda registrado como un movimiento de entrada, no como el número directo.</span>
                </label>

                <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
                    <input type="checkbox" name="is_active" value="1" x-model="fila.is_active">
                    Producto activo
                </label>

                <div class="formulario-panel__acciones">
                    <button class="btn btn--vidrio" type="button" @click="cerrar()">Cancelar</button>
                    <button class="btn btn--fuego" type="submit">Guardar</button>
                </div>
            </form>

            {{-- Movimiento de stock: solo al editar. El stock nunca se toca
                 directo — la verdad vive en stock_movements (ver AGENTS.md). --}}
            <div class="formulario-panel" x-show="editando" x-cloak
                 style="margin-top:var(--e-5);padding-top:var(--e-5);border-top:1px solid var(--acero)">
                <h3 style="font-size:var(--t-lg)">Stock actual: <span x-text="fila.stock ?? '—'"></span></h3>
                <p style="color:var(--ceniza);font-size:var(--t-sm)">
                    El stock nunca se edita directo — cada cambio queda en el historial de movimientos.
                </p>
                <form class="formulario-panel" method="POST" :action="movimientoUrl.replace('__ID__', fila.id)">
                    @csrf
                    <div class="formulario-panel__fila">
                        <label class="campo"><span class="campo__etiqueta">Tipo</span>
                            <select class="campo__control" name="type" required>
                                <option value="entrada">Entrada (ingreso de mercancía)</option>
                                <option value="ajuste">Ajuste (merma, conteo, corrección)</option>
                            </select></label>
                        <label class="campo"><span class="campo__etiqueta">Cantidad</span>
                            <input class="campo__control" type="number" name="quantity" required placeholder="Positivo suma, negativo resta"></label>
                    </div>
                    <label class="campo"><span class="campo__etiqueta">Motivo (opcional)</span>
                        <input class="campo__control" type="text" name="reason"></label>
                    <div class="formulario-panel__acciones">
                        <button class="btn btn--vidrio" type="submit">Registrar movimiento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
