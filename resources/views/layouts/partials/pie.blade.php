<footer class="pie">
    <div class="contenedor">
        <div class="pie__rejilla">
            <div>
                <a class="nav__marca" href="{{ route('landing') }}">
                    <span>Sparta</span><em>Gym</em>
                </a>
                <p style="margin-top: var(--e-4); color: var(--ceniza); font-size: var(--t-sm); max-width: 32ch;">
                    {{ $gym->description }}
                </p>
            </div>

            <div>
                <h4>Secciones</h4>
                <ul class="pie__lista">
                    <li><a href="#historia">Historia</a></li>
                    <li><a href="#ejercicios">Biblioteca</a></li>
                    <li><a href="#guias">Guías</a></li>
                    <li><a href="#planes">Planes</a></li>
                </ul>
            </div>

            <div>
                <h4>Contacto</h4>
                <ul class="pie__lista">
                    @if ($gym->phone)
                        <li><a href="tel:{{ preg_replace('/\s+/', '', $gym->phone) }}">{{ $gym->phone }}</a></li>
                    @endif
                    @if ($gym->email)
                        <li><a href="mailto:{{ $gym->email }}">{{ $gym->email }}</a></li>
                    @endif
                    <li><span style="color: var(--ceniza)">{{ $gym->address }}, {{ $gym->city }}</span></li>
                </ul>
            </div>

            <div>
                <h4>Síguenos</h4>
                <div class="pie__redes">
                    @foreach (array_filter($gym->socials ?? []) as $red => $url)
                        <a class="pie__red" href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                           aria-label="{{ ucfirst($red) }}">
                            <x-icono :nombre="$red" />
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="pie__legal">
            <span>© {{ date('Y') }} {{ $gym->name }}. Todos los derechos reservados.</span>
            <span style="font-family: var(--f-mono); letter-spacing: .2em; text-transform: uppercase;">
                {{ $gym->tagline }}
            </span>
        </div>
    </div>
</footer>
