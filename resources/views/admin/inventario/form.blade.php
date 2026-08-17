@extends('layouts.panel')

@section('titulo', $producto->exists ? 'Editar producto' : 'Nuevo producto')

@section('contenido')
    <form class="tarjeta formulario-panel" method="POST" enctype="multipart/form-data"
          action="{{ $producto->exists ? route('admin.inventario.update', $producto) : route('admin.inventario.store') }}">
        @csrf
        @if ($producto->exists) @method('PUT') @endif

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Nombre</span>
                <input class="campo__control" type="text" name="name" required value="{{ old('name', $producto->name) }}"></label>
<label class="campo"><span class="campo__etiqueta">SKU (opcional)</span>
                <input class="campo__control" type="text" name="sku" id="sku"
                    value="{{ old('sku', $producto->sku) }}"
                    {{ old('generar_sku_automatico') ? 'readonly' : '' }}
                    @error('sku')<span class="campo__error">{{ $message }}</span>@enderror>
            </label>
</div>

        <div class="formulario-panel__fila">
            <label class="campo">
                <input type="checkbox" name="generar_sku_automatico" id="generar_sku_automatico"
                    value="1" checked>
                Generar SKU automáticamente
            </label>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const chk = document.getElementById('generar_sku_automatico');
                const skuInput = document.getElementById('sku');
                if (!chk) return;
                if (chk.checked) {
                    skuInput.readOnly = true;
                    skuInput.value = '';
                } else {
                    skuInput.readOnly = false;
                }
                chk.addEventListener('change', function() {
                    if (this.checked) {
                        skuInput.readOnly = true;
                        skuInput.value = '';
                    } else {
                        skuInput.readOnly = false;
                    }
                });
            });
        </script>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Categoría</span>
                <select class="campo__control" name="category" required>
                    @foreach (\App\Http\Controllers\Admin\ProductController::CATEGORIAS as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('category', $producto->category ?? 'suplemento') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select></label>
        </div>

        <label class="campo"><span class="campo__etiqueta">Descripción</span>
            <textarea class="campo__control" name="description" style="min-height:6rem">{{ old('description', $producto->description) }}</textarea></label>

        <label class="campo"><span class="campo__etiqueta">Foto</span>
            <input class="campo__control" type="file" name="imagen" accept="image/*"></label>
        @if ($producto->image_path)
            <img src="{{ asset('storage/' . $producto->image_path) }}" alt="" style="width:4rem;height:4rem;border-radius:var(--r-2);object-fit:cover;margin-top:var(--e-2)">
        @endif

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Costo (S/)</span>
                <input class="campo__control" type="number" step="0.01" min="0" name="cost_price" required value="{{ old('cost_price', $producto->cost_price) }}"></label>
            <label class="campo"><span class="campo__etiqueta">Precio de venta (S/)</span>
                <input class="campo__control" type="number" step="0.01" min="0" name="sale_price" required value="{{ old('sale_price', $producto->sale_price) }}"></label>
            <label class="campo"><span class="campo__etiqueta">Stock mínimo</span>
                <input class="campo__control" type="number" min="0" name="min_stock" value="{{ old('min_stock', $producto->min_stock ?? 0) }}"></label>
        </div>

        @if (! $producto->exists)
            <label class="campo"><span class="campo__etiqueta">Stock inicial</span>
                <input class="campo__control" type="number" min="0" name="stock_inicial" value="{{ old('stock_inicial', 0) }}"></label>
            <p style="color:var(--ceniza);font-size:var(--t-xs);margin-top:calc(var(--e-2) * -1)">
                Queda registrado como un movimiento de entrada, no como el número directo.
            </p>
        @endif

        <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $producto->is_active ?? true))]
            Producto activo
        </label>

        <div class="formulario-panel__acciones">
            <a class="btn btn--vidrio" href="{{ route('admin.inventario.index') }}">Cancelar</a>
            <button class="btn btn--fuego" type="submit">Guardar</button>
        </div>
    </form>

    @if ($producto->exists)
        <div class="tarjeta" style="margin-top:var(--e-6)">
            <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-2)">Stock actual: {{ $producto->stock }}</h3>
            <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-4)">
                El stock nunca se edita directo — cada cambio queda en el historial de movimientos.
            </p>
            <form class="formulario-panel" method="POST" action="{{ route('admin.inventario.movimiento', $producto) }}">
                @csrf
                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Tipo</span>
                        <select class="campo__control" name="type" required>
                            <option value="entrada">Entrada (ingreso de mercancía)</option>
                            <option value="ajuste">Ajuste (merma, conteo, corrección)</option>
                        </select></label>
                    <label class="campo"><span class="campo__etiqueta">Cantidad</span>
                        <input class="campo__control" type="number" name="quantity" required placeholder="Positivo suma, negativo resta">
                        @error('quantity')<span class="campo__error">{{ $message }}</span>@enderror
                    </label>
                </div>
                <label class="campo"><span class="campo__etiqueta">Motivo (opcional)</span>
                    <input class="campo__control" type="text" name="reason"></label>
                <div class="formulario-panel__acciones">
                    <button class="btn btn--vidrio" type="submit">Registrar movimiento</button>
                </div>
            </form>
        </div>
    @endif
@endsection
