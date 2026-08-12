@extends('layouts.panel')

@section('titulo', $usuario->exists ? 'Editar cuenta' : 'Nueva cuenta')

@section('contenido')
    @php $esCliente = (int) old('role_id', $usuario->role_id) === (int) \App\Models\Role::where('slug', 'cliente')->value('id'); @endphp

    <form class="tarjeta formulario-panel" method="POST"
          action="{{ $usuario->exists ? route('admin.usuarios.update', $usuario) : route('admin.usuarios.store') }}"
          x-data="{ rol: '{{ old('role_id', $usuario->role_id) }}', q: '', clienteId: '{{ old('member_id') }}', resultados: [] }">
        @csrf
        @if ($usuario->exists) @method('PUT') @endif

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Nombre completo</span>
                <input class="campo__control" type="text" name="name" required value="{{ old('name', $usuario->name) }}"></label>
            <label class="campo"><span class="campo__etiqueta">Correo</span>
                <input class="campo__control" type="email" name="email" required value="{{ old('email', $usuario->email) }}"></label>
        </div>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Rol</span>
                <select class="campo__control" name="role_id" required x-model="rol">
                    @foreach ($roles as $r)
                        <option value="{{ $r->id }}" @selected((int) old('role_id', $usuario->role_id) === (int) $r->id)>{{ $r->name }}</option>
                    @endforeach
                </select></label>

            @if ($sedesCreador->count() > 1)
                <label class="campo"><span class="campo__etiqueta">Sede</span>
                    <select class="campo__control" name="gym_id" required>
                        @foreach ($sedesCreador as $sede)
                            <option value="{{ $sede->id }}" @selected((int) old('gym_id', $usuario->gym_id) === (int) $sede->id)>{{ $sede->name }}</option>
                        @endforeach
                    </select></label>
            @endif
        </div>

        <template x-if="rol == {{ \App\Models\Role::where('slug', 'cliente')->value('id') }}">
            <label class="campo">
                <span class="campo__etiqueta">Cliente a dar acceso</span>
                <input class="campo__control" type="text" x-model="q"
                       x-on:input.debounce.250ms="fetch('{{ route('admin.usuarios.buscar-clientes') }}?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                           .then(r => r.json())
                           .then(r => { resultados = r })"
                       placeholder="Busca por nombre o código…">
                <select class="campo__control" name="member_id" x-model="clienteId" x-show="resultados.length" style="margin-top:var(--e-2)" x-cloak>
                    <template x-for="s in resultados" :key="s.id">
                        <option :value="s.id" x-text="s.full_name + ' (' + s.code + ')'"></option>
                    </template>
                </select>
                <span x-show="q.length >= 2 && resultados.length === 0" x-cloak
                      style="color:var(--humo);font-size:var(--t-sm)">Sin clientes sin cuenta con ese nombre.</span>
            </label>
        </template>

        @unless ($usuario->exists)
            <p style="color:var(--ceniza);font-size:var(--t-sm);line-height:1.6">
                La contraseña se genera al azar y se entrega <b style="color:var(--hueso)">una sola vez</b>
                al terminar el registro. La persona la cambia en su primer ingreso.
            </p>
        @endunless

        <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $usuario->is_active ?? true))>
            Cuenta activa (puede iniciar sesión)
        </label>

        <div class="formulario-panel__acciones">
            <a class="btn btn--vidrio" href="{{ route('admin.usuarios.index') }}">Cancelar</a>
            <button class="btn btn--fuego" type="submit">Guardar</button>
        </div>
    </form>
@endsection
