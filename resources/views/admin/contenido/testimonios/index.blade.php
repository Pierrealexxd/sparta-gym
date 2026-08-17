@extends('layouts.panel')

@section('titulo', 'Contenido web')
@section('subtitulo', $testimonios->total() . ' testimonios')

@section('acciones')
    <div style="display:flex;gap:var(--e-3)">
        <button class="btn btn--vidrio" type="button"
                @click="window.dispatchEvent(new CustomEvent('abrir-preview', { detail: { seccion: 'testimonios' } }))">
            <x-icono nombre="ojo" /> Previsualizar
        </button>
        <button class="btn btn--fuego" type="button" @click="window.dispatchEvent(new CustomEvent('abrir-testimonio'))">
            <x-icono nombre="agregar" /> Nuevo testimonio
        </button>
    </div>
@endsection

@section('contenido')
    @php
        $errorEditor = $errors->any() && old('_origen') === 'testimonio';
        $vacios = [
            'author' => '', 'role' => '', 'content' => '', 'rating' => '5',
            'sort_order' => '0', 'is_published' => true,
            'member_nombre' => null, 'foto_existente' => null,
        ];
    @endphp

    @include('admin.contenido._pestanas')

    <form class="panel__toolbar" method="GET" style="margin-bottom:var(--e-6)">
        <div class="panel__busqueda">
            <x-icono nombre="lupa" />
            <input class="campo__control" type="search" name="q" value="{{ request('q') }}"
                   placeholder="Buscar por autor o reseña…">
        </div>
    </form>

    @if ($pendientes->isNotEmpty())
        <div class="tarjeta" style="margin-bottom:var(--e-6);border-color:var(--brasa)" data-revelar>
            <h3 style="font-size:var(--t-lg);margin-bottom:var(--e-3)">
                <x-icono nombre="campana" /> Pendientes de aprobación
                <span class="etiqueta etiqueta--fuego">{{ $pendientes->count() }}</span>
            </h3>
            <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-4)">
                Reseñas enviadas por clientes desde su panel. No aparecen en la web hasta que las publiques con el ojito.
            </p>
            <div class="tabla-envoltorio">
                <table class="tabla tabla--tarjetas">
                    <thead><tr><th>Socio</th><th>Reseña</th><th>Calificación</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($pendientes as $testimonio)
                            <tr>
                                <td class="es-fuerte" data-etiqueta="Socio">{{ $testimonio->author }}<br><span style="color:var(--ceniza);font-size:var(--t-xs)">{{ $testimonio->role }}</span></td>
                                <td style="max-width:28rem" data-etiqueta="Reseña">{{ $testimonio->content }}</td>
                                <td data-etiqueta="Calificación">{{ str_repeat('★', $testimonio->rating) }}</td>
                                <td data-etiqueta="nada">
                                    <div style="display:flex;gap:var(--e-2)">
                                        <form method="POST" action="{{ route('admin.testimonios.publicar', $testimonio) }}">
                                            @csrf
                                            <button class="btn btn--desnudo" type="submit" title="Publicar">
                                                <x-icono nombre="ojo-tachado" />
                                            </button>
                                        </form>
                                        <button class="btn btn--desnudo" type="button" title="Editar"
                                                @click="window.dispatchEvent(new CustomEvent('abrir-testimonio', { detail: @js([
                                                    'id' => $testimonio->id,
                                                    'author' => $testimonio->author,
                                                    'role' => $testimonio->role,
                                                    'content' => $testimonio->content,
                                                    'rating' => $testimonio->rating,
                                                    'sort_order' => $testimonio->sort_order,
                                                    'is_published' => (bool) $testimonio->is_published,
                                                    'member_nombre' => $testimonio->member?->full_name,
                                                    'foto_existente' => $testimonio->photo_path ? asset('storage/' . $testimonio->photo_path) : null,
                                                ]) }))">
                                            <x-icono nombre="lapiz" />
                                        </button>
                                        <button class="btn btn--desnudo" type="button" title="Eliminar"
                                                @click="$store.confirmar.abrir({
                                                    accion: '{{ route('admin.testimonios.destroy', $testimonio) }}',
                                                    titulo: 'Eliminar testimonio',
                                                    mensaje: '¿Eliminar definitivamente este testimonio? No se puede recuperar.',
                                                    etiqueta: 'Eliminar'
                                                })">
                                            <x-icono nombre="papelera" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="tabla-envoltorio" data-revelar>
        <table class="tabla tabla--tarjetas">
            <thead><tr><th>Autor</th><th>Reseña</th><th>Calificación</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse ($testimonios as $testimonio)
                    <tr>
                        <td class="es-fuerte" data-etiqueta="Autor">{{ $testimonio->author }} @if($testimonio->member_id)<span class="etiqueta">Socio</span>@endif</td>
                        <td style="max-width:28rem" data-etiqueta="Reseña">{{ \Illuminate\Support\Str::limit($testimonio->content, 90) }}</td>
                        <td data-etiqueta="Calificación">{{ str_repeat('★', $testimonio->rating) }}</td>
                        <td data-etiqueta="Estado"><span class="estado estado--{{ $testimonio->is_published ? 'activo' : 'inactivo' }}">{{ $testimonio->is_published ? 'Publicado' : 'Oculto' }}</span></td>
                        <td data-etiqueta="nada">
                            <div style="display:flex;gap:var(--e-2)">
                                @if ($testimonio->is_published)
                                    <button class="btn btn--desnudo" type="button" title="Despublicar"
                                            @click="$store.confirmar.abrir({
                                                accion: '{{ route('admin.testimonios.ocultar', $testimonio) }}',
                                                metodo: 'POST',
                                                titulo: 'Ocultar testimonio',
                                                mensaje: '¿Ocultar este testimonio de la web? Podrás volver a publicarlo.',
                                                etiqueta: 'Ocultar'
                                            })">
                                        <x-icono nombre="ojo" />
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('admin.testimonios.publicar', $testimonio) }}">
                                        @csrf
                                        <button class="btn btn--desnudo" type="submit" title="Publicar">
                                            <x-icono nombre="ojo-tachado" />
                                        </button>
                                    </form>
                                @endif
                                <button class="btn btn--desnudo" type="button" title="Editar"
                                        @click="window.dispatchEvent(new CustomEvent('abrir-testimonio', { detail: @js([
                                            'id' => $testimonio->id,
                                            'author' => $testimonio->author,
                                            'role' => $testimonio->role,
                                            'content' => $testimonio->content,
                                            'rating' => $testimonio->rating,
                                            'sort_order' => $testimonio->sort_order,
                                            'is_published' => (bool) $testimonio->is_published,
                                            'member_nombre' => $testimonio->member?->full_name,
                                            'foto_existente' => $testimonio->photo_path ? asset('storage/' . $testimonio->photo_path) : null,
                                        ]) }))">
                                    <x-icono nombre="lapiz" />
                                </button>
                                <button class="btn btn--desnudo" type="button" title="Eliminar"
                                        @click="$store.confirmar.abrir({
                                            accion: '{{ route('admin.testimonios.destroy', $testimonio) }}',
                                            titulo: 'Eliminar testimonio',
                                            mensaje: '¿Eliminar definitivamente este testimonio? No se puede recuperar.',
                                            etiqueta: 'Eliminar'
                                        })">
                                    <x-icono nombre="papelera" />
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="tabla__vacio"><x-estado-vacio icono="estrella" texto="Sin testimonios todavía." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="paginacion">{{ $testimonios->links() }}</div>

    <x-modal-confirmar />

    <div class="modal__fondo"
         x-data="editorGenerico(@js([
            'abierta'   => $errorEditor,
            'editando'  => (bool) old('id'),
            'crearUrl'  => route('admin.testimonios.store'),
            'editarUrl' => route('admin.testimonios.update', '__ID__'),
            'base'      => $vacios,
            'fila'      => $errorEditor ? old() : $vacios,
         ]))"
         x-show="abierta" x-cloak
         @abrir-testimonio.window="abrir($event.detail)"
         @keydown.escape.window="cerrar()">
        <div class="tarjeta modal__caja formulario-panel" @click.outside="cerrar()">
            <div class="modal__cabecera">
                <h3 x-text="editando ? 'Editar testimonio' : 'Nuevo testimonio'"></h3>
                <button class="modal__cerrar" type="button" @click="cerrar()" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
            </div>

            <form class="formulario-panel" method="POST" :action="accion" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_method" :value="editando ? 'PUT' : ''">
                <input type="hidden" name="_origen" value="testimonio">
                <input type="hidden" name="id" :value="fila.id">

                @if ($errors->any() && old('_origen') === 'testimonio')
                    <div class="aviso aviso--error" role="alert">{{ $errors->first() }}</div>
                @endif

                <p x-show="fila.member_nombre" x-cloak style="color:var(--ceniza);font-size:var(--t-sm)">
                    Enviado por el socio <strong x-text="fila.member_nombre"></strong> desde su panel.
                </p>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Autor</span>
                        <input class="campo__control" type="text" name="author" required x-model="fila.author"></label>
                    <label class="campo"><span class="campo__etiqueta">Rol / antigüedad</span>
                        <input class="campo__control" type="text" name="role" placeholder="Socio desde 2023" x-model="fila.role"></label>
                </div>

                <label class="campo"><span class="campo__etiqueta">Testimonio</span>
                    <textarea class="campo__control" name="content" required style="min-height:7rem" x-model="fila.content"></textarea></label>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Calificación (1-5)</span>
                        <input class="campo__control" type="number" name="rating" min="1" max="5" required x-model="fila.rating"></label>
                    <label class="campo"><span class="campo__etiqueta">Orden</span>
                        <input class="campo__control" type="number" name="sort_order" min="0" x-model="fila.sort_order"></label>
                </div>

                <label class="campo"><span class="campo__etiqueta">Foto (opcional)</span>
                    <input class="campo__control" type="file" name="foto" accept="image/*"></label>
                <div x-show="editando && fila.foto_existente" x-cloak>
                    <img :src="fila.foto_existente" alt="" style="width:4rem;height:4rem;border-radius:50%;object-fit:cover;margin-top:var(--e-2)">
                </div>

                <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
                    <input type="checkbox" name="is_published" value="1" x-model="fila.is_published">
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