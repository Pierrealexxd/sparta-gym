@extends('layouts.panel')

@section('titulo', $receta->exists ? 'Editar receta' : 'Nueva receta')

@section('contenido')
    <form class="tarjeta formulario-panel" method="POST"
          action="{{ $receta->exists ? route('admin.recetas.update', $receta) : route('admin.recetas.store') }}">
        @csrf
        @if ($receta->exists) @method('PUT') @endif

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Nombre</span>
                <input class="campo__control" type="text" name="name" required value="{{ old('name', $receta->name) }}"></label>
            <label class="campo"><span class="campo__etiqueta">Tiempo de preparación (min)</span>
                <input class="campo__control" type="number" name="prep_minutes" min="1" max="600" value="{{ old('prep_minutes', $receta->prep_minutes) }}"></label>
        </div>

        <label class="campo"><span class="campo__etiqueta">Comensales</span>
            <input class="campo__control" type="number" name="servings" min="1" max="50" style="max-width:10rem" value="{{ old('servings', $receta->servings) }}"></label>

        <label class="campo"><span class="campo__etiqueta">Descripción</span>
            <textarea class="campo__control" name="description" style="min-height:5rem">{{ old('description', $receta->description) }}</textarea></label>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Ingredientes (uno por línea)</span>
                <textarea class="campo__control" name="ingredients" style="min-height:8rem">{{ old('ingredients', $receta->ingredients) }}</textarea></label>
            <label class="campo"><span class="campo__etiqueta">Preparación (paso a paso)</span>
                <textarea class="campo__control" name="steps" style="min-height:8rem">{{ old('steps', $receta->steps) }}</textarea></label>
        </div>

        <label class="campo"><span class="campo__etiqueta">Etiquetas (una por línea)</span>
            <textarea class="campo__control" name="tags" style="min-height:4rem" placeholder="criollo&#10;sin gluten">{{ old('tags', is_array($receta->tags) ? implode("\n", $receta->tags) : '') }}</textarea></label>

        {{-- Fase 3 del plan de nutrición: mismo lenguaje de porciones de
             mano que el diario de comidas (Fase 2) — palma, puño, cuenco,
             pulgar. Un número + un alimento opcional por porción. --}}
        <div>
            <span class="campo__etiqueta">Porciones de mano</span>
            <div style="display:grid;gap:var(--e-4);grid-template-columns:repeat(auto-fit, minmax(12rem, 1fr));margin-top:var(--e-2)">
                @foreach (\App\Http\Controllers\Admin\RecipeController::TIPOS_PORCION as $tipo => $etiqueta)
                    @php $porcion = $receta->exists ? $receta->portions->firstWhere('portion_type', $tipo) : null; @endphp
                    <div style="display:flex;flex-direction:column;gap:var(--e-2)">
                        <label class="campo"><span class="campo__etiqueta" style="font-size:var(--t-xs)">{{ $etiqueta }}</span>
                            <input class="campo__control" type="number" min="0" max="20"
                                   name="porciones[{{ $tipo }}][count]"
                                   value="{{ old("porciones.$tipo.count", $porcion->count ?? 0) }}"></label>
                        <input class="campo__control" type="text" placeholder="Alimento (opcional)"
                               name="porciones[{{ $tipo }}][food_name]"
                               value="{{ old("porciones.$tipo.food_name", $porcion->food_name ?? '') }}">
                    </div>
                @endforeach
            </div>
        </div>

        <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $receta->is_active ?? true))>
            Publicada en la biblioteca
        </label>

        <div class="formulario-panel__acciones">
            <a class="btn btn--vidrio" href="{{ route('admin.recetas.index') }}">Cancelar</a>
            <button class="btn btn--fuego" type="submit">Guardar</button>
        </div>
    </form>
@endsection
