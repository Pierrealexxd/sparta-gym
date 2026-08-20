@extends('layouts.panel')

@section('titulo', 'Editar Venta')
@section('subtitulo', 'Modificar los datos de la venta')

@section('contenido')
<div class="panel__toolbar">
    <a class="btn btn--vidrio" href="{{ route('admin.ventas.index') }}">
        <x-icono nombre="atras" /> Volver al listado
    </a>
</div>

<div class="tarjeta">
    <h2 style="font-size:var(--t-lg)">Editar Venta {{ $venta->number }}</h2>

    <form method="POST" action="{{ route('admin.ventas.update', $venta) }}">
        @csrf
        @method('PUT')

        <input type="hidden" name="venta_id" value="{{ $venta->id }}">

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Concepto</span>
                <input class="campo__control" type="text" name="concept" value="{{ old('concept', $venta->concept) }}" maxlength="120"
                       placeholder="Descripción de la venta">
            </label>
        </div>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Método de pago</span>
                <select class="campo__control" name="method">
                    @foreach (config('sparta.metodos_pago') as $v => $l)
                        <option value="{{ $v }}" {{ old('method', $venta->method) == $v ? 'selected' : '' }}>
                            {{ $l }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Estado</span>
                <select class="campo__control" name="status">
                    <option value="completada" {{ old('status', $venta->status) == 'completada' ? 'selected' : '' }}>Completada</option>
                    <option value="anulada" {{ old('status', $venta->status) == 'anulada' ? 'selected' : '' }}>Anulada</option>
                </select>
            </label>
        </div>

        <div class="formulario-panel__acciones">
            <button class="btn btn--fuego btn--bloque" type="submit">Guardar cambios</button>
            <a class="btn btn--desnudo" href="{{ route('admin.ventas.index') })">Cancelar</a>
        </div>
    </form>
</div>
@endsection