@extends('layouts.panel')

@section('titulo', 'Mi perfil')

@section('contenido')
    <div class="perfil" x-data="{ guiaNutricional: false }">
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

            {{-- Recomendaciones (Fase 3): sólo para socios — ver PerfilController::mostrar(). --}}
            @if (isset($recomendacion))
                <article class="tarjeta recomendaciones">
                    <span class="tarjeta__filo"></span>
                    <h3 class="recomendaciones__titulo"><x-icono nombre="lampara" /> Recomendaciones para ti</h3>

                    <div class="recomendaciones__grid">
                        <div class="recomendacion">
                            <div class="recomendacion__icono recomendacion__icono--proteina"><x-icono nombre="proteina" /></div>
                            <div class="recomendacion__datos">
                                <small>Proteína diaria</small>
                                <b>{{ $recomendacion['proteina_g'] }} g</b>
                                <span>≈ {{ $recomendacion['proteina_scoops'] }} scoops</span>
                            </div>
                        </div>
                        <div class="recomendacion">
                            <div class="recomendacion__icono recomendacion__icono--creatina"><x-icono nombre="polvo" /></div>
                            <div class="recomendacion__datos">
                                <small>Creatina diaria</small>
                                <b>{{ $recomendacion['creatina_g'] }} g</b>
                                <span>1 cucharadita</span>
                            </div>
                        </div>
                        <div class="recomendacion">
                            <div class="recomendacion__icono recomendacion__icono--agua"><x-icono nombre="gota" /></div>
                            <div class="recomendacion__datos">
                                <small>Agua diaria</small>
                                <b>{{ $recomendacion['agua_litros'] }} L</b>
                                <span>{{ $recomendacion['agua_ml'] }} ml</span>
                            </div>
                        </div>
                        <div class="recomendacion">
                            <div class="recomendacion__icono recomendacion__icono--calorias"><x-icono nombre="llama" /></div>
                            <div class="recomendacion__datos">
                                <small>Calorías estimadas</small>
                                <b>{{ number_format($recomendacion['calorias']) }} kcal</b>
                                <span>{{ \App\Services\NutritionAdvisor::objetivoLegible($recomendacion['objetivo']) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="recomendaciones__macros">
                        <h4>Distribución de macros</h4>
                        <div class="macro-barra">
                            <div class="macro" style="flex:{{ $recomendacion['proteina_g'] }}">
                                <b>{{ $recomendacion['proteina_g'] }}g</b><small>Proteína</small>
                            </div>
                            <div class="macro macro--carbs" style="flex:{{ $recomendacion['carbs_g'] }}">
                                <b>{{ $recomendacion['carbs_g'] }}g</b><small>Carbos</small>
                            </div>
                            <div class="macro macro--grasas" style="flex:{{ $recomendacion['grasas_g'] }}">
                                <b>{{ $recomendacion['grasas_g'] }}g</b><small>Grasas</small>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn--vidrio recomendaciones__guia" @click="guiaNutricional = true">
                        <x-icono nombre="libro" /> Ver guía completa
                    </button>
                </article>
            @endif

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

    {{-- Modal "Guía completa" (Fase 3): resumen + tablas de referencia +
         recetas — mismo patrón modal__fondo que progreso.blade.php. --}}
    @if (isset($recomendacion))
        @php
            $formasCortasPerfil = ['palma' => 'Palma', 'puno' => 'Puño', 'cuenco' => 'Cuenco', 'pulgar' => 'Pulgar'];
        @endphp
        <div class="modal__fondo" x-show="guiaNutricional" x-cloak @keydown.escape.window="guiaNutricional = false">
            <div class="tarjeta modal__caja modal__caja--ancho" @click.outside="guiaNutricional = false">
                <div class="modal__cabecera">
                    <h3>Tu guía nutricional</h3>
                    <button class="modal__cerrar" type="button" @click="guiaNutricional = false" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
                </div>

                <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-5)">
                    {{ number_format($recomendacion['calorias']) }} kcal/día · {{ $recomendacion['proteina_g'] }} g proteína ·
                    {{ $recomendacion['agua_litros'] }} L de agua · {{ $recomendacion['creatina_g'] }} g de creatina.
                    Calculado con tu peso, altura, edad y objetivo activo.
                </p>

                <div class="tabla-envoltorio" style="margin-bottom:var(--e-5)">
                    <table class="tabla tabla--tarjetas">
                        <thead><tr><th>Porción</th><th>Mide</th><th>Equivale a</th></tr></thead>
                        <tbody>
                            <tr><td class="es-fuerte" data-etiqueta="Porción">Palma</td><td data-etiqueta="Mide">Proteína</td><td data-etiqueta="Equivale a">~100 g carne/tofu, 1 yogurt griego, 2 huevos</td></tr>
                            <tr><td class="es-fuerte" data-etiqueta="Porción">Puño</td><td data-etiqueta="Mide">Verduras</td><td data-etiqueta="Equivale a">~1 taza (brócoli, espinaca, zanahoria)</td></tr>
                            <tr><td class="es-fuerte" data-etiqueta="Porción">Cuenco</td><td data-etiqueta="Mide">Carbohidratos</td><td data-etiqueta="Equivale a">~½-⅔ taza arroz/quinoa, 1 fruta, 1 tubérculo</td></tr>
                            <tr><td class="es-fuerte" data-etiqueta="Porción">Pulgar</td><td data-etiqueta="Mide">Grasas</td><td data-etiqueta="Equivale a">~1 cda aceite, mantequilla de maní, queso, frutos secos</td></tr>
                        </tbody>
                    </table>
                </div>

                @if (($recetasMasa ?? collect())->isNotEmpty())
                    <h4 style="font-size:var(--t-sm);text-transform:uppercase;letter-spacing:.04em;color:var(--ceniza);margin-bottom:var(--e-3)">Recetas para ganar masa</h4>
                    <div style="display:grid;gap:var(--e-2);margin-bottom:var(--e-5)">
                        @foreach ($recetasMasa as $receta)
                            @php $resumenReceta = collect($receta->conteo)->filter()->map(fn ($n, $k) => "{$n} {$formasCortasPerfil[$k]}" . ($n === 1 ? '' : 's'))->implode(' · '); @endphp
                            <div style="display:flex;justify-content:space-between;gap:var(--e-3);padding:var(--e-3);background:var(--relleno-suave);border-radius:var(--r-md)">
                                <b style="color:var(--hueso);font-size:var(--t-sm)">{{ $receta->name }}</b>
                                <span style="color:var(--ceniza);font-size:var(--t-xs);font-family:var(--f-mono)">{{ $resumenReceta ?: '—' }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if (($recetasGrasa ?? collect())->isNotEmpty())
                    <h4 style="font-size:var(--t-sm);text-transform:uppercase;letter-spacing:.04em;color:var(--ceniza);margin-bottom:var(--e-3)">Recetas para perder grasa</h4>
                    <div style="display:grid;gap:var(--e-2);margin-bottom:var(--e-5)">
                        @foreach ($recetasGrasa as $receta)
                            @php $resumenReceta = collect($receta->conteo)->filter()->map(fn ($n, $k) => "{$n} {$formasCortasPerfil[$k]}" . ($n === 1 ? '' : 's'))->implode(' · '); @endphp
                            <div style="display:flex;justify-content:space-between;gap:var(--e-3);padding:var(--e-3);background:var(--relleno-suave);border-radius:var(--r-md)">
                                <b style="color:var(--hueso);font-size:var(--t-sm)">{{ $receta->name }}</b>
                                <span style="color:var(--ceniza);font-size:var(--t-xs);font-family:var(--f-mono)">{{ $resumenReceta ?: '—' }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="aviso">
                    <b style="display:block;color:var(--hueso);margin-bottom:var(--e-1)">Hidratación y suplementación</b>
                    Reparte tu agua a lo largo del día, no toda de golpe. La creatina se toma a cualquier hora —no hace falta fase de carga— y la proteína en polvo es sólo un atajo cuando no llegas con comida a tu palma diaria.
                </div>
            </div>
        </div>
    @endif
@endsection
