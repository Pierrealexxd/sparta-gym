@extends('layouts.panel')

@section('titulo', 'Contenido web')
@section('subtitulo', $faqs->total() . ' preguntas frecuentes')

@section('acciones')
    <div style="display:flex;gap:var(--e-3)">
        {{-- Abre la sección real de la landing (con lo ya guardado), en una
             pestaña nueva — no es un editor visual, solo un atajo para ver
             cómo queda antes de avisarle a nadie. --}}
        <a class="btn btn--vidrio" href="{{ route('landing') }}#preguntas" target="_blank" rel="noopener">
            <x-icono nombre="ojo" /> Previsualizar
        </a>
        <button class="btn btn--fuego" type="button" @click="window.dispatchEvent(new CustomEvent('abrir-faq'))">
            <x-icono nombre="agregar" /> Nueva pregunta
        </button>
    </div>
@endsection

@section('contenido')
    @php
        $errorEditor = $errors->any() && old('_origen') === 'faq';
        $vacios = [
            'question' => '', 'answer' => '', 'category' => '',
            'sort_order' => '0', 'is_published' => true,
        ];
    @endphp

    @include('admin.contenido._pestanas')

    <form class="panel__toolbar" method="GET">
        <div class="panel__busqueda">
            <x-icono nombre="lupa" />
            <input class="campo__control" type="search" name="q" value="{{ request('q') }}"
                   placeholder="Buscar por pregunta, respuesta o categoría…">
        </div>
    </form>

    <div class="tabla-envoltorio" data-revelar>
        <table class="tabla tabla--tarjetas">
            <thead><tr><th>Pregunta</th><th>Categoría</th><th>Orden</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse ($faqs as $faq)
                    <tr>
                        <td class="es-fuerte" data-etiqueta="Pregunta">{{ $faq->question }}</td>
                        <td data-etiqueta="Categoría">{{ $faq->category ?? '—' }}</td>
                        <td data-etiqueta="Orden">{{ $faq->sort_order }}</td>
                        <td data-etiqueta="Estado"><span class="estado estado--{{ $faq->is_published ? 'activo' : 'inactivo' }}">{{ $faq->is_published ? 'Publicada' : 'Oculta' }}</span></td>
                        <td data-etiqueta="nada">
                            <div style="display:flex;gap:var(--e-2)">
                                @if ($faq->is_published)
                                    <button class="btn btn--desnudo" type="button" title="Despublicar"
                                            @click="$store.confirmar.abrir({
                                                accion: '{{ route('admin.faqs.ocultar', $faq) }}',
                                                metodo: 'POST',
                                                titulo: 'Ocultar pregunta',
                                                mensaje: '¿Ocultar esta pregunta de la web? Pódras volver a publicarla.',
                                                etiqueta: 'Ocultar'
                                            })">
                                        <x-icono nombre="ojo" />
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('admin.faqs.publicar', $faq) }}">
                                        @csrf
                                        <button class="btn btn--desnudo" type="submit" title="Publicar">
                                            <x-icono nombre="ojo-tachado" />
                                        </button>
                                    </form>
                                @endif
                                <button class="btn btn--desnudo" type="button" title="Editar"
                                        @click="window.dispatchEvent(new CustomEvent('abrir-faq', { detail: @js([
                                            'id' => $faq->id,
                                            'question' => $faq->question,
                                            'answer' => $faq->answer,
                                            'category' => $faq->category,
                                            'sort_order' => $faq->sort_order,
                                            'is_published' => (bool) $faq->is_published,
                                        ]) }))">
                                    <x-icono nombre="lapiz" />
                                </button>
                                <button class="btn btn--desnudo" type="button" title="Eliminar"
                                        @click="$store.confirmar.abrir({
                                            accion: '{{ route('admin.faqs.destroy', $faq) }}',
                                            titulo: 'Eliminar pregunta',
                                            mensaje: '¿Eliminar definitivamente esta pregunta? No se puede recuperar.',
                                            etiqueta: 'Eliminar'
                                        })">
                                    <x-icono nombre="papelera" />
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="tabla__vacio"><x-estado-vacio icono="lista" texto="Sin preguntas todavía." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="paginacion">{{ $faqs->links() }}</div>

    <x-modal-confirmar />

    {{-- Nueva / editar pregunta como modal (antes: admin.contenido.faqs.form).
         El registro se despacha por el evento abrir-faq desde el botón de la
         fila; tras un error de validación, _origen reabre este modal. --}}
    <div class="modal__fondo"
         x-data="editorGenerico(@js([
            'abierta'   => $errorEditor,
            'editando'  => (bool) old('id'),
            'crearUrl'  => route('admin.faqs.store'),
            'editarUrl' => route('admin.faqs.update', '__ID__'),
            'base'      => $vacios,
            'fila'      => $errorEditor ? old() : $vacios,
         ]))"
         x-show="abierta" x-cloak
         @abrir-faq.window="abrir($event.detail)"
         @keydown.escape.window="cerrar()">
        <div class="tarjeta modal__caja formulario-panel" @click.outside="cerrar()">
            <div class="modal__cabecera">
                <h3 x-text="editando ? 'Editar pregunta' : 'Nueva pregunta'"></h3>
                <button class="modal__cerrar" type="button" @click="cerrar()" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
            </div>

            <form class="formulario-panel" method="POST" :action="accion">
                @csrf
                <input type="hidden" name="_method" :value="editando ? 'PUT' : ''">
                <input type="hidden" name="_origen" value="faq">
                <input type="hidden" name="id" :value="fila.id">

                @if ($errors->any() && old('_origen') === 'faq')
                    <div class="aviso aviso--error" role="alert">{{ $errors->first() }}</div>
                @endif

                <label class="campo"><span class="campo__etiqueta">Pregunta</span>
                    <input class="campo__control" type="text" name="question" required x-model="fila.question"></label>

                <label class="campo"><span class="campo__etiqueta">Respuesta</span>
                    <textarea class="campo__control" name="answer" required style="min-height:8rem" x-model="fila.answer"></textarea></label>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Categoría</span>
                        <input class="campo__control" type="text" name="category" x-model="fila.category"></label>
                    <label class="campo"><span class="campo__etiqueta">Orden</span>
                        <input class="campo__control" type="number" name="sort_order" min="0" x-model="fila.sort_order"></label>
                </div>

                <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
                    <input type="checkbox" name="is_published" value="1" x-model="fila.is_published">
                    Publicada en la web
                </label>

                <div class="formulario-panel__acciones">
                    <button class="btn btn--vidrio" type="button" @click="cerrar()">Cancelar</button>
                    <button class="btn btn--fuego" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>
@endsection