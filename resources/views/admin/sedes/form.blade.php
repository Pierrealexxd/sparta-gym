@extends('layouts.panel')

@section('titulo', $sede->exists ? 'Editar sede' : 'Nueva sede')

@section('contenido')
    <form class="tarjeta formulario-panel" method="POST" enctype="multipart/form-data"
          action="{{ $sede->exists ? route('admin.sedes.update', $sede) : route('admin.sedes.store') }}">
        @csrf
        @if ($sede->exists) @method('PUT') @endif

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Nombre</span>
                <input class="campo__control" type="text" name="name" required value="{{ old('name', $sede->name) }}" placeholder="Sparta Norte">
                @error('name')<span class="campo__error">{{ $message }}</span>@enderror
            </label>
            <label class="campo"><span class="campo__etiqueta">Ciudad</span>
                <input class="campo__control" type="text" name="city" value="{{ old('city', $sede->city) }}"></label>
        </div>

        <label class="campo"><span class="campo__etiqueta">Dirección</span>
            <input class="campo__control" type="text" name="address" value="{{ old('address', $sede->address) }}"></label>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Teléfono</span>
                <input class="campo__control" type="text" name="phone" value="{{ old('phone', $sede->phone) }}"></label>
            <label class="campo"><span class="campo__etiqueta">Correo</span>
                <input class="campo__control" type="email" name="email" value="{{ old('email', $sede->email) }}"></label>
        </div>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Lema corto</span>
                <input class="campo__control" type="text" name="tagline" value="{{ old('tagline', $sede->tagline) }}"></label>
        </div>

        <label class="campo"><span class="campo__etiqueta">Descripción</span>
            <textarea class="campo__control" name="description" style="min-height:6rem">{{ old('description', $sede->description) }}</textarea></label>

        <label class="campo"><span class="campo__etiqueta">Logo</span>
            <input class="campo__control" type="file" name="logo" accept="image/*"></label>
        <p style="color:var(--ceniza);font-size:var(--t-xs);margin-top:calc(var(--e-2) * -1)">
            Se usa como ícono de pestaña del navegador y junto al nombre en este panel — el wordmark de la web pública no cambia.
        </p>
        @if ($sede->logo_path)
            <img src="{{ asset('storage/' . $sede->logo_path) }}" alt="" style="width:3rem;height:3rem;border-radius:var(--r-2);object-fit:cover;margin-top:var(--e-2)">
        @endif

        {{-- Horario: una fila por franja (día / abre / cierra), mismo espíritu
             que "un beneficio por línea" en planes, pero con 3 campos. --}}
        <div x-data="{
                filas: {{ \Illuminate\Support\Js::from(old('schedule', $sede->schedule ?: [['dia' => '', 'abre' => '', 'cierra' => '']])) }}
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
            <label class="campo"><span class="campo__etiqueta">Instagram</span>
                <input class="campo__control" type="text" name="socials[instagram]" placeholder="https://instagram.com/…" value="{{ old('socials.instagram', $sede->socials['instagram'] ?? '') }}"></label>
            <label class="campo"><span class="campo__etiqueta">Facebook</span>
                <input class="campo__control" type="text" name="socials[facebook]" placeholder="https://facebook.com/…" value="{{ old('socials.facebook', $sede->socials['facebook'] ?? '') }}"></label>
        </div>
        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">TikTok</span>
                <input class="campo__control" type="text" name="socials[tiktok]" placeholder="https://tiktok.com/@…" value="{{ old('socials.tiktok', $sede->socials['tiktok'] ?? '') }}"></label>
            <label class="campo"><span class="campo__etiqueta">YouTube</span>
                <input class="campo__control" type="text" name="socials[youtube]" placeholder="https://youtube.com/@…" value="{{ old('socials.youtube', $sede->socials['youtube'] ?? '') }}"></label>
        </div>

        <label style="display:flex;align-items:center;gap:var(--e-3);font-size:var(--t-sm);color:var(--ceniza)">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $sede->is_active ?? true))>
            Sede activa
        </label>

        <div class="formulario-panel__acciones">
            <a class="btn btn--vidrio" href="{{ route('admin.sedes.index') }}">Cancelar</a>
            <button class="btn btn--fuego" type="submit">Guardar</button>
        </div>
    </form>
@endsection
