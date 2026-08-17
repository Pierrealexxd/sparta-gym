<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A0A0B">
    <title>Crear cuenta · Sparta Gym</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @vite(['resources/css/auth-entry.css', 'resources/js/app-public.js'])
</head>
<body>
    <main class="auth auth--paneles">
        <div class="hero__brasa" style="top:50%" aria-hidden="true"></div>

        <div class="auth__paneles">
            {{-- Ver login.blade.php: mismo panel visual, sin arte propio del
                 gimnasio — partículas del hero + el logotipo como marca de
                 agua, ambos ya existentes en el sitio. El logo enlaza a la
                 landing (tocar la marca = volver a la página pública); por
                 eso es el único elemento fuera del aria-hidden. --}}
            <section class="auth__panel-visual">
                <canvas data-particulas aria-hidden="true"></canvas>

                @if ($logoUrl)
                    <div class="auth__panel-agua">
                        <a href="{{ route('landing') }}" class="auth__panel-agua__enlace"
                           aria-label="Volver a la página principal de Sparta Gym">
                            <img src="{{ $logoUrl }}" alt="">
                        </a>
                    </div>
                @endif

                <p class="auth__panel-pie" aria-hidden="true">© {{ date('Y') }} Sparta Gym</p>
            </section>

            <section class="auth__panel-formulario">
                <div class="vidrio auth__vidrio auth__vidrio--ancho">
                    <div class="auth__marca">
                        <a href="{{ route('landing') }}" aria-label="Sparta Gym">
                            @if ($logoUrl)
                                <img class="auth__logo" src="{{ $logoUrl }}" alt="">
                            @endif
                            <span class="nav__marca"><span>Sparta</span><em>Gym</em></span>
                        </a>
                    </div>

                    <header class="auth__cabecera">
                        <h1>Crear cuenta</h1>
                        <p>Regístrate para ver tu progreso, tu rutina y tus pagos. La matrícula de tu plan la haces en el gimnasio.</p>
                    </header>

                    <form method="POST" action="{{ route('registro.store') }}" class="auth__formulario">
                        @csrf

                        @if ($errors->any())
                            <div class="aviso aviso--error" role="alert">{{ $errors->first() }}</div>
                        @endif

                        <div class="formulario-panel__fila">
                            <label class="campo">
                                <span class="campo__etiqueta">Nombres</span>
                                <input class="campo__control" type="text" name="first_name" required
                                       value="{{ old('first_name') }}" autofocus>
                            </label>
                            <label class="campo">
                                <span class="campo__etiqueta">Apellidos</span>
                                <input class="campo__control" type="text" name="last_name" required
                                       value="{{ old('last_name') }}">
                            </label>
                        </div>

                        <label class="campo">
                            <span class="campo__etiqueta">Correo</span>
                            <input class="campo__control" type="email" name="email" required
                                   value="{{ old('email') }}" autocomplete="username"
                                   placeholder="tucorreo@ejemplo.com">
                        </label>

                        <label class="campo">
                            <span class="campo__etiqueta">Teléfono (opcional)</span>
                            <input class="campo__control" type="text" name="phone" value="{{ old('phone') }}">
                        </label>

                        @if ($gyms->count() > 1)
                            {{-- Solo aparece si hay más de una sede — con una sola,
                                 asignarla es automático y preguntar sería ruido. --}}
                            <label class="campo">
                                <span class="campo__etiqueta">¿De qué sede eres?</span>
                                <select class="campo__control" name="gym_id" required>
                                    <option value="">— Elige tu sede —</option>
                                    @foreach ($gyms as $gimnasio)
                                        <option value="{{ $gimnasio->id }}" @selected((int) old('gym_id') === $gimnasio->id)>{{ $gimnasio->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif

                        <div class="formulario-panel__fila">
                            <label class="campo">
                                <span class="campo__etiqueta">Contraseña</span>
                                <div class="campo__control-envoltorio">
                                    <input class="campo__control" type="password" name="password" required
                                           id="registro-password"
                                           minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                                    <span class="ojo" onclick="togglePassword('registro-password')">
                                        <x-icono nombre="ojo" />
                                    </span>
                                </div>
                            </label>
                            <label class="campo">
                                <span class="campo__etiqueta">Repetir contraseña</span>
                                <div class="campo__control-envoltorio">
                                    <input class="campo__control" type="password" name="password_confirmation" required
                                           id="registro-password-confirm"
                                           minlength="8" autocomplete="new-password">
                                    <span class="ojo" onclick="togglePassword('registro-password-confirm')">
                                        <x-icono nombre="ojo" />
                                    </span>
                                </div>
                            </label>
                        </div>

                        <button class="btn btn--fuego btn--bloque btn--grande" type="submit">Crear mi cuenta</button>
                    </form>

                    <footer class="auth__pie auth__pie--centro">
                        ¿Ya tienes cuenta?
                        <a href="{{ route('login') }}" class="btn btn--desnudo" style="font-size:var(--t-sm)">
                            Login
                        </a>
                    </footer>
                </div>
            </section>
        </div>

        <script>
            function togglePassword(fieldId) {
                const input = document.getElementById(fieldId);
                if (input.type === 'password') {
                    input.type = 'text';
                } else {
                    input.type = 'password';
                }
            }
        </script>
    </main>
</body>
</html>