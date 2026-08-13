@extends('layouts.panel')

@section('titulo', 'Mi perfil')

@section('contenido')
    <div class="perfil">
        <div class="perfil__columna">
            <article class="tarjeta perfil__resumen">
                <div class="perfil__avatar" x-data="{ vista: null }">
                    @if ($usuario->avatar_path)
                        <img src="{{ asset('storage/' . $usuario->avatar_path) }}" alt="Foto de perfil">
                    @else
                        <span class="perfil__iniciales">{{ $usuario->iniciales }}</span>
                    @endif
                </div>
                <h2 class="perfil__nombre">{{ $usuario->name }}</h2>
                <p class="perfil__rol">{{ $usuario->role?->name }}</p>
                @if ($usuario->member)
                    <div class="perfil__dato">
                        <span>Código de socio</span>
                        <b>{{ $usuario->member->code }}</b>
                    </div>
                @endif
                @if ($usuario->member)
                    <div class="perfil__dato">
                        <span>Socio desde</span>
                        <b>{{ $usuario->member->joined_at?->translatedFormat('d M Y') ?? '—' }}</b>
                    </div>
                @endif
                <a class="btn btn--vidrio perfil__wa"
                   href="https://wa.me/{{ preg_replace('/\D+/', '', $usuario->phone ?? '') }}"
                   target="_blank" rel="noopener">
                    <x-icono nombre="telefono" /> Mi WhatsApp
                </a>
            </article>
        </div>

        <div class="perfil__columna">
            <form class="tarjeta formulario-panel" method="POST" action="{{ route('perfil.actualizar') }}"
                  enctype="multipart/form-data">
                @csrf

                <h3 class="perfil__seccion-titulo">Foto de perfil</h3>
                <div class="perfil__foto-campo" x-data="{ vista: null }">
                    <div class="perfil__avatar perfil__avatar--vista">
                        <template x-if="vista">
                            <img :src="vista" alt="Vista previa">
                        </template>
                        <template x-if="!vista">
                            @if ($usuario->avatar_path)
                                <img src="{{ asset('storage/' . $usuario->avatar_path) }}" alt="Foto de perfil">
                            @else
                                <span class="perfil__iniciales">{{ $usuario->iniciales }}</span>
                            @endif
                        </template>
                    </div>
                    <label class="campo" style="flex:1">
                        <span class="campo__etiqueta">Imagen (JPG, PNG o WebP · máx 3 MB)</span>
                        <input class="campo__control" type="file" name="avatar" accept="image/*"
                               @change="vista = URL.createObjectURL($event.target.files[0])">
                        @error('avatar')<span class="campo__error">{{ $message }}</span>@enderror
                    </label>
                </div>

                <h3 class="perfil__seccion-titulo">Credenciales</h3>
                <div class="formulario-panel__fila">
                    @if ($usuario->member)
                        <label class="campo">
                            <span class="campo__etiqueta">Nombres</span>
                            <input class="campo__control" type="text" name="first_name" required maxlength="80"
                                   value="{{ old('first_name', $usuario->member->first_name) }}">
                            @error('first_name')<span class="campo__error">{{ $message }}</span>@enderror
                        </label>
                        <label class="campo">
                            <span class="campo__etiqueta">Apellidos</span>
                            <input class="campo__control" type="text" name="last_name" required maxlength="120"
                                   value="{{ old('last_name', $usuario->member->last_name) }}">
                            @error('last_name')<span class="campo__error">{{ $message }}</span>@enderror
                        </label>
                    @else
                        <label class="campo">
                            <span class="campo__etiqueta">Nombre completo</span>
                            <input class="campo__control" type="text" name="name" required maxlength="120"
                                   value="{{ old('name', $usuario->name) }}">
                            @error('name')<span class="campo__error">{{ $message }}</span>@enderror
                        </label>
                    @endif
                </div>

                <div class="formulario-panel__fila">
                    <label class="campo">
                        <span class="campo__etiqueta">Correo</span>
                        <input class="campo__control" type="email" name="email" required maxlength="180"
                               value="{{ old('email', $usuario->email) }}">
                        @error('email')<span class="campo__error">{{ $message }}</span>@enderror
                    </label>
                    <label class="campo">
                        <span class="campo__etiqueta">Teléfono (opcional)</span>
                        <input class="campo__control" type="text" name="phone" maxlength="40"
                               placeholder="9XX XXX XXX"
                               value="{{ old('phone', $usuario->phone) }}">
                        @error('phone')<span class="campo__error">{{ $message }}</span>@enderror
                    </label>
                </div>

                @unless ($usuario->member)
                    {{-- Staff (hoy solo entrenadores lo usan): el DNI se carga
                         una vez acá y de ahí lo toma el escaneo QR de
                         asistencia laboral — no se vuelve a pedir cada vez
                         que marca. --}}
                    <div class="formulario-panel__fila">
                        <label class="campo">
                            <span class="campo__etiqueta">DNI (opcional)</span>
                            <input class="campo__control" type="text" name="dni" maxlength="20"
                                   placeholder="Para el escaneo QR de asistencia"
                                   value="{{ old('dni', $usuario->dni) }}">
                            @error('dni')<span class="campo__error">{{ $message }}</span>@enderror
                        </label>
                    </div>
                @endunless

                @if ($usuario->member)
                    <h3 class="perfil__seccion-titulo">Datos personales</h3>
                    <div class="formulario-panel__fila">
                        <label class="campo">
                            <span class="campo__etiqueta">Nacimiento</span>
                            <input class="campo__control" type="date" name="birth_date"
                                   value="{{ old('birth_date', $usuario->member->birth_date?->toDateString()) }}">
                            @error('birth_date')<span class="campo__error">{{ $message }}</span>@enderror
                        </label>
                        <label class="campo">
                            <span class="campo__etiqueta">Género</span>
                            <select class="campo__control" name="gender">
                                <option value="">—</option>
                                <option value="M" @selected(old('gender', $usuario->member->gender) === 'M')>Masculino</option>
                                <option value="F" @selected(old('gender', $usuario->member->gender) === 'F')>Femenino</option>
                                <option value="O" @selected(old('gender', $usuario->member->gender) === 'O')>Otro</option>
                            </select>
                        </label>
                        <label class="campo">
                            <span class="campo__etiqueta">Altura (cm)</span>
                            <input class="campo__control" type="number" name="height_cm" min="100" max="260"
                                   value="{{ old('height_cm', $usuario->member->height_cm) }}">
                            @error('height_cm')<span class="campo__error">{{ $message }}</span>@enderror
                        </label>
                    </div>
                    <div class="formulario-panel__fila">
                        <label class="campo">
                            <span class="campo__etiqueta">Contacto de emergencia</span>
                            <input class="campo__control" type="text" name="emergency_contact" maxlength="120"
                                   value="{{ old('emergency_contact', $usuario->member->emergency_contact) }}">
                        </label>
                        <label class="campo">
                            <span class="campo__etiqueta">Teléfono de emergencia</span>
                            <input class="campo__control" type="text" name="emergency_phone" maxlength="40"
                                   value="{{ old('emergency_phone', $usuario->member->emergency_phone) }}">
                        </label>
                    </div>
                @endif

                <div class="formulario-panel__acciones">
                    <button class="btn btn--fuego" type="submit">Guardar cambios</button>
                </div>
            </form>

            <form class="tarjeta formulario-panel" method="POST" action="{{ route('perfil.password') }}">
                @csrf
                <h3 class="perfil__seccion-titulo">Credenciales · contraseña</h3>
                <p class="perfil__ayuda">Necesitas tu contraseña actual para cambiarla.</p>

                <div class="formulario-panel__fila">
                    <label class="campo">
                        <span class="campo__etiqueta">Contraseña actual</span>
                        <input class="campo__control" type="password" name="password_actual" required autocomplete="current-password">
                        @error('password_actual')<span class="campo__error">{{ $message }}</span>@enderror
                    </label>
                </div>
                <div class="formulario-panel__fila">
                    <label class="campo">
                        <span class="campo__etiqueta">Nueva contraseña</span>
                        <input class="campo__control" type="password" name="password" required minlength="8" autocomplete="new-password">
                        @error('password')<span class="campo__error">{{ $message }}</span>@enderror
                    </label>
                    <label class="campo">
                        <span class="campo__etiqueta">Confirmar nueva contraseña</span>
                        <input class="campo__control" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                    </label>
                </div>

                <div class="formulario-panel__acciones">
                    <button class="btn btn--vidrio" type="submit">Cambiar contraseña</button>
                </div>
            </form>
        </div>
    </div>
@endsection
