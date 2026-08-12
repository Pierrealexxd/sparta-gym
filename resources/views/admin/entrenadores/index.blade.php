@extends('layouts.panel')

@section('titulo', 'Entrenadores')
@section('subtitulo', $entrenadores->total() . ' en total')

@section('acciones')
    <button class="btn btn--fuego" type="button" @click="window.dispatchEvent(new CustomEvent('abrir-entrenador'))">
        <x-icono nombre="agregar" /> Nuevo entrenador
    </button>
@endsection

@section('contenido')
    @php
        $errorEditor = $errors->any() && old('_origen') === 'entrenador';
        $vacios = [
            'name' => '', 'email' => '', 'password' => '',
            'specialty' => '', 'years_experience' => '', 'bio' => '',
            'is_public' => true,
        ];
    @endphp

    @include('admin.configuracion._pestanas')

    <form class="panel__toolbar" method="GET">
        <div class="panel__busqueda">
            <x-icono nombre="lupa" />
            <input class="campo__control" type="search" name="q" value="{{ request('q') }}"
                   placeholder="Buscar por nombre o especialidad…">
        </div>
    </form>

    <div class="tabla-envoltorio" data-revelar>
        <table class="tabla tabla--tarjetas">
            <thead><tr><th>Nombre</th><th class="tabla__oculta-movil">Especialidad</th><th>Correo</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse ($entrenadores as $e)
                    <tr>
                        <td class="es-fuerte" data-etiqueta="Nombre">{{ $e->nombre }}</td>
                        <td class="tabla__oculta-movil" data-etiqueta="Especialidad">{{ $e->specialty ?? '—' }}</td>
                        <td data-etiqueta="Correo">{{ $e->user?->email }}</td>
                        <td data-etiqueta="Estado"><span class="estado estado--{{ $e->is_active ? 'activo' : 'inactivo' }}">{{ $e->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                        <td data-etiqueta="nada">
                            <button class="btn btn--desnudo" type="button" title="Editar"
                                    @click="window.dispatchEvent(new CustomEvent('abrir-entrenador', { detail: @js([
                                        'id' => $e->id,
                                        'name' => $e->user?->name,
                                        'email' => $e->user?->email,
                                        'specialty' => $e->specialty,
                                        'years_experience' => $e->years_experience,
                                        'bio' => $e->bio,
                                        'is_public' => (bool) $e->is_public,
                                    ]) }))">
                                <x-icono nombre="lapiz" />
                            </button>
                            @if ($e->is_active)
                                <button class="btn btn--desnudo" type="button"
                                        @click="$store.confirmar.abrir({
                                            accion: '{{ route('admin.entrenadores.destroy', $e) }}',
                                            titulo: 'Desactivar entrenador',
                                            mensaje: '¿Desactivar a {{ $e->nombre }}? Dejará de aparecer en el panel.',
                                            etiqueta: 'Desactivar'
                                        })">
                                    <x-icono nombre="papelera" />
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="tabla__vacio"><x-estado-vacio icono="entrenador" texto="Sin entrenadores todavía." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="paginacion">{{ $entrenadores->links() }}</div>

    <x-modal-confirmar />

    {{-- Nuevo / editar entrenador como modal (antes: admin.entrenadores.form).
         El registro se despacha por el evento abrir-entrenador desde el botón
         de la fila; tras un error de validación, _origen reabre este modal. --}}
    <div class="modal__fondo"
         x-data="editorGenerico(@js([
            'abierta'   => $errorEditor,
            'editando'  => (bool) old('id'),
            'crearUrl'  => route('admin.entrenadores.store'),
            'editarUrl' => route('admin.entrenadores.update', '__ID__'),
            'base'      => $vacios,
            'fila'      => $errorEditor ? array_replace($vacios, old(), ['is_public' => (bool) old('is_public')]) : $vacios,
         ]))"
         x-show="abierta" x-cloak
         @abrir-entrenador.window="abrir($event.detail)"
         @keydown.escape.window="cerrar()">
        <div class="tarjeta modal__caja formulario-panel" @click.outside="cerrar()">
            <div class="modal__cabecera">
                <h3 x-text="editando ? 'Editar entrenador' : 'Nuevo entrenador'"></h3>
                <button class="modal__cerrar" type="button" @click="cerrar()" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
            </div>

            <form class="formulario-panel" method="POST" :action="accion">
                @csrf
                <input type="hidden" name="_method" :value="editando ? 'PUT' : ''">
                <input type="hidden" name="_origen" value="entrenador">
                <input type="hidden" name="id" :value="fila.id">

                @if ($errorEditor)
                    <div class="aviso aviso--error" role="alert">{{ $errors->first() }}</div>
                @endif

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Nombre completo</span>
                        <input class="campo__control" type="text" name="name" required x-model="fila.name"></label>
                    <label class="campo"><span class="campo__etiqueta">Correo</span>
                        <input class="campo__control" type="email" name="email" required x-model="fila.email"></label>
                </div>

                <label class="campo" x-show="!editando"><span class="campo__etiqueta">Contraseña inicial (opcional)</span>
                    <input class="campo__control" type="text" name="password" x-model="fila.password"
                           placeholder="Se genera una si se deja en blanco"></label>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Especialidad</span>
                        <input class="campo__control" type="text" name="specialty" x-model="fila.specialty"></label>
                    <label class="campo"><span class="campo__etiqueta">Años de experiencia</span>
                        <input class="campo__control" type="number" name="years_experience" min="0" max="60" x-model="fila.years_experience"></label>
                </div>

                <label class="campo"><span class="campo__etiqueta">Biografía (para la web)</span>
                    <textarea class="campo__control" name="bio" style="min-height:6rem" x-model="fila.bio"></textarea></label>

                <input type="hidden" name="is_public" :value="fila.is_public ? 1 : 0">
                <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
                    <input type="checkbox" x-model="fila.is_public">
                    Mostrar en la web pública
                </label>

                <div class="formulario-panel__acciones">
                    <button class="btn btn--vidrio" type="button" @click="cerrar()">Cancelar</button>
                    <button class="btn btn--fuego" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>
@endsection
