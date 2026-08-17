@extends('layouts.panel')

@section('titulo', 'Planes')
@section('subtitulo', $planes->total() . ' planes')

@section('acciones')
    <div style="display:flex;gap:var(--e-3)">
        <button class="btn btn--vidrio" type="button"
                @click="window.dispatchEvent(new CustomEvent('abrir-preview', { detail: { seccion: 'planes' } }))">
            <x-icono nombre="ojo" /> Previsualizar
        </button>
        <button class="btn btn--fuego" type="button" @click="window.dispatchEvent(new CustomEvent('abrir-plan'))">
            <x-icono nombre="agregar" /> Nuevo plan
        </button>
    </div>
@endsection

@section('contenido')
    @php
        $errorEditor = $errors->any() && old('_origen') === 'plan';
        $vacios = [
            'name' => '', 'tagline' => '', 'price' => '', 'duration_days' => '',
            'features' => '', 'accent_color' => '', 'is_featured' => false, 'is_public' => true,
        ];
    @endphp

    @include('admin.contenido._pestanas')

    <form class="panel__toolbar" method="GET">
        <div class="panel__busqueda">
            <x-icono nombre="lupa" />
            <input class="campo__control" type="search" name="q" value="{{ request('q') }}"
                   placeholder="Buscar por nombre…">
        </div>
    </form>

    <div class="tabla-envoltorio" data-revelar>
        <table class="tabla tabla--tarjetas">
            <thead><tr><th>Plan</th><th>Precio</th><th class="tabla__oculta-movil">Duración</th><th class="tabla__oculta-movil">Membresías vendidas</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse ($planes as $plan)
                    <tr>
                        <td class="es-fuerte" data-etiqueta="Plan">{{ $plan->name }} @if($plan->is_featured)<span class="etiqueta etiqueta--fuego">Destacado</span>@endif</td>
                        <td data-etiqueta="Precio">S/ {{ number_format($plan->price, 0) }}</td>
                        <td class="tabla__oculta-movil" data-etiqueta="Duración">{{ $plan->duracion_legible }}</td>
                        <td class="tabla__oculta-movil" data-etiqueta="Vendidas">{{ $plan->memberships_count }}</td>
                        <td data-etiqueta="Estado"><span class="estado estado--{{ $plan->is_active ? 'activo' : 'inactivo' }}">{{ $plan->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                        <td data-etiqueta="nada">
                            <button class="btn btn--desnudo" type="button" title="Editar"
                                    @click="window.dispatchEvent(new CustomEvent('abrir-plan', { detail: @js([
                                        'id' => $plan->id,
                                        'name' => $plan->name,
                                        'tagline' => $plan->tagline,
                                        'price' => $plan->price,
                                        'duration_days' => $plan->duration_days,
                                        'features' => is_array($plan->features) ? implode("\n", $plan->features) : '',
                                        'accent_color' => $plan->accent_color ?? '',
                                        'is_featured' => (bool) $plan->is_featured,
                                        'is_public' => (bool) $plan->is_public,
                                    ]) }))">
                                <x-icono nombre="lapiz" />
                            </button>
                            @if ($plan->is_active)
                                <button class="btn btn--desnudo" type="button"
                                        @click="$store.confirmar.abrir({
                                            accion: '{{ route('admin.planes.destroy', $plan) }}',
                                            titulo: 'Desactivar plan',
                                            mensaje: '¿Desactivar este plan? Dejará de ofrecerse en nuevas membresías.',
                                            etiqueta: 'Desactivar'
                                        })">
                                    <x-icono nombre="papelera" />
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="tabla__vacio"><x-estado-vacio icono="lista" texto="Sin planes todavía." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="paginacion">{{ $planes->links() }}</div>

    <x-modal-confirmar />

    {{-- Nuevo / editar plan como modal (antes: admin.planes.form).
         El registro se despacha por el evento abrir-plan desde el botón de la
         fila; tras un error de validación, _origen reabre este modal. --}}
    <div class="modal__fondo"
         x-data="editorGenerico(@js([
            'abierta'   => $errorEditor,
            'editando'  => (bool) old('id'),
            'crearUrl'  => route('admin.planes.store'),
            'editarUrl' => route('admin.planes.update', '__ID__'),
            'base'      => $vacios,
            'fila'      => $errorEditor ? array_replace($vacios, old(), [
                'is_featured' => (bool) old('is_featured'),
                'is_public'   => (bool) old('is_public', true),
            ]) : $vacios,
         ]))"
         x-show="abierta" x-cloak
         @abrir-plan.window="abrir($event.detail)"
         @keydown.escape.window="cerrar()">
        <div class="tarjeta modal__caja formulario-panel" @click.outside="cerrar()">
            <div class="modal__cabecera">
                <h3 x-text="editando ? 'Editar plan' : 'Nuevo plan'"></h3>
                <button class="modal__cerrar" type="button" @click="cerrar()" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
            </div>

            <form class="formulario-panel" method="POST" :action="accion">
                @csrf
                <input type="hidden" name="_method" :value="editando ? 'PUT' : ''">
                <input type="hidden" name="_origen" value="plan">
                <input type="hidden" name="id" :value="fila.id">

                @if ($errorEditor)
                    <div class="aviso aviso--error" role="alert">{{ $errors->first() }}</div>
                @endif

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Nombre</span>
                        <input class="campo__control" type="text" name="name" required x-model="fila.name"></label>
                    <label class="campo"><span class="campo__etiqueta">Lema corto</span>
                        <input class="campo__control" type="text" name="tagline" x-model="fila.tagline"></label>
                </div>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Precio (S/)</span>
                        <input class="campo__control" type="number" step="0.01" name="price" required x-model="fila.price"></label>
                    <label class="campo"><span class="campo__etiqueta">Duración (días)</span>
                        <input class="campo__control" type="number" name="duration_days" required min="1" x-model="fila.duration_days"></label>
                </div>

                <label class="campo"><span class="campo__etiqueta">Beneficios (uno por línea)</span>
                    <textarea class="campo__control" name="features" style="min-height:7rem" x-model="fila.features"></textarea></label>

                <div class="campo">
                    <span class="campo__etiqueta">Color de acento (opcional)</span>
                    <div style="display:flex;align-items:center;gap:var(--e-3)">
                        {{-- Sin name: es solo el control visual. El input
                             type="color" no soporta quedar "vacío" (siempre
                             manda algún hex), así que lo que en verdad se
                             envía es el oculto de abajo, que sí puede ir
                             vacío cuando no se eligió color propio. --}}
                        <input class="campo__color" type="color"
                               :value="fila.accent_color || '#ff6a1f'" @input="fila.accent_color = $el.value">
                        <span style="color:var(--ceniza);font-size:var(--t-sm)" x-text="fila.accent_color || 'Usa el degradado de fuego por defecto'"></span>
                        <button class="btn btn--desnudo" type="button" x-show="fila.accent_color" @click="fila.accent_color = ''">
                            Quitar
                        </button>
                    </div>
                    <input type="hidden" name="accent_color" :value="fila.accent_color">
                </div>

                <div style="display:flex;gap:var(--e-6)">
                    <input type="hidden" name="is_featured" :value="fila.is_featured ? 1 : 0">
                    <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
                        <input type="checkbox" x-model="fila.is_featured">
                        Destacar en la web
                    </label>
                    <input type="hidden" name="is_public" :value="fila.is_public ? 1 : 0">
                    <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
                        <input type="checkbox" x-model="fila.is_public">
                        Mostrar en la web
                    </label>
                </div>

                <div class="formulario-panel__acciones">
                    <button class="btn btn--vidrio" type="button" @click="cerrar()">Cancelar</button>
                    <button class="btn btn--fuego" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>
@endsection
