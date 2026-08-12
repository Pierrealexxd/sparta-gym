@extends('layouts.panel')

@section('titulo', $testimonio->exists ? 'Editar testimonio' : 'Nuevo testimonio')

@section('contenido')
    <form class="tarjeta formulario-panel" method="POST" enctype="multipart/form-data"
          action="{{ $testimonio->exists ? route('admin.testimonios.update', $testimonio) : route('admin.testimonios.store') }}">
        @csrf
        @if ($testimonio->exists) @method('PUT') @endif

        @if ($testimonio->member_id)
            <p style="color:var(--ceniza);font-size:var(--t-sm)">Enviado por el socio <strong>{{ $testimonio->member?->full_name }}</strong> desde su panel.</p>
        @endif

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Autor</span>
                <input class="campo__control" type="text" name="author" required value="{{ old('author', $testimonio->author) }}"></label>
            <label class="campo"><span class="campo__etiqueta">Rol / antigüedad</span>
                <input class="campo__control" type="text" name="role" placeholder="Socio desde 2023" value="{{ old('role', $testimonio->role) }}"></label>
        </div>

        <label class="campo"><span class="campo__etiqueta">Testimonio</span>
            <textarea class="campo__control" name="content" required style="min-height:8rem">{{ old('content', $testimonio->content) }}</textarea></label>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Calificación (1-5)</span>
                <input class="campo__control" type="number" name="rating" min="1" max="5" required value="{{ old('rating', $testimonio->rating ?? 5) }}"></label>
            <label class="campo"><span class="campo__etiqueta">Orden</span>
                <input class="campo__control" type="number" name="sort_order" min="0" value="{{ old('sort_order', $testimonio->sort_order ?? 0) }}"></label>
        </div>

        <label class="campo"><span class="campo__etiqueta">Foto (opcional)</span>
            <input class="campo__control" type="file" name="foto" accept="image/*"></label>
        @if ($testimonio->photo_path)
            <img src="{{ asset('storage/' . $testimonio->photo_path) }}" alt="" style="width:4rem;height:4rem;border-radius:50%;object-fit:cover;margin-top:var(--e-2)">
        @endif

        <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $testimonio->is_published ?? true))>
            Publicado en la web
        </label>

        <div class="formulario-panel__acciones">
            <a class="btn btn--vidrio" href="{{ route('admin.testimonios.index') }}">Cancelar</a>
            <button class="btn btn--fuego" type="submit">Guardar</button>
        </div>
    </form>
@endsection
