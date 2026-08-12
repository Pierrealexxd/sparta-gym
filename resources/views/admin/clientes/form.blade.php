@extends('layouts.panel')

{{-- Solo edición: "Nuevo cliente" vive como modal de admin.clientes.index
     (ver ese archivo) desde que se sacó "create" del resource. --}}
@section('titulo', 'Editar cliente')

@section('contenido')
    <form class="tarjeta formulario-panel" method="POST"
          action="{{ route('admin.clientes.update', $cliente) }}"
          enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="formulario-panel__fila">
            <label class="campo">
                <span class="campo__etiqueta">Nombres</span>
                <input class="campo__control" type="text" name="first_name" required
                       value="{{ old('first_name', $cliente->first_name) }}">
                @error('first_name')<span class="campo__error">{{ $message }}</span>@enderror
            </label>
            <label class="campo">
                <span class="campo__etiqueta">Apellidos</span>
                <input class="campo__control" type="text" name="last_name" required
                       value="{{ old('last_name', $cliente->last_name) }}">
                @error('last_name')<span class="campo__error">{{ $message }}</span>@enderror
            </label>
        </div>

        <div class="formulario-panel__fila">
            <label class="campo">
                <span class="campo__etiqueta">Documento</span>
                <input class="campo__control" type="text" name="document" value="{{ old('document', $cliente->document) }}">
            </label>
            <label class="campo">
                <span class="campo__etiqueta">Teléfono</span>
                <input class="campo__control" type="text" name="phone" value="{{ old('phone', $cliente->phone) }}">
            </label>
            <label class="campo">
                <span class="campo__etiqueta">Correo</span>
                <input class="campo__control" type="email" name="email" value="{{ old('email', $cliente->email) }}">
            </label>
        </div>

        <div class="formulario-panel__fila">
            <label class="campo">
                <span class="campo__etiqueta">Nacimiento</span>
                <input class="campo__control" type="date" name="birth_date" value="{{ old('birth_date', $cliente->birth_date?->toDateString()) }}">
            </label>
            <label class="campo">
                <span class="campo__etiqueta">Género</span>
                <select class="campo__control" name="gender">
                    <option value="">—</option>
                    <option value="M" @selected(old('gender', $cliente->gender) === 'M')>Masculino</option>
                    <option value="F" @selected(old('gender', $cliente->gender) === 'F')>Femenino</option>
                    <option value="O" @selected(old('gender', $cliente->gender) === 'O')>Otro</option>
                </select>
            </label>
            <label class="campo">
                <span class="campo__etiqueta">Altura (cm)</span>
                <input class="campo__control" type="number" name="height_cm" value="{{ old('height_cm', $cliente->height_cm) }}">
            </label>
            <label class="campo">
                <span class="campo__etiqueta">Estado</span>
                <select class="campo__control" name="status" required>
                    @foreach (['activo' => 'Activo', 'inactivo' => 'Inactivo', 'suspendido' => 'Suspendido'] as $v => $l)
                        <option value="{{ $v }}" @selected(old('status', $cliente->status ?? 'activo') === $v)>{{ $l }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="formulario-panel__fila">
            <label class="campo">
                <span class="campo__etiqueta">Contacto de emergencia</span>
                <input class="campo__control" type="text" name="emergency_contact" value="{{ old('emergency_contact', $cliente->emergency_contact) }}">
            </label>
            <label class="campo">
                <span class="campo__etiqueta">Teléfono de emergencia</span>
                <input class="campo__control" type="text" name="emergency_phone" value="{{ old('emergency_phone', $cliente->emergency_phone) }}">
            </label>
        </div>

        <label class="campo">
            <span class="campo__etiqueta">Notas médicas</span>
            <textarea class="campo__control" name="medical_notes">{{ old('medical_notes', $cliente->medical_notes) }}</textarea>
        </label>

        <label class="campo">
            <span class="campo__etiqueta">Fotografía</span>
            <input class="campo__control" type="file" name="foto" accept="image/*">
        </label>

        <label class="campo">
            <span class="campo__etiqueta">Notas</span>
            <textarea class="campo__control" name="notes">{{ old('notes', $cliente->notes) }}</textarea>
        </label>

        <div class="formulario-panel__acciones">
            <a class="btn btn--vidrio" href="{{ route('admin.clientes.index') }}">Cancelar</a>
            <button class="btn btn--fuego" type="submit">Guardar</button>
        </div>
    </form>
@endsection
