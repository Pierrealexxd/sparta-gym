@extends('layouts.panel')

@section('titulo', 'Contenido web')
@section('subtitulo', 'Contacto de la página pública')

@section('acciones')
    <div style="display:flex;gap:var(--e-3)">
        <button class="btn btn--vidrio" type="button"
                @click="window.dispatchEvent(new CustomEvent('abrir-preview', { detail: { seccion: 'contacto' } }))">
            <x-icono nombre="ojo" /> Previsualizar
        </button>
    </div>
@endsection

@section('contenido')
    @include('admin.contenido._pestanas')

    <form class="tarjeta formulario-panel" method="POST" action="{{ route('admin.contenido.contacto.guardar') }}">
        @csrf

        <h3 class="contacto-admin__titulo">Cabecera de la sección</h3>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Etiqueta</span>
                <input class="campo__control" type="text" name="contacto[eyebrow]" maxlength="40"
                       value="{{ old('contacto.eyebrow', $gym->settings['contacto']['eyebrow'] ?? 'Contacto') }}">
                @error('contacto.eyebrow')<span class="campo__error">{{ $message }}</span>@enderror
            </label>
            <label class="campo"><span class="campo__etiqueta">Título</span>
                <input class="campo__control" type="text" name="contacto[titulo]" maxlength="120"
                       value="{{ old('contacto.titulo', $gym->settings['contacto']['titulo'] ?? 'Ven a verlo') }}">
                @error('contacto.titulo')<span class="campo__error">{{ $message }}</span>@enderror
            </label>
        </div>

        <label class="campo"><span class="campo__etiqueta">Texto de apoyo</span>
            <textarea class="campo__control" name="contacto[lead]" maxlength="300" style="min-height:5rem"
                      placeholder="Pásate cuando quieras. La primera visita incluye una vuelta por la sala.">{{ old('contacto.lead', $gym->settings['contacto']['lead'] ?? 'Pásate cuando quieras. La primera visita incluye una vuelta por la sala.') }}</textarea>
            @error('contacto.lead')<span class="campo__error">{{ $message }}</span>@enderror
        </label>

        <h3 class="contacto-admin__titulo">Datos</h3>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Dirección</span>
                <input class="campo__control" type="text" name="address" maxlength="200" value="{{ old('address', $gym->address) }}">
                @error('address')<span class="campo__error">{{ $message }}</span>@enderror
            </label>
            <label class="campo"><span class="campo__etiqueta">Ciudad</span>
                <input class="campo__control" type="text" name="city" maxlength="80" value="{{ old('city', $gym->city) }}">
            </label>
        </div>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Teléfono</span>
                <input class="campo__control" type="text" name="phone" maxlength="40" value="{{ old('phone', $gym->phone) }}">
                @error('phone')<span class="campo__error">{{ $message }}</span>@enderror
            </label>
            <label class="campo"><span class="campo__etiqueta">Correo</span>
                <input class="campo__control" type="email" name="email" maxlength="180" value="{{ old('email', $gym->email) }}">
                @error('email')<span class="campo__error">{{ $message }}</span>@enderror
            </label>
        </div>

        <label class="campo"><span class="campo__etiqueta">WhatsApp</span>
            <input class="campo__control" type="text" name="whatsapp" maxlength="40" value="{{ old('whatsapp', $gym->whatsapp) }}" placeholder="+51 999 999 999">
            @error('whatsapp')<span class="campo__error">{{ $message }}</span>@enderror
        </label>

        {{-- Horario: una fila por franja (día / abre / cierra), el mismo
             repeater que la ficha de sede en admin/sedes/form.blade.php. --}}
        <div x-data="{
                filas: {{ \Illuminate\Support\Js::from(old('schedule', $gym->schedule ?: [['dia' => '', 'abre' => '', 'cierra' => '']])) }}
             }">
            <span class="campo__etiqueta">Horario</span>
            <template x-for="(fila, i) in filas" :key="i">
                <div class="fila-borrable">
                    <div class="formulario-panel__fila">
                        <label class="campo"><span class="campo__etiqueta">Día</span>
                            <input class="campo__control" type="text" :name="`schedule[${i}][dia]`" x-model="fila.dia" placeholder="Lunes a viernes"></label>
                        <label class="campo"><span class="campo__etiqueta">Abre</span>
                            <input class="campo__control" type="text" :name="`schedule[${i}][abre]`" x-model="fila.abre" placeholder="05:00"></label>
                        <label class="campo"><span class="campo__etiqueta">Cierra</span>
                            <input class="campo__control" type="text" :name="`schedule[${i}][cierra]`" x-model="fila.cierra" placeholder="23:00"></label>
                    </div>
                    <button class="btn btn--desnudo" type="button" @click="filas.splice(i, 1)" aria-label="Quitar franja">
                        <x-icono nombre="papelera" />
                    </button>
                </div>
            </template>
            <button class="btn btn--vidrio" type="button" @click="filas.push({ dia: '', abre: '', cierra: '' })">
                <x-icono nombre="agregar" /> Añadir franja
            </button>
        </div>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Latitud</span>
                <input class="campo__control" type="number" step="any" name="latitude" value="{{ old('latitude', $gym->latitude) }}">
                @error('latitude')<span class="campo__error">{{ $message }}</span>@enderror
            </label>
            <label class="campo"><span class="campo__etiqueta">Longitud</span>
                <input class="campo__control" type="number" step="any" name="longitude" value="{{ old('longitude', $gym->longitude) }}">
                @error('longitude')<span class="campo__error">{{ $message }}</span>@enderror
            </label>
        </div>
        <p class="contacto-admin__nota">
            El mapa de la página pública se muestra solo si ambos valores están rellenos.
        </p>

        <div class="formulario-panel__acciones">
            <a class="btn btn--vidrio" href="{{ route('admin.contenido.contacto') }}">Cancelar</a>
            <button class="btn btn--fuego" type="submit">Guardar</button>
        </div>
    </form>
@endsection
