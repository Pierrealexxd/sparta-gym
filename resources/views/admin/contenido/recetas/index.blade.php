@extends('layouts.panel')

@section('titulo', 'Contenido web')
@section('subtitulo', 'Recetas · ' . $recetas->total() . ' recetas')

@section('acciones')
    <div style="display:flex;gap:var(--e-3)">
        <button class="btn btn--vidrio" type="button"
                @click="window.dispatchEvent(new CustomEvent('abrir-preview', { detail: { seccion: 'recetas' } }))">
            <x-icono nombre="ojo" /> Previsualizar
        </button>
        <button class="btn btn--fuego" type="button" @click="window.dispatchEvent(new CustomEvent('abrir-receta'))">
            <x-icono nombre="agregar" /> Nueva receta
        </button>
    </div>
@endsection

@section('contenido')
    @php
        $errorEditor = $errors->any() && old('_origen') === 'receta';
        $porcionesBase = collect(\App\Http\Controllers\Admin\RecipeController::TIPOS_PORCION)
            ->mapWithKeys(fn ($etiqueta, $tipo) => [$tipo => ['count' => 0, 'food_name' => '']])
            ->all();
        $vacios = [
            'name' => '', 'prep_minutes' => '', 'servings' => '',
            'description' => '', 'ingredients' => '', 'steps' => '',
            'tags' => '', 'porciones' => $porcionesBase, 'is_active' => true,
        ];
    @endphp

    @include('admin.contenido._pestanas')

    <form class="panel__toolbar" method="GET">
        <div class="panel__busqueda">
            <x-icono nombre="lupa" />
            <input class="campo__control" type="search" name="q" value="{{ request('q') }}"
                   placeholder="Buscar por nombre o ingrediente…">
        </div>
    </form>

    <div class="tabla-envoltorio" data-revelar>
        <table class="tabla tabla--tarjetas">
            <thead><tr><th>Receta</th><th>Porciones de mano</th><th>Tiempo</th><th>Comensales</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse ($recetas as $receta)
                    @php
                        $formasCortas = ['palma' => 'palmas', 'puno' => 'puños', 'cuenco' => 'cuencos', 'pulgar' => 'pulgares'];
                        $resumen = collect($receta->conteo)->filter()->map(fn ($n, $k) => "{$n} {$formasCortas[$k]}")->implode(' · ');
                        $porcionesDatos = array_replace($porcionesBase, $receta->portions->mapWithKeys(fn ($p) => [$p->portion_type => ['count' => (int) $p->count, 'food_name' => $p->food_name]])->all());
                    @endphp
                    <tr>
                        <td class="es-fuerte" data-etiqueta="Receta">{{ $receta->name }}</td>
                        <td style="font-family:var(--f-mono);font-size:var(--t-xs)" data-etiqueta="Porciones">{{ $resumen ?: '—' }}</td>
                        <td data-etiqueta="Tiempo">{{ $receta->prep_minutes ? $receta->prep_minutes . ' min' : '—' }}</td>
                        <td data-etiqueta="Comensales">{{ $receta->servings ?? '—' }}</td>
                        <td data-etiqueta="Estado"><span class="estado estado--{{ $receta->is_active ? 'activo' : 'inactivo' }}">{{ $receta->is_active ? 'Publicada' : 'Oculta' }}</span></td>
                        <td data-etiqueta="nada">
                            <div style="display:flex;gap:var(--e-2)">
                                @if ($receta->is_active)
                                    <button class="btn btn--desnudo" type="button" title="Despublicar"
                                            @click="$store.confirmar.abrir({
                                                accion: '{{ route('admin.recetas.ocultar', $receta) }}',
                                                metodo: 'POST',
                                                titulo: 'Ocultar receta',
                                                mensaje: '¿Ocultar {{ $receta->name }} de la biblioteca? Podrás volver a publicarla.',
                                                etiqueta: 'Ocultar'
                                            })">
                                        <x-icono nombre="ojo" />
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('admin.recetas.publicar', $receta) }}">
                                        @csrf
                                        <button class="btn btn--desnudo" type="submit" title="Publicar">
                                            <x-icono nombre="ojo-tachado" />
                                        </button>
                                    </form>
                                @endif
                                <button class="btn btn--desnudo" type="button" title="Editar"
                                        @click="window.dispatchEvent(new CustomEvent('abrir-receta', { detail: @js([
                                            'id' => $receta->id,
                                            'name' => $receta->name,
                                            'prep_minutes' => $receta->prep_minutes,
                                            'servings' => $receta->servings,
                                            'description' => $receta->description,
                                            'ingredients' => $receta->ingredients,
                                            'steps' => $receta->steps,
                                            'tags' => implode("\n", $receta->tags ?? []),
                                            'porciones' => $porcionesDatos,
                                            'is_active' => (bool) $receta->is_active,
                                        ]) }))">
                                    <x-icono nombre="lapiz" />
                                </button>
                                <button class="btn btn--desnudo" type="button" title="Eliminar"
                                        @click="$store.confirmar.abrir({
                                            accion: '{{ route('admin.recetas.destroy', $receta) }}',
                                            titulo: 'Eliminar receta',
                                            mensaje: '¿Eliminar definitivamente {{ $receta->name }}? No se puede recuperar.',
                                            etiqueta: 'Eliminar'
                                        })">
                                    <x-icono nombre="papelera" />
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="tabla__vacio"><x-estado-vacio icono="lista" texto="Sin recetas todavía." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="paginacion">{{ $recetas->links() }}</div>

    <x-modal-confirmar />

    <div class="modal__fondo"
         x-data="editorGenerico(@js([
            'abierta'   => $errorEditor,
            'editando'  => (bool) old('id'),
            'crearUrl'  => route('admin.recetas.store'),
            'editarUrl' => route('admin.recetas.update', '__ID__'),
            'base'      => $vacios,
            'fila'      => $errorEditor ? old() : $vacios,
         ]))"
         x-show="abierta" x-cloak
         @abrir-receta.window="abrir($event.detail)"
         @keydown.escape.window="cerrar()">
        <div class="tarjeta modal__caja formulario-panel" @click.outside="cerrar()">
            <div class="modal__cabecera">
                <h3 x-text="editando ? 'Editar receta' : 'Nueva receta'"></h3>
                <button class="modal__cerrar" type="button" @click="cerrar()" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
            </div>

            <form class="formulario-panel" method="POST" :action="accion">
                @csrf
                <input type="hidden" name="_method" :value="editando ? 'PUT' : ''">
                <input type="hidden" name="_origen" value="receta">
                <input type="hidden" name="id" :value="fila.id">

                @if ($errors->any() && old('_origen') === 'receta')
                    <div class="aviso aviso--error" role="alert">{{ $errors->first() }}</div>
                @endif

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Nombre</span>
                        <input class="campo__control" type="text" name="name" required x-model="fila.name"></label>
                    <label class="campo"><span class="campo__etiqueta">Tiempo de preparación (min)</span>
                        <input class="campo__control" type="number" name="prep_minutes" min="1" max="600" x-model="fila.prep_minutes"></label>
                </div>

                <label class="campo"><span class="campo__etiqueta">Comensales</span>
                    <input class="campo__control" type="number" name="servings" min="1" max="50" style="max-width:10rem" x-model="fila.servings"></label>

                <label class="campo"><span class="campo__etiqueta">Descripción</span>
                    <textarea class="campo__control" name="description" style="min-height:4rem" x-model="fila.description"></textarea></label>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Ingredientes (uno por línea)</span>
                        <textarea class="campo__control" name="ingredients" style="min-height:6rem" x-model="fila.ingredients"></textarea></label>
                    <label class="campo"><span class="campo__etiqueta">Preparación (paso a paso)</span>
                        <textarea class="campo__control" name="steps" style="min-height:6rem" x-model="fila.steps"></textarea></label>
                </div>

                <label class="campo"><span class="campo__etiqueta">Etiquetas (una por línea)</span>
                    <textarea class="campo__control" name="tags" style="min-height:4rem" placeholder="criollo&#10;sin gluten" x-model="fila.tags"></textarea></label>

                {{-- Fase 3 del plan de nutrición: mismo lenguaje de porciones de
                     mano que el diario de comidas — palma, puño, cuenco, pulgar. --}}
                <div>
                    <span class="campo__etiqueta">Porciones de mano</span>
                    <div style="display:grid;gap:var(--e-4);grid-template-columns:repeat(auto-fit, minmax(12rem, 1fr));margin-top:var(--e-2)">
                        @foreach (\App\Http\Controllers\Admin\RecipeController::TIPOS_PORCION as $tipo => $etiqueta)
                            <div style="display:flex;flex-direction:column;gap:var(--e-2)">
                                <label class="campo"><span class="campo__etiqueta" style="font-size:var(--t-xs)">{{ $etiqueta }}</span>
                                    <input class="campo__control" type="number" min="0" max="20"
                                           name="porciones[{{ $tipo }}][count]"
                                           x-model="fila.porciones['{{ $tipo }}'].count"></label>
                                <input class="campo__control" type="text" placeholder="Alimento (opcional)"
                                       name="porciones[{{ $tipo }}][food_name]"
                                       x-model="fila.porciones['{{ $tipo }}'].food_name">
                            </div>
                        @endforeach
                    </div>
                </div>

                <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
                    <input type="checkbox" name="is_active" value="1" x-model="fila.is_active">
                    Publicada en la biblioteca
                </label>

                <div class="formulario-panel__acciones">
                    <button class="btn btn--vidrio" type="button" @click="cerrar()">Cancelar</button>
                    <button class="btn btn--fuego" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>
@endsection