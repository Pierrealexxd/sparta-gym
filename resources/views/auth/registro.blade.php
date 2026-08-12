<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A0A0B">
    <title>Crear cuenta · Sparta Gym</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="auth">
        <div class="hero__brasa" style="top:50%" aria-hidden="true"></div>

        <div class="vidrio auth__vidrio auth__vidrio--ancho">
            <div class="auth__marca">
                <a class="nav__marca" href="{{ route('landing') }}">
                    <span>Sparta</span><em>Gym</em>
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
                        <input class="campo__control" type="password" name="password" required
                               minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                    </label>
                    <label class="campo">
                        <span class="campo__etiqueta">Repetir contraseña</span>
                        <input class="campo__control" type="password" name="password_confirmation" required
                               minlength="8" autocomplete="new-password">
                    </label>
                </div>

                <button class="btn btn--fuego btn--bloque btn--grande" type="submit">Crear mi cuenta</button>
            </form>

            <footer class="auth__pie auth__pie--centro">
                ¿Ya tienes cuenta?
                <a href="{{ route('login') }}" class="btn btn--desnudo" style="font-size:var(--t-sm)">
                    Entrar
                </a>
            </footer>
        </div>
    </main>
</body>
</html>
