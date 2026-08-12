@extends('layouts.panel')

@section('titulo', $entrenador->exists ? 'Editar entrenador' : 'Nuevo entrenador')

@section('contenido')
    <form class="tarjeta formulario-panel" method="POST"
          action="{{ $entrenador->exists ? route('admin.entrenadores.update', $entrenador) : route('admin.entrenadores.store') }}">
        @csrf
        @if ($entrenador->exists) @method('PUT') @endif

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Nombre completo</span>
                <input class="campo__control" type="text" name="name" required value="{{ old('name', $entrenador->user?->name) }}"></label>
            <label class="campo"><span class="campo__etiqueta">Correo</span>
                <input class="campo__control" type="email" name="email" required value="{{ old('email', $entrenador->user?->email) }}"></label>
        </div>

        @unless ($entrenador->exists)
            <label class="campo"><span class="campo__etiqueta">Contraseña inicial (opcional)</span>
                <input class="campo__control" type="text" name="password" placeholder="Se genera una si se deja en blanco"></label>
        @endunless

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Especialidad</span>
                <input class="campo__control" type="text" name="specialty" value="{{ old('specialty', $entrenador->specialty) }}"></label>
            <label class="campo"><span class="campo__etiqueta">Años de experiencia</span>
                <input class="campo__control" type="number" name="years_experience" value="{{ old('years_experience', $entrenador->years_experience) }}"></label>
        </div>

        <label class="campo"><span class="campo__etiqueta">Biografía (para la web)</span>
            <textarea class="campo__control" name="bio">{{ old('bio', $entrenador->bio) }}</textarea></label>

        <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
            <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $entrenador->is_public ?? true))>
            Mostrar en la web pública
        </label>

        <div class="formulario-panel__acciones">
            <a class="btn btn--vidrio" href="{{ route('admin.entrenadores.index') }}">Cancelar</a>
            <button class="btn btn--fuego" type="submit">Guardar</button>
        </div>
    </form>
@endsection
