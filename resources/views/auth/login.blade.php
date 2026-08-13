<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A0A0B">
    <title>Login · Sparta Gym</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app-public.js'])
</head>
<body>
    <main class="auth">
        <div class="hero__brasa" style="top:50%" aria-hidden="true"></div>

        <div class="vidrio auth__vidrio">
            <div class="auth__marca">
                <a class="nav__marca" href="{{ route('landing') }}">
                    <span>Sparta</span><em>Gym</em>
                </a>
            </div>

            <header class="auth__cabecera">
                <h1>Login</h1>
                <p>Entra a tu panel para ver tu rutina, tus pagos y tu progreso.</p>
            </header>

            <form method="POST" action="{{ route('login') }}" class="auth__formulario">
                @csrf

                @error('email')
                    <div class="aviso aviso--error" role="alert">{{ $message }}</div>
                @enderror

                <label class="campo">
                    <span class="campo__etiqueta">Correo</span>
                    <input class="campo__control" type="email" name="email" required autofocus
                           value="{{ old('email') }}" autocomplete="username"
                           placeholder="tucorreo@ejemplo.com">
                </label>

                <label class="campo">
                    <span class="campo__etiqueta">Contraseña</span>
                    <input class="campo__control" type="password" name="password" required
                           id="login-password"
                           autocomplete="current-password" placeholder="••••••••">
                    <span class="ojo" onclick="togglePassword('login-password')">
                        <x-icono nombre="ojo" />
                    </span>
                </label>

                <label class="auth__recordar">
                    <input type="checkbox" name="remember" value="1">
                    Mantener la sesión abierta
                </label>

                <button class="btn btn--fuego btn--bloque btn--grande" type="submit">Login</button>
            </form>

            <footer class="auth__pie">
                <span>
                    ¿Todavía no eres socio?
                    <a href="{{ route('registro') }}" class="btn btn--desnudo" style="font-size:var(--t-sm)">
                        Regístrate
                    </a>
                </span>
                <a href="{{ route('landing') }}#planes" class="btn btn--desnudo" style="font-size:var(--t-sm)">
                    Ver planes
                </a>
            </footer>
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