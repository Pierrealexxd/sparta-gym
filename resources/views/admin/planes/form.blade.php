@extends('layouts.panel')

@section('titulo', $plan->exists ? 'Editar plan' : 'Nuevo plan')

@section('contenido')
    <form class="tarjeta formulario-panel" method="POST"
          action="{{ $plan->exists ? route('admin.planes.update', $plan) : route('admin.planes.store') }}">
        @csrf
        @if ($plan->exists) @method('PUT') @endif

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Nombre</span>
                <input class="campo__control" type="text" name="name" required value="{{ old('name', $plan->name) }}"></label>
            <label class="campo"><span class="campo__etiqueta">Lema corto</span>
                <input class="campo__control" type="text" name="tagline" value="{{ old('tagline', $plan->tagline) }}"></label>
        </div>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Precio (S/)</span>
                <input class="campo__control" type="number" step="0.01" name="price" required value="{{ old('price', $plan->price) }}"></label>
            <label class="campo"><span class="campo__etiqueta">Duración (días)</span>
                <input class="campo__control" type="number" name="duration_days" required value="{{ old('duration_days', $plan->duration_days) }}"></label>
        </div>

        <label class="campo"><span class="campo__etiqueta">Beneficios (uno por línea)</span>
            <textarea class="campo__control" name="features" style="min-height:10rem">{{ old('features', is_array($plan->features) ? implode("\n", $plan->features) : '') }}</textarea></label>

        <div style="display:flex;gap:var(--e-6)">
            <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $plan->is_featured))>
                Destacar en la web
            </label>
            <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
                <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $plan->is_public ?? true))>
                Mostrar en la web
            </label>
        </div>

        <div class="formulario-panel__acciones">
            <a class="btn btn--vidrio" href="{{ route('admin.planes.index') }}">Cancelar</a>
            <button class="btn btn--fuego" type="submit">Guardar</button>
        </div>
    </form>
@endsection
