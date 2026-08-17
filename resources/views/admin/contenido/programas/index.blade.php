@extends('layouts.panel')

@section('titulo', 'Contenido web')
@section('subtitulo', $programas->total() . ' programas')

@section('acciones')
    <div style="display:flex;gap:var(--e-3)">
        <a class="btn btn--vidrio" href="{{ route('landing') }}#programas" target="_blank" rel="noopener">
            <x-icono nombre="ojo" /> Previsualizar
        </a>
        <button class="btn btn--fuego" type="button" @click="window.dispatchEvent(new CustomEvent('abrir-programa'))">
            <x-icono nombre="agregar" /> Nuevo programa
        </button>
    </div>
@endsection

@section('contenido')
    @php
        $errorEditor = $errors->any() && old('_origen') === 'programa';
        $vacios = [
            'name' => '', 'tagline' => '', 'objective' => 'ganar_masa', 'description' => '',
            'highlights' => '', 'icon' => 'llama', 'accent_color' => '', 'duration_weeks' => '',
            'difficulty' => 'intermedio', 'sort_order' => '0', 'is_active' => true, 'is_public' => true,
        ];
        $objetivos = [
            'ganar_masa' => 'Ganar masa', 'perder_grasa' => 'Perder grasa', 'fuerza' => 'Fuerza',
            'resistencia' => 'Resistencia', 'salud' => 'Salud', 'otro' => 'Otro',
        ];
        $dificultades = ['principiante' => 'Principiante', 'intermedio' => 'Intermedio', 'avanzado' => 'Avanzado'];
    @endphp

    @include('admin.contenido._pestanas')

    <form class="panel__toolbar" method="GET">
        <div class="panel__busqueda">
            <x-icono nombre="lupa" />
            <input class="campo__control" type="search" name="q" value="{{ request('q') }}"
                   placeholder="Buscar por nombre o tagline…">
        </div>
    </form>

    <div class="tabla-envoltorio" data-revelar>
        <table class="tabla tabla--tarjetas">
            <thead><tr><th>Programa</th><th>Objetivo</th><th>Dificultad</th><th>Duración</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse ($programas as $programa)
                    <tr>
                        <td class="es-fuerte" data-etiqueta="Programa">{{ $programa->name }}</td>
                        <td data-etiqueta="Objetivo">{{ $objetivos[$programa->objective] ?? $programa->objective }}</td>
                        <td data-etiqueta="Dificultad">{{ $dificultades[$programa->difficulty] ?? $programa->difficulty }}</td>
                        <td data-etiqueta="Duración">{{ $programa->duration_weeks ? $programa->duration_weeks . ' sem.' : '—' }}</td>
                        <td data-etiqueta="Estado"><span class="estado estado--{{ $programa->is_active ? 'activo' : 'inactivo' }}">{{ $programa->is_active ? 'Publicado' : 'Oculto' }}</span></td>
                        <td data-etiqueta="nada">
                            <div style="display:flex;gap:var(--e-2)">
                                @if ($programa->is_active)
                                    <button class="btn btn--desnudo" type="button" title="Despublicar"
                                            @click="$store.confirmar.abrir({
                                                accion: '{{ route('admin.programas.ocultar', $programa) }}',
                                                metodo: 'POST',
                                                titulo: 'Ocultar programa',
                                                mensaje: '¿Ocultar este programa de la web? Podrás volver a publicarlo.',
                                                etiqueta: 'Ocultar'
                                            })">
                                        <x-icono nombre="ojo" />
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('admin.programas.publicar', $programa) }}">
                                        @csrf
                                        <button class="btn btn--desnudo" type="submit" title="Publicar">
                                            <x-icono nombre="ojo-tachado" />
                                        </button>
                                    </form>
                                @endif
                                <a class="btn btn--desnudo" title="Rutinas base" href="{{ route('admin.programas.rutinas.index', $programa) }}">
                                    <x-icono nombre="lista" />
                                </a>
                                <button class="btn btn--desnudo" type="button" title="Editar"
                                        @click="window.dispatchEvent(new CustomEvent('abrir-programa', { detail: @js([
                                            'id' => $programa->id,
                                            'name' => $programa->name,
                                            'tagline' => $programa->tagline,
                                            'objective' => $programa->objective,
                                            'description' => $programa->description,
                                            'highlights' => implode(\"\n\", $programa->highlights ?? []),
                                            'icon' => $programa->icon,
                                            'accent_color' => $programa->accent_color,
                                            'duration_weeks' => $programa->duration_weeks,
                                            'difficulty' => $programa->difficulty,
                                            'sort_order' => $programa->sort_order,
                                            'is_active' => (bool) $programa->is_active,
                                            'is_public' => (bool) $programa->is_public,
                                        ]) }}))">
                                    <x-icono nombre="lapiz" />
                                </button>
                                <button class="btn btn--desnudo" type="button" title="Eliminar"
                                        @click="$store.confirmar.abrir({
                                            accion: '{{ route('admin.programas.destroy', $programa) }}',
                                            titulo: 'Eliminar programa',
                                            mensaje: '¿Eliminar definitivamente este programa? También se borrarán sus rutinas base.',
                                            etiqueta: 'Eliminar'
                                        })">
                                    <x-icono nombre="papelera" />
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="tabla__vacio"><x-estado-vacio icono="lista" texto="Sin programas todavía." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="paginacion">{{ $programas->links() }}</div>

    <x-modal-confirmar />

    {{-- Crear/editar como modal, mismo patrón que admin.contenido.faqs.index.
         Las opciones de objetivo/dificultad van fijas: son el enum de la
         columna en la migración, no listas administrables aparte. --}}
    <div class="modal__fondo"
         x-data="editorGenerico(@js([
            'abierta'   => $errorEditor,
            'editando'  => (bool) old('id'),
            'crearUrl'  => route('admin.programas.store'),
            'editarUrl' => route('admin.programas.update', '__ID__'),
            'base'      => $vacios,
            'fila'      => $errorEditor ? old() : $vacios,
         ]))"
         x-show="abierta" x-cloak
         @abrir-programa.window="abrir($event.detail)"
         @keydown.escape.window="cerrar()">
        <div class="tarjeta modal__caja modal__caja--ancho formulario-panel" @click.outside="cerrar()">
            <div class="modal__cabecera">
                <h3 x-text="editando ? 'Editar programa' : 'Nuevo programa'"></h3>
                <button class="modal__cerrar" type="button" @click="cerrar()" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
            </div>

            <form class="formulario-panel" method="POST" :action="accion">
                @csrf
                <input type="hidden" name="_method" :value="editando ? 'PUT' : ''">
                <input type="hidden" name="_origen" value="programa">
                <input type="hidden" name="id" :value="fila.id">

                @if ($errors->any() && old('_origen') === 'programa')
                    <div class="aviso aviso--error" role="alert">{{ $errors->first() }}</div>
                @endif

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Nombre</span>
                        <input class="campo__control" type="text" name="name" required x-model="fila.name"></label>
                    <label class="campo"><span class="campo__etiqueta">Tagline</span>
                        <input class="campo__control" type="text" name="tagline" x-model="fila.tagline"></label>
                </div>

                <label class="campo"><span class="campo__etiqueta">Descripción</span>
                    <textarea class="campo__control" name="description" required style="min-height:8rem" x-model="fila.description"></textarea></label>

                <label class="campo"><span class="campo__etiqueta">Características (una por línea)</span>
                    <textarea class="campo__control" name="highlights" style="min-height:6rem" x-model="fila.highlights"></textarea></label>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Objetivo</span>
                        <select class="campo__control" name="objective" x-model="fila.objective">
                            @foreach ($objetivos as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select></label>
                    <label class="campo"><span class="campo__etiqueta">Dificultad</span>
                        <select class="campo__control" name="difficulty" x-model="fila.difficulty">
                            @foreach ($dificultades as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select></label>
                </div>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Ícono</span>
                        <select class="campo__control" name="icon" x-model="fila.icon">
                            <option value="llama">Llama</option>
                            <option value="rayo">Rayo</option>
                            <option value="pesa">Pesa</option>
                            <option value="objetivo">Objetivo</option>
                            <option value="escudo">Escudo</option>
                        </select></label>
                    <label class="campo"><span class="campo__etiqueta">Duración (semanas)</span>
                        <input class="campo__control" type="number" name="duration_weeks" min="1" max="52" x-model="fila.duration_weeks"></label>
                    <label class="campo"><span class="campo__etiqueta">Orden</span>
                        <input class="campo__control" type="number" name="sort_order" min="0" x-model="fila.sort_order"></label>
                </div>

                <div style="display:flex;gap:var(--e-5)">
                    <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
                        <input type="checkbox" name="is_active" value="1" x-model="fila.is_active">
                        Publicado en la web
                    </label>
                    <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
                        <input type="checkbox" name="is_public" value="1" x-model="fila.is_public">
                        Visible para todos los gimnasios
                    </label>
                </div>

                <div class="formulario-panel__acciones">
                    <button class="btn btn--vidrio" type="button" @click="cerrar()">Cancelar</button>
                    <button class="btn btn--fuego" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>
@endsection
