@extends('layouts.panel')

@section('titulo', 'Usuarios')
@section('subtitulo', $usuarios->total() . ' cuentas en total')

@section('acciones')
    <button class="btn btn--fuego" type="button" @click="window.dispatchEvent(new CustomEvent('abrir-usuario'))">
        <x-icono nombre="agregar" /> Nueva cuenta
    </button>
@endsection

@section('contenido')
    @php
        $clienteRolId = \App\Models\Role::where('slug', 'cliente')->value('id');
        $errorEditor = $errors->any() && old('_origen') === 'usuario';
        $vacios = [
            'name' => '', 'email' => '',
            'role_id' => (string) ($roles->first()?->id ?? ''),
            'gym_id' => (string) ($sedesCreador->first()?->id ?? ''),
            'member_id' => '', 'q' => '', 'resultados' => [],
            'is_active' => true,
        ];
    @endphp

    @include('admin.configuracion._pestanas')

    <form class="panel__toolbar" method="GET">
        <div class="panel__busqueda">
            <x-icono nombre="lupa" />
            <input class="campo__control" type="search" name="q" value="{{ request('q') }}"
                   placeholder="Buscar por nombre, correo o rol…">
        </div>
    </form>

    <div class="tabla-envoltorio" data-revelar>
        <table class="tabla tabla--tarjetas">
            <thead><tr><th>Nombre</th><th>Correo</th><th class="tabla__oculta-movil">Rol</th><th class="tabla__oculta-movil">Sede</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse ($usuarios as $u)
                    <tr>
                        <td class="es-fuerte" data-etiqueta="Nombre">{{ $u->name }}</td>
                        <td data-etiqueta="Correo">{{ $u->email }}</td>
                        <td class="tabla__oculta-movil" data-etiqueta="Rol"><span class="estado">{{ $u->role?->name ?? '—' }}</span></td>
                        <td class="tabla__oculta-movil" data-etiqueta="Sede"><span class="estado">{{ $u->gym?->name ?? '—' }}</span></td>
                        <td data-etiqueta="Estado"><span class="estado estado--{{ $u->is_active ? 'activo' : 'inactivo' }}">{{ $u->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                        <td data-etiqueta="nada">
                            <button class="btn btn--desnudo" type="button" title="Editar"
                                    @click="window.dispatchEvent(new CustomEvent('abrir-usuario', { detail: @js([
                                        'id' => $u->id,
                                        'name' => $u->name,
                                        'email' => $u->email,
                                        'role_id' => (string) $u->role_id,
                                        'gym_id' => (string) $u->gym_id,
                                        'member_id' => $u->member?->id ?? '',
                                        'is_active' => (bool) $u->is_active,
                                    ]) }))">
                                <x-icono nombre="lapiz" />
                            </button>
                            @if ($u->is_active && ! $u->is(auth()->user()))
                                <button class="btn btn--desnudo" type="button"
                                        @click="$store.confirmar.abrir({
                                            accion: '{{ route('admin.usuarios.destroy', $u) }}',
                                            titulo: 'Desactivar cuenta',
                                            mensaje: '¿Desactivar la cuenta de {{ $u->name }}? No podrá iniciar sesión.',
                                            etiqueta: 'Desactivar'
                                        })">
                                    <x-icono nombre="papelera" />
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="tabla__vacio"><x-estado-vacio icono="usuarios" texto="Sin cuentas todavía." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="paginacion">{{ $usuarios->links() }}</div>

    <x-modal-confirmar />

    {{-- Nueva / editar cuenta como modal (antes: admin.usuarios.form).
         El registro se despacha por el evento abrir-usuario desde el botón de
         la fila; tras un error de validación, _origen reabre este modal. El
         buscador de clientes (rol "cliente") vive en fila.q/fila.resultados
         para reiniciarse limpio en cada apertura. --}}
    <div class="modal__fondo"
         x-data="editorGenerico(@js([
            'abierta'   => $errorEditor,
            'editando'  => (bool) old('id'),
            'crearUrl'  => route('admin.usuarios.store'),
            'editarUrl' => route('admin.usuarios.update', '__ID__'),
            'base'      => $vacios,
            'fila'      => $errorEditor ? array_replace($vacios, old(), [
                'is_active' => (bool) old('is_active', true),
            ]) : $vacios,
         ]))"
         x-show="abierta" x-cloak
         @abrir-usuario.window="abrir($event.detail)"
         @keydown.escape.window="cerrar()">
        <div class="tarjeta modal__caja formulario-panel" @click.outside="cerrar()">
            <div class="modal__cabecera">
                <h3 x-text="editando ? 'Editar cuenta' : 'Nueva cuenta'"></h3>
                <button class="modal__cerrar" type="button" @click="cerrar()" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
            </div>

            <form class="formulario-panel" method="POST" :action="accion">
                @csrf
                <input type="hidden" name="_method" :value="editando ? 'PUT' : ''">
                <input type="hidden" name="_origen" value="usuario">
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

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Rol</span>
                        <select class="campo__control" name="role_id" required x-model="fila.role_id">
                            @foreach ($roles as $r)
                                <option value="{{ $r->id }}">{{ $r->name }}</option>
                            @endforeach
                        </select></label>

                    @if ($sedesCreador->count() > 1)
                        <label class="campo"><span class="campo__etiqueta">Sede</span>
                            <select class="campo__control" name="gym_id" required x-model="fila.gym_id">
                                @foreach ($sedesCreador as $sede)
                                    <option value="{{ $sede->id }}">{{ $sede->name }}</option>
                                @endforeach
                            </select></label>
                    @endif
                </div>

                <template x-if="fila.role_id == '{{ $clienteRolId }}'">
                    <label class="campo">
                        <span class="campo__etiqueta">Cliente a dar acceso</span>
                        <input class="campo__control" type="text" x-model="fila.q"
                               x-on:input.debounce.250ms="fetch('{{ route('admin.usuarios.buscar-clientes') }}?q=' + encodeURIComponent(fila.q), { headers: { 'Accept': 'application/json' } })
                                   .then(r => r.json())
                                   .then(r => { fila.resultados = r })"
                               placeholder="Busca por nombre o código…">
                        <select class="campo__control" name="member_id" x-model="fila.member_id" x-show="fila.resultados.length" style="margin-top:var(--e-2)" x-cloak>
                            <template x-for="s in fila.resultados" :key="s.id">
                                <option :value="s.id" x-text="s.full_name + ' (' + s.code + ')'"></option>
                            </template>
                        </select>
                        <span x-show="fila.q.length >= 2 && fila.resultados.length === 0" x-cloak
                              style="color:var(--humo);font-size:var(--t-sm)">Sin clientes sin cuenta con ese nombre.</span>
                    </label>
                </template>

                <p x-show="!editando" style="color:var(--ceniza);font-size:var(--t-sm);line-height:1.6">
                    La contraseña se genera al azar y se entrega <b style="color:var(--hueso)">una sola vez</b>
                    al terminar el registro. La persona la cambia en su primer ingreso.
                </p>

                <input type="hidden" name="is_active" :value="fila.is_active ? 1 : 0">
                <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
                    <input type="checkbox" x-model="fila.is_active">
                    Cuenta activa (puede iniciar sesión)
                </label>

                <div class="formulario-panel__acciones">
                    <button class="btn btn--vidrio" type="button" @click="cerrar()">Cancelar</button>
                    <button class="btn btn--fuego" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>
@endsection
