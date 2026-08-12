@extends('layouts.panel')

@section('titulo', 'Contenido web')
@section('subtitulo', 'Biblioteca · ' . $ejercicios->total() . ' ejercicios')

@section('acciones')
    <button class="btn btn--fuego" type="button" @click="window.dispatchEvent(new CustomEvent('abrir-ejercicio'))">
        <x-icono nombre="agregar" /> Nuevo ejercicio
    </button>
@endsection

@section('contenido')
    @php
        $errorEditor = $errors->any() && old('_origen') === 'ejercicio';
        $vacios = [
            'name' => '', 'equipment' => '', 'category' => 'fuerza', 'level' => 'principiante',
            'muscle_groups' => '', 'description' => '', 'common_mistakes' => '',
            'tips' => '', 'video_url' => '', 'is_active' => true,
            'imagen_existente' => null,
        ];
    @endphp

    @include('admin.contenido._pestanas')

    <form class="panel__toolbar" method="GET">
        <div class="panel__busqueda">
            <x-icono nombre="lupa" />
            <input class="campo__control" type="search" name="q" value="{{ request('q') }}"
                   placeholder="Buscar por nombre, categoría o equipo…">
        </div>
    </form>

    <div class="tabla-envoltorio" data-revelar>
        <table class="tabla tabla--tarjetas">
            <thead><tr><th>Ejercicio</th><th>Categoría</th><th>Nivel</th><th>Video</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse ($ejercicios as $ejercicio)
                    <tr>
                        <td class="es-fuerte" data-etiqueta="Ejercicio">{{ $ejercicio->name }}</td>
                        <td data-etiqueta="Categoría">{{ \App\Http\Controllers\Admin\ExerciseController::CATEGORIAS[$ejercicio->category] ?? $ejercicio->category }}</td>
                        <td data-etiqueta="Nivel">{{ \App\Http\Controllers\Admin\ExerciseController::NIVELES[$ejercicio->level] ?? $ejercicio->level }}</td>
                        <td data-etiqueta="Video">{{ $ejercicio->video_url ? 'Sí' : '—' }}</td>
                        <td data-etiqueta="Estado"><span class="estado estado--{{ $ejercicio->is_active ? 'activo' : 'inactivo' }}">{{ $ejercicio->is_active ? 'Publicado' : 'Oculto' }}</span></td>
                        <td data-etiqueta="nada">
                            <div style="display:flex;gap:var(--e-2)">
                                @if ($ejercicio->is_active)
                                    <button class="btn btn--desnudo" type="button" title="Despublicar"
                                            @click="$store.confirmar.abrir({
                                                accion: '{{ route('admin.ejercicios.ocultar', $ejercicio) }}',
                                                metodo: 'POST',
                                                titulo: 'Ocultar ejercicio',
                                                mensaje: '¿Ocultar {{ $ejercicio->name }} de la biblioteca? Podrás volver a publicarlo.',
                                                etiqueta: 'Ocultar'
                                            })">
                                        <x-icono nombre="ojo" />
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('admin.ejercicios.publicar', $ejercicio) }}">
                                        @csrf
                                        <button class="btn btn--desnudo" type="submit" title="Publicar">
                                            <x-icono nombre="ojo-tachado" />
                                        </button>
                                    </form>
                                @endif
                                <button class="btn btn--desnudo" type="button" title="Editar"
                                        @click="window.dispatchEvent(new CustomEvent('abrir-ejercicio', { detail: @js([
                                            'id' => $ejercicio->id,
                                            'name' => $ejercicio->name,
                                            'equipment' => $ejercicio->equipment,
                                            'category' => $ejercicio->category,
                                            'level' => $ejercicio->level,
                                            'muscle_groups' => implode("\n", $ejercicio->muscle_groups ?? []),
                                            'description' => $ejercicio->description,
                                            'common_mistakes' => $ejercicio->common_mistakes,
                                            'tips' => $ejercicio->tips,
                                            'video_url' => $ejercicio->video_url,
                                            'is_active' => (bool) $ejercicio->is_active,
                                            'imagen_existente' => $ejercicio->image_path ? asset('storage/' . $ejercicio->image_path) : null,
                                        ]) }))">
                                    <x-icono nombre="lapiz" />
                                </button>
                                <button class="btn btn--desnudo" type="button" title="Eliminar"
                                        @click="$store.confirmar.abrir({
                                            accion: '{{ route('admin.ejercicios.destroy', $ejercicio) }}',
                                            titulo: 'Eliminar ejercicio',
                                            mensaje: '¿Eliminar definitivamente {{ $ejercicio->name }}? No se puede recuperar.',
                                            etiqueta: 'Eliminar'
                                        })">
                                    <x-icono nombre="papelera" />
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="tabla__vacio"><x-estado-vacio icono="pesa" texto="Sin ejercicios todavía." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="paginacion">{{ $ejercicios->links() }}</div>

    <x-modal-confirmar />

    <div class="modal__fondo"
         x-data="editorGenerico(@js([
            'abierta'   => $errorEditor,
            'editando'  => (bool) old('id'),
            'crearUrl'  => route('admin.ejercicios.store'),
            'editarUrl' => route('admin.ejercicios.update', '__ID__'),
            'base'      => $vacios,
            'fila'      => $errorEditor ? old() : $vacios,
         ]))"
         x-show="abierta" x-cloak
         @abrir-ejercicio.window="abrir($event.detail)"
         @keydown.escape.window="cerrar()">
        <div class="tarjeta modal__caja formulario-panel" @click.outside="cerrar()">
            <div class="modal__cabecera">
                <h3 x-text="editando ? 'Editar ejercicio' : 'Nuevo ejercicio'"></h3>
                <button class="modal__cerrar" type="button" @click="cerrar()" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
            </div>

            <form class="formulario-panel" method="POST" :action="accion" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_method" :value="editando ? 'PUT' : ''">
                <input type="hidden" name="_origen" value="ejercicio">
                <input type="hidden" name="id" :value="fila.id">

                @if ($errors->any() && old('_origen') === 'ejercicio')
                    <div class="aviso aviso--error" role="alert">{{ $errors->first() }}</div>
                @endif

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Nombre</span>
                        <input class="campo__control" type="text" name="name" required x-model="fila.name"></label>
                    <label class="campo"><span class="campo__etiqueta">Equipo</span>
                        <input class="campo__control" type="text" name="equipment" placeholder="Barra olímpica, sin equipo…" x-model="fila.equipment"></label>
                </div>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Categoría</span>
                        <select class="campo__control" name="category" required x-model="fila.category">
                            @foreach (\App\Http\Controllers\Admin\ExerciseController::CATEGORIAS as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select></label>
                    <label class="campo"><span class="campo__etiqueta">Nivel</span>
                        <select class="campo__control" name="level" required x-model="fila.level">
                            @foreach (\App\Http\Controllers\Admin\ExerciseController::NIVELES as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select></label>
                </div>

                <label class="campo"><span class="campo__etiqueta">Grupos musculares (uno por línea)</span>
                    <textarea class="campo__control" name="muscle_groups" style="min-height:5rem" x-model="fila.muscle_groups"></textarea></label>

                <label class="campo"><span class="campo__etiqueta">Descripción</span>
                    <textarea class="campo__control" name="description" style="min-height:5rem" x-model="fila.description"></textarea></label>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Evita (error común)</span>
                        <textarea class="campo__control" name="common_mistakes" x-model="fila.common_mistakes"></textarea></label>
                    <label class="campo"><span class="campo__etiqueta">Hazlo así</span>
                        <textarea class="campo__control" name="tips" x-model="fila.tips"></textarea></label>
                </div>

                <label class="campo"><span class="campo__etiqueta">Video (enlace de YouTube, opcional)</span>
                    <input class="campo__control" type="url" name="video_url" placeholder="https://www.youtube.com/watch?v=…" x-model="fila.video_url"></label>

                <label class="campo"><span class="campo__etiqueta">Foto (opcional)</span>
                    <input class="campo__control" type="file" name="imagen" accept="image/*"></label>
                <div x-show="editando && fila.imagen_existente" x-cloak>
                    <img :src="fila.imagen_existente" alt="" style="width:4rem;height:4rem;border-radius:var(--r-2);object-fit:cover;margin-top:var(--e-2)">
                </div>

                <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
                    <input type="checkbox" name="is_active" value="1" x-model="fila.is_active">
                    Publicado en la web
                </label>

                <div class="formulario-panel__acciones">
                    <button class="btn btn--vidrio" type="button" @click="cerrar()">Cancelar</button>
                    <button class="btn btn--fuego" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>
@endsection