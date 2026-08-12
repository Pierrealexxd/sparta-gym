@extends('layouts.panel')

@section('titulo', $faq->exists ? 'Editar pregunta' : 'Nueva pregunta')

@section('contenido')
    <form class="tarjeta formulario-panel" method="POST"
          action="{{ $faq->exists ? route('admin.faqs.update', $faq) : route('admin.faqs.store') }}">
        @csrf
        @if ($faq->exists) @method('PUT') @endif

        <label class="campo"><span class="campo__etiqueta">Pregunta</span>
            <input class="campo__control" type="text" name="question" required value="{{ old('question', $faq->question) }}"></label>

        <label class="campo"><span class="campo__etiqueta">Respuesta</span>
            <textarea class="campo__control" name="answer" required style="min-height:10rem">{{ old('answer', $faq->answer) }}</textarea></label>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Categoría</span>
                <input class="campo__control" type="text" name="category" value="{{ old('category', $faq->category) }}"></label>
            <label class="campo"><span class="campo__etiqueta">Orden</span>
                <input class="campo__control" type="number" name="sort_order" min="0" value="{{ old('sort_order', $faq->sort_order ?? 0) }}"></label>
        </div>

        <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $faq->is_published ?? true))>
            Publicada en la web
        </label>

        <div class="formulario-panel__acciones">
            <a class="btn btn--vidrio" href="{{ route('admin.faqs.index') }}">Cancelar</a>
            <button class="btn btn--fuego" type="submit">Guardar</button>
        </div>
    </form>
@endsection
