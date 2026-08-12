<section class="seccion" id="contacto">
    <div class="contenedor">
        <div class="seccion__cabecera" data-revelar>
            <span class="eyebrow">Contacto</span>
            <h2>Ven a verlo</h2>
            <p class="lead">Pásate cuando quieras. La primera visita incluye una vuelta por la sala.</p>
        </div>

        <div class="contacto">
            <div class="contacto__datos" data-revelar data-revelar-grupo>
                <div class="dato">
                    <span class="dato__icono"><x-icono nombre="ubicacion" /></span>
                    <div>
                        <b>Dirección</b>
                        <span>{{ $gym->address }}, {{ $gym->city }}</span>
                    </div>
                </div>

                @if ($gym->phone)
                    <div class="dato">
                        <span class="dato__icono"><x-icono nombre="telefono" /></span>
                        <div>
                            <b>Teléfono</b>
                            <a href="tel:{{ preg_replace('/\s+/', '', $gym->phone) }}">{{ $gym->phone }}</a>
                        </div>
                    </div>
                @endif

                @if ($gym->email)
                    <div class="dato">
                        <span class="dato__icono"><x-icono nombre="correo" /></span>
                        <div>
                            <b>Correo</b>
                            <a href="mailto:{{ $gym->email }}">{{ $gym->email }}</a>
                        </div>
                    </div>
                @endif

                <div class="dato">
                    <span class="dato__icono"><x-icono nombre="reloj" /></span>
                    <div style="width:100%">
                        <b>Horario</b>
                        <div class="horario">
                            @foreach ($gym->schedule ?? [] as $franja)
                                <div class="horario__fila">
                                    <span>{{ $franja['dia'] }}</span>
                                    <span>{{ $franja['abre'] }} – {{ $franja['cierra'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <form class="vidrio formulario" method="POST" action="{{ route('contacto.enviar') }}" data-revelar>
                @csrf

                @if (session('exito'))
                    <div class="aviso aviso--exito" role="status">{{ session('exito') }}</div>
                @endif

                <div class="formulario__fila">
                    <label class="campo">
                        <span class="campo__etiqueta">Nombre</span>
                        <input class="campo__control" type="text" name="name" required
                               value="{{ old('name') }}" placeholder="Tu nombre"
                               @error('name') aria-invalid="true" @enderror>
                        @error('name') <span class="campo__error">{{ $message }}</span> @enderror
                    </label>

                    <label class="campo">
                        <span class="campo__etiqueta">Teléfono</span>
                        <input class="campo__control" type="tel" name="phone"
                               value="{{ old('phone') }}" placeholder="9XX XXX XXX">
                    </label>
                </div>

                <label class="campo">
                    <span class="campo__etiqueta">Correo</span>
                    <input class="campo__control" type="email" name="email"
                           value="{{ old('email') }}" placeholder="tucorreo@ejemplo.com"
                           @error('email') aria-invalid="true" @enderror>
                    @error('email') <span class="campo__error">{{ $message }}</span> @enderror
                </label>

                <label class="campo">
                    <span class="campo__etiqueta">Plan que te interesa</span>
                    <select class="campo__control" name="interested_in">
                        <option value="">Todavía no lo sé</option>
                        @foreach ($planes as $plan)
                            <option value="{{ $plan->name }}" @selected(old('interested_in') === $plan->name)>
                                {{ $plan->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="campo">
                    <span class="campo__etiqueta">Mensaje</span>
                    <textarea class="campo__control" name="message" required
                              placeholder="¿En qué podemos ayudarte?"
                              @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea>
                    @error('message') <span class="campo__error">{{ $message }}</span> @enderror
                </label>

                {{-- Trampa para robots: invisible y fuera del orden de tabulación.
                     Una persona nunca la rellena; un bot automático, casi siempre. --}}
                <div aria-hidden="true" style="position:absolute;left:-9999px">
                    <label>No rellenar
                        <input type="text" name="website" tabindex="-1" autocomplete="off">
                    </label>
                </div>

                <button class="btn btn--fuego btn--bloque btn--grande" type="submit">
                    Enviar mensaje
                </button>
            </form>
        </div>

        {{-- Mapa: se carga en diferido para no penalizar la primera pintada --}}
        @if ($gym->latitude && $gym->longitude)
            <div class="mapa" style="margin-top: var(--e-8)" data-revelar>
                <iframe
                    src="https://www.openstreetmap.org/export/embed.html?bbox={{ $gym->longitude - 0.01 }}%2C{{ $gym->latitude - 0.008 }}%2C{{ $gym->longitude + 0.01 }}%2C{{ $gym->latitude + 0.008 }}&amp;layer=mapnik&amp;marker={{ $gym->latitude }}%2C{{ $gym->longitude }}"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    title="Ubicación de {{ $gym->name }}"></iframe>
            </div>
        @endif
    </div>
</section>
