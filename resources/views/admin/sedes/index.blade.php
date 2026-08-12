@extends('layouts.panel')

@section('titulo', 'Sedes')
@section('subtitulo', $sedes->total() . ' registradas')

@section('acciones')
    <button class="btn btn--fuego" type="button" @click="window.dispatchEvent(new CustomEvent('abrir-sede'))">
        <x-icono nombre="agregar" /> Nueva sede
    </button>
@endsection

@section('contenido')
    @php
        $errorEditor = $errors->any() && old('_origen') === 'sede';
        $vacios = [
            'name' => '', 'city' => '', 'address' => '', 'phone' => '', 'email' => '',
            'tagline' => '', 'description' => '', 'logo_path' => null,
            'schedule' => [['dia' => '', 'abre' => '', 'cierra' => '']],
            'socials' => ['instagram' => '', 'facebook' => '', 'tiktok' => '', 'youtube' => ''],
            'is_active' => true,
        ];
    @endphp

    @include('admin.configuracion._pestanas')

    <form class="panel__toolbar" method="GET">
        <div class="panel__busqueda">
            <x-icono nombre="lupa" />
            <input class="campo__control" type="search" name="q" value="{{ request('q') }}"
                   placeholder="Buscar por nombre o ciudad…">
        </div>
    </form>

    <div class="tabla-envoltorio" data-revelar>
        <table class="tabla">
            <thead><tr><th>Nombre</th><th>Ciudad</th><th>Teléfono</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse ($sedes as $sede)
                    <tr>
                        <td class="es-fuerte">{{ $sede->name }}</td>
                        <td>{{ $sede->city ?? '—' }}</td>
                        <td>{{ $sede->phone ?? '—' }}</td>
                        <td><span class="estado estado--{{ $sede->is_active ? 'activo' : 'inactivo' }}">{{ $sede->is_active ? 'Activa' : 'Inactiva' }}</span></td>
                        <td style="display:flex;gap:var(--e-2)">
                            <a class="btn btn--desnudo" href="{{ route('admin.sedes.qr', $sede) }}" title="QR de asistencia"><x-icono nombre="qr" /></a>
                            <button class="btn btn--desnudo" type="button" title="Editar"
                                    @click="window.dispatchEvent(new CustomEvent('abrir-sede', { detail: @js([
                                        'id' => $sede->id,
                                        'name' => $sede->name,
                                        'city' => $sede->city,
                                        'address' => $sede->address,
                                        'phone' => $sede->phone,
                                        'email' => $sede->email,
                                        'tagline' => $sede->tagline,
                                        'description' => $sede->description,
                                        'logo_path' => $sede->logo_path,
                                        'schedule' => $sede->schedule ?: [['dia' => '', 'abre' => '', 'cierra' => '']],
                                        'socials' => $sede->socials ?: [],
                                        'is_active' => (bool) $sede->is_active,
                                    ]) }))">
                                <x-icono nombre="lapiz" />
                            </button>
                            @if ($sede->is_active)
                                <button class="btn btn--desnudo" type="button"
                                        @click="$store.confirmar.abrir({
                                            accion: '{{ route('admin.sedes.destroy', $sede) }}',
                                            titulo: 'Desactivar sede',
                                            mensaje: '¿Desactivar {{ $sede->name }}? Dejará de aparecer en el selector.',
                                            etiqueta: 'Desactivar'
                                        })">
                                    <x-icono nombre="papelera" />
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="tabla__vacio"><x-estado-vacio icono="ubicacion" texto="Sin sedes registradas." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="paginacion">{{ $sedes->links() }}</div>

    <x-modal-confirmar />

    {{-- Nueva / editar sede como modal (antes: admin.sedes.form).
         El registro se despacha por el evento abrir-sede desde el botón de la
         fila; tras un error de validación, _origen reabre este modal. --}}
    <div class="modal__fondo"
         x-data="editorGenerico(@js([
            'abierta'   => $errorEditor,
            'editando'  => (bool) old('id'),
            'crearUrl'  => route('admin.sedes.store'),
            'editarUrl' => route('admin.sedes.update', '__ID__'),
            'base'      => $vacios,
            'fila'      => $errorEditor ? array_replace($vacios, old(), [
                'is_active' => (bool) old('is_active', true),
                'socials'   => array_replace($vacios['socials'], old('socials', [])),
            ]) : $vacios,
         ]))"
         x-show="abierta" x-cloak
         @abrir-sede.window="abrir($event.detail)"
         @keydown.escape.window="cerrar()">
        <div class="tarjeta modal__caja formulario-panel" @click.outside="cerrar()">
            <div class="modal__cabecera">
                <h3 x-text="editando ? 'Editar sede' : 'Nueva sede'"></h3>
                <button class="modal__cerrar" type="button" @click="cerrar()" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
            </div>

            <form class="formulario-panel" method="POST" enctype="multipart/form-data" :action="accion">
                @csrf
                <input type="hidden" name="_method" :value="editando ? 'PUT' : ''">
                <input type="hidden" name="_origen" value="sede">
                <input type="hidden" name="id" :value="fila.id">

                @if ($errorEditor)
                    <div class="aviso aviso--error" role="alert">{{ $errors->first() }}</div>
                @endif

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Nombre</span>
                        <input class="campo__control" type="text" name="name" required x-model="fila.name" placeholder="Sparta Norte"></label>
                    <label class="campo"><span class="campo__etiqueta">Ciudad</span>
                        <input class="campo__control" type="text" name="city" x-model="fila.city"></label>
                </div>

                <label class="campo"><span class="campo__etiqueta">Dirección</span>
                    <input class="campo__control" type="text" name="address" x-model="fila.address"></label>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Teléfono</span>
                        <input class="campo__control" type="text" name="phone" x-model="fila.phone"></label>
                    <label class="campo"><span class="campo__etiqueta">Correo</span>
                        <input class="campo__control" type="email" name="email" x-model="fila.email"></label>
                </div>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Lema corto</span>
                        <input class="campo__control" type="text" name="tagline" x-model="fila.tagline"></label>
                </div>

                <label class="campo"><span class="campo__etiqueta">Descripción</span>
                    <textarea class="campo__control" name="description" style="min-height:5rem" x-model="fila.description"></textarea></label>

                <label class="campo"><span class="campo__etiqueta">Logo</span>
                    <input class="campo__control" type="file" name="logo" accept="image/*"></label>
                <p style="color:var(--ceniza);font-size:var(--t-xs);margin-top:calc(var(--e-2) * -1)">
                    Se usa como ícono de pestaña del navegador y junto al nombre en este panel — el wordmark de la web pública no cambia.
                </p>
                <img x-show="fila.logo_path" :src="'{{ asset('storage') }}' + '/' + fila.logo_path" alt=""
                     style="width:3rem;height:3rem;border-radius:var(--r-2);object-fit:cover;margin-top:var(--e-2)">

                {{-- Horario: una fila por franja (día / abre / cierra), mismo
                     espíritu que "un beneficio por línea" en planes, pero con
                     3 campos — vive en fila.schedule para reiniciarse en cada
                     apertura del modal. --}}
                <span class="campo__etiqueta">Horario</span>
                <template x-for="(fh, i) in fila.schedule" :key="i">
                    <div class="fila-borrable">
                        <div class="formulario-panel__fila">
                            <label class="campo"><span class="campo__etiqueta">Día</span>
                                <input class="campo__control" type="text" :name="`schedule[${i}][dia]`" x-model="fh.dia" placeholder="Lunes a viernes"></label>
                            <label class="campo"><span class="campo__etiqueta">Abre</span>
                                <input class="campo__control" type="text" :name="`schedule[${i}][abre]`" x-model="fh.abre" placeholder="05:00"></label>
                            <label class="campo"><span class="campo__etiqueta">Cierra</span>
                                <input class="campo__control" type="text" :name="`schedule[${i}][cierra]`" x-model="fh.cierra" placeholder="23:00"></label>
                        </div>
                        <button class="btn btn--desnudo" type="button" @click="fila.schedule.splice(i, 1)" aria-label="Quitar franja">
                            <x-icono nombre="papelera" />
                        </button>
                    </div>
                </template>
                <button class="btn btn--vidrio" type="button" @click="fila.schedule.push({ dia: '', abre: '', cierra: '' })">
                    <x-icono nombre="agregar" /> Añadir franja
                </button>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Instagram</span>
                        <input class="campo__control" type="text" name="socials[instagram]" placeholder="https://instagram.com/…" x-model="fila.socials.instagram"></label>
                    <label class="campo"><span class="campo__etiqueta">Facebook</span>
                        <input class="campo__control" type="text" name="socials[facebook]" placeholder="https://facebook.com/…" x-model="fila.socials.facebook"></label>
                </div>
                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">TikTok</span>
                        <input class="campo__control" type="text" name="socials[tiktok]" placeholder="https://tiktok.com/@…" x-model="fila.socials.tiktok"></label>
                    <label class="campo"><span class="campo__etiqueta">YouTube</span>
                        <input class="campo__control" type="text" name="socials[youtube]" placeholder="https://youtube.com/@…" x-model="fila.socials.youtube"></label>
                </div>

                <input type="hidden" name="is_active" :value="fila.is_active ? 1 : 0">
                <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
                    <input type="checkbox" x-model="fila.is_active">
                    Sede activa
                </label>

                <div class="formulario-panel__acciones">
                    <button class="btn btn--vidrio" type="button" @click="cerrar()">Cancelar</button>
                    <button class="btn btn--fuego" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>
@endsection
