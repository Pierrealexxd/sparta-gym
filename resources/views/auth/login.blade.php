<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0A0A0B">
    <title>Login · Sparta Gym</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @vite(['resources/css/auth-entry.css', 'resources/js/app-public.js'])
</head>
<body>
    <main class="auth auth--paneles">
        <div class="hero__brasa" style="top:50%" aria-hidden="true"></div>

        <div class="auth__paneles">
            {{-- Panel visual: sin arte propio del gimnasio, así que se arma
                 con lo que el sitio ya tiene — el mismo sistema de
                 partículas del hero (particulas.js, vía data-particulas) y
                 el logotipo como marca de agua. El logo es el camino de
                 vuelta a la página pública (tocar la marca = volver a la
                 landing), por eso sale del aria-hidden: es el único
                 elemento navegable del panel, el resto sigue decorativo. --}}
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
                <div class="vidrio auth__vidrio">
                    <div class="auth__marca">
                        <a href="{{ route('landing') }}" aria-label="Sparta Gym">
                            @if ($logoUrl)
                                <img class="auth__logo" src="{{ $logoUrl }}" alt="">
                            @endif
                            <span class="nav__marca"><span>Sparta</span><em>Gym L.</em></span>
                        </a>
                    </div>

                    <header class="auth__cabecera">
                        <h1>Login</h1>
                        <p>Entra a tu panel para ver tu rutina, tus pagos y tu progreso.</p>
                    </header>

                    <form method="POST" action="{{ route('login') }}" class="auth__formulario">
                        @csrf

                        @if ($errors->any())
                            <div class="aviso aviso--error" role="alert">{{ $errors->first() }}</div>
                        @endif

                        <label class="campo">
                            <span class="campo__etiqueta">Correo</span>
                            <input class="campo__control" type="email" name="email" required autofocus
                                   value="{{ old('email') }}" autocomplete="username"
                                   placeholder="tucorreo@ejemplo.com">
                        </label>

                        <label class="campo">
                            <span class="campo__etiqueta">Contraseña</span>
                            <div class="campo__control-envoltorio">
                                <input class="campo__control" type="password" name="password" required
                                       id="login-password"
                                       autocomplete="current-password" placeholder="••••••••">
                                <span class="ojo" onclick="togglePassword('login-password')">
                                    <x-icono nombre="ojo" />
                                </span>
                            </div>
                        </label>

                        <label class="auth__recordar">
                            <input type="checkbox" name="remember" value="1">
                            Mantener la sesión abierta
                        </label>

                        <button class="btn btn--fuego btn--bloque btn--grande" type="submit">Login</button>
                    </form>

                    <footer class="auth__pie auth__pie--centro">
                        <span>
                            ¿Todavía no eres socio?
                            <a href="{{ route('registro') }}" class="btn btn--desnudo" style="font-size:var(--t-sm)">
                                Regístrate
                            </a>
                        </span>
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