@extends('layouts.panel')

@section('titulo', $producto->name)
@section('subtitulo', 'SKU: ' . ($producto->sku ?? '—'))

@section('acciones')
    <div style="display:flex;gap:var(--e-3)">
        @if (auth()->user()->tienePermiso('inventario.gestionar'))
            <button class="btn btn--vidrio" type="button"
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
                <x-icono nombre="lapiz" /> Editar
            </button>
            <button class="btn btn--vidrio" type="button"
                    @click="$store.confirmar.abrir({
                        accion: '{{ route('admin.inventario.destroy', $producto) }}',
                        titulo: 'Desactivar producto',
                        mensaje: '¿Desactivar {{ $producto->name }}? Dejará de poder venderse.',
                        etiqueta: 'Desactivar'
                    })">
                <x-icono nombre="papelera" />
            </button>
        @endif
        <a class="btn btn--fuego" href="{{ route('admin.inventario.index') }}"><x-icono nombre="flecha-der" style="transform:rotate(180deg)" /> Volver</a>
    </div>
@endsection

@section('contenido')
    <div class="ficha">
        <div class="ficha__resumen">
            <div class="tarjeta" style="padding:var(--e-5)">
                <div style="margin-bottom:var(--e-4);display:flex;align-items:center;gap:var(--e-4)">
                    @if ($producto->image_path)
                        <img src="{{ asset('storage/' . $producto->image_path) }}" alt="{{ $producto->name }}"
                             style="width:4.5rem;height:4.5rem;border-radius:var(--r-2);object-fit:cover">
                    @else
                        <span class="ficha__iniciales">{{ mb_strtoupper(mb_substr($producto->name, 0, 2)) }}</span>
                    @endif
                    <div>
                        <b style="font-family:var(--f-display);font-size:var(--t-lg)">{{ $producto->name }}</b>
                        @if ($producto->sku)
                            <div style="font-family:var(--f-mono);font-size:var(--t-xs);color:var(--ceniza)">{{ $producto->sku }}</div>
                        @endif
                    </div>
                </div>

                <div class="ficha__dato"><span>Categoría</span><span>{{ \App\Http\Controllers\Admin\ProductController::CATEGORIAS[$producto->category] ?? $producto->category }}</span></div>
                <div class="ficha__dato"><span>Costo</span><span>S/ {{ number_format($producto->cost_price, 2) }}</span></div>
                <div class="ficha__dato"><span>Precio de venta</span><span>S/ {{ number_format($producto->sale_price, 2) }}</span></div>
                <div class="ficha__dato"><span>Margen</span><span>S/ {{ number_format($producto->margen, 2) }}</span></div>
                <div class="ficha__dato">
                    <span>Stock</span>
                    <span>
                        <b style="font-family:var(--f-mono)">{{ $producto->stock }}</b>
                        @php
                            $estadoStock = $producto->estado_stock;
                            $tono = ['bajo' => 'bronce', 'critico' => 'fuego', 'agotado' => 'fuego'][$estadoStock] ?? null;
                        @endphp
                        @if ($tono)
                            <span class="etiqueta etiqueta--{{ $tono }}">
                                {{ ['bajo' => 'Stock bajo', 'critico' => 'Por agotarse', 'agotado' => 'Agotado'][$estadoStock] }}
                            </span>
                        @endif
                    </span>
                </div>
                <div class="ficha__dato"><span>Stock mínimo</span><span>{{ $producto->min_stock }}</span></div>
                <div class="ficha__dato"><span>Estado</span><span class="estado estado--{{ $producto->is_active ? 'activo' : 'inactivo' }}">{{ $producto->is_active ? 'Activo' : 'Inactivo' }}</span></div>
            </div>
        </div>

        <div>
            <article class="tarjeta" style="padding:var(--e-5)">
                <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-3)">Descripción</h3>
                <p style="color:var(--hueso);line-height:1.6">{{ $producto->description ?: 'Sin descripción.' }}</p>
            </article>

            <article class="tarjeta" style="padding:var(--e-5);margin-top:var(--e-5)">
                <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-3)">Historial de movimientos</h3>
                <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-4)">
                    El stock nunca se edita directo — cada cambio queda aquí, en el libro mayor.
                </p>

                <div class="tabla-envoltorio">
                    <table class="tabla tabla--tarjetas">
                        <thead><tr><th>Fecha</th><th>Tipo</th><th class="tabla__oculta-movil">Cantidad</th><th class="tabla__oculta-movil">Stock después</th><th>Motivo</th><th class="tabla__oculta-movil">Quién</th></tr></thead>
                        <tbody>
                            @forelse ($movimientos as $movimiento)
                                <tr>
                                    <td class="es-fuerte" style="font-family:var(--f-mono);font-size:var(--t-xs)" data-etiqueta="Fecha">{{ $movimiento->created_at->format('d/m/y H:i') }}</td>
                                    <td data-etiqueta="Tipo">
                                        <span class="estado estado--{{ $movimiento->type === 'entrada' ? 'activo' : ($movimiento->type === 'salida' ? 'inactivo' : 'suspendido') }}">
                                            {{ ucfirst($movimiento->type) }}
                                        </span>
                                    </td>
                                    <td class="tabla__oculta-movil" style="font-family:var(--f-mono)" data-etiqueta="Cantidad">{{ $movimiento->quantity }}</td>
                                    <td class="tabla__oculta-movil" style="font-family:var(--f-mono)" data-etiqueta="Stock después">{{ $movimiento->stock_after }}</td>
                                    <td data-etiqueta="Motivo">{{ $movimiento->reason ?? '—' }}</td>
                                    <td class="tabla__oculta-movil" data-etiqueta="Quién">{{ $movimiento->user?->name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="tabla__vacio"><x-estado-vacio icono="caja" texto="Sin movimientos todavía." /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="paginacion">{{ $movimientos->links() }}</div>
            </article>
        </div>
    </div>

    @if (auth()->user()->tienePermiso('inventario.gestionar'))
        {{-- El mismo modal editor de inventario/index para editar desde aquí. --}}
        <div class="modal__fondo"
             x-data="editorGenerico(@js([
                'abierta'   => false,
                'editando'  => true,
                'crearUrl'  => route('admin.inventario.store'),
                'editarUrl' => route('admin.inventario.update', '__ID__'),
                'base'      => ['name' => '', 'sku' => '', 'category' => 'suplemento', 'description' => '', 'cost_price' => '', 'sale_price' => '', 'min_stock' => '0', 'stock_inicial' => '', 'is_active' => true],
                'fila'      => [
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
                ],
                'extras'    => ['movimientoUrl' => route('admin.inventario.movimiento', '__ID__')],
             ]))"
             x-show="abierta" x-cloak
             @abrir-inventario.window="abrir($event.detail)"
             @keydown.escape.window="cerrar()">
            <div class="tarjeta modal__caja formulario-panel" @click.outside="cerrar()">
                <div class="modal__cabecera">
                    <h3>Editar producto</h3>
                    <button class="modal__cerrar" type="button" @click="cerrar()" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
                </div>

                <form class="formulario-panel" method="POST" :action="accion" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="_method" value="PUT">
                    <input type="hidden" name="_origen" value="inventario">
                    <input type="hidden" name="id" :value="fila.id">

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

                    <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
                        <input type="checkbox" name="is_active" value="1" x-model="fila.is_active">
                        Producto activo
                    </label>

                    <div class="formulario-panel__acciones">
                        <button class="btn btn--vidrio" type="button" @click="cerrar()">Cancelar</button>
                        <button class="btn btn--fuego" type="submit">Guardar</button>
                    </div>
                </form>

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

        <x-modal-confirmar />
    @endif
@endsection