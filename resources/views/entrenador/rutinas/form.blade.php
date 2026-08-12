@extends('layouts.panel')

@section('titulo', $rutina->exists ? 'Editar rutina' : 'Nueva rutina')

@section('contenido')
    <form class="tarjeta formulario-panel" method="POST"
          action="{{ $rutina->exists ? route('entrenador.rutinas.update', $rutina) : route('entrenador.rutinas.store') }}">
        @csrf
        @if ($rutina->exists) @method('PUT') @endif

        @unless ($rutina->exists)
            <label class="campo"><span class="campo__etiqueta">Socio</span>
                <select class="campo__control" name="member_id" required>
                    <option value="">Selecciona un socio</option>
                    @foreach ($clientes as $s)
                        <option value="{{ $s->id }}">{{ $s->full_name }}</option>
                    @endforeach
                </select></label>
        @endunless

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Nombre de la rutina</span>
                <input class="campo__control" type="text" name="name" required value="{{ old('name', $rutina->name) }}"></label>
            <label class="campo"><span class="campo__etiqueta">Objetivo</span>
                <input class="campo__control" type="text" name="objective" value="{{ old('objective', $rutina->objective) }}"></label>
        </div>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Inicio</span>
                <input class="campo__control" type="date" name="starts_at" value="{{ old('starts_at', $rutina->starts_at?->toDateString()) }}"></label>
            <label class="campo"><span class="campo__etiqueta">Fin</span>
                <input class="campo__control" type="date" name="ends_at" value="{{ old('ends_at', $rutina->ends_at?->toDateString()) }}"></label>
        </div>

        @if ($rutina->exists)
            <label class="campo"><span class="campo__etiqueta">Estado</span>
                <select class="campo__control" name="status">
                    @foreach (['activa' => 'Activa', 'finalizada' => 'Finalizada', 'borrador' => 'Borrador'] as $v => $l)
                        <option value="{{ $v }}" @selected(old('status', $rutina->status) === $v)>{{ $l }}</option>
                    @endforeach
                </select></label>
        @endif

        <label class="campo"><span class="campo__etiqueta">Notas</span>
            <textarea class="campo__control" name="notes">{{ old('notes', $rutina->notes) }}</textarea></label>

        <div class="formulario-panel__acciones">
            <a class="btn btn--vidrio" href="{{ route('entrenador.rutinas.index') }}">Cancelar</a>
            <button class="btn btn--fuego" type="submit">Guardar</button>
        </div>
    </form>
@endsection
