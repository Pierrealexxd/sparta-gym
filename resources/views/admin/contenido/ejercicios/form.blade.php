@extends('layouts.panel')

@section('titulo', $ejercicio->exists ? 'Editar ejercicio' : 'Nuevo ejercicio')

@section('contenido')
    <form class="tarjeta formulario-panel" method="POST" enctype="multipart/form-data"
          action="{{ $ejercicio->exists ? route('admin.ejercicios.update', $ejercicio) : route('admin.ejercicios.store') }}">
        @csrf
        @if ($ejercicio->exists) @method('PUT') @endif

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Nombre</span>
                <input class="campo__control" type="text" name="name" required value="{{ old('name', $ejercicio->name) }}"></label>
            <label class="campo"><span class="campo__etiqueta">Equipo</span>
                <input class="campo__control" type="text" name="equipment" placeholder="Barra olímpica, sin equipo…" value="{{ old('equipment', $ejercicio->equipment) }}"></label>
        </div>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Categoría</span>
                <select class="campo__control" name="category" required>
                    @foreach (\App\Http\Controllers\Admin\ExerciseController::CATEGORIAS as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('category', $ejercicio->category ?? 'fuerza') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select></label>
            <label class="campo"><span class="campo__etiqueta">Nivel</span>
                <select class="campo__control" name="level" required>
                    @foreach (\App\Http\Controllers\Admin\ExerciseController::NIVELES as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('level', $ejercicio->level ?? 'principiante') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select></label>
        </div>

        <label class="campo"><span class="campo__etiqueta">Grupos musculares (uno por línea)</span>
            <textarea class="campo__control" name="muscle_groups" style="min-height:6rem">{{ old('muscle_groups', is_array($ejercicio->muscle_groups) ? implode("\n", $ejercicio->muscle_groups) : '') }}</textarea></label>

        <label class="campo"><span class="campo__etiqueta">Descripción</span>
            <textarea class="campo__control" name="description" style="min-height:6rem">{{ old('description', $ejercicio->description) }}</textarea></label>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Evita (error común)</span>
                <textarea class="campo__control" name="common_mistakes">{{ old('common_mistakes', $ejercicio->common_mistakes) }}</textarea></label>
            <label class="campo"><span class="campo__etiqueta">Hazlo así</span>
                <textarea class="campo__control" name="tips">{{ old('tips', $ejercicio->tips) }}</textarea></label>
        </div>

        <label class="campo"><span class="campo__etiqueta">Video (enlace de YouTube, opcional)</span>
            <input class="campo__control" type="url" name="video_url" placeholder="https://www.youtube.com/watch?v=…" value="{{ old('video_url', $ejercicio->video_url) }}">
            @error('video_url')<span class="campo__error">{{ $message }}</span>@enderror
        </label>

        <label class="campo"><span class="campo__etiqueta">Foto (opcional)</span>
            <input class="campo__control" type="file" name="imagen" accept="image/*"></label>
        @if ($ejercicio->image_path)
            <img src="{{ asset('storage/' . $ejercicio->image_path) }}" alt="" style="width:4rem;height:4rem;border-radius:var(--r-2);object-fit:cover;margin-top:var(--e-2)">
        @endif

        <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $ejercicio->is_active ?? true))>
            Publicado en la web
        </label>

        <div class="formulario-panel__acciones">
            <a class="btn btn--vidrio" href="{{ route('admin.ejercicios.index') }}">Cancelar</a>
            <button class="btn btn--fuego" type="submit">Guardar</button>
        </div>
    </form>
@endsection
