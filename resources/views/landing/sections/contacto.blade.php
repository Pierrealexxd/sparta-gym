@php
    $contacto = $gym->settings['contacto'] ?? [];

    // Unificar la sede principal con las adicionales para el carrusel.
    $todas = array_merge(
        [[
            'name'      => $gym->name,
            'address'   => $gym->address,
            'city'      => $gym->city,
            'phone'     => $gym->phone,
            'latitude'  => $gym->latitude,
            'longitude' => $gym->longitude,
        ]],
        $ubicaciones ?? []
    );

    // Filtrar las que no tienen dirección ni coordenadas.
    $todas = array_values(array_filter($todas, fn ($u) =>
        filled($u['address'] ?? null) || (filled($u['latitude'] ?? null) && filled($u['longitude'] ?? null))
    ));

    $multisede = count($todas) > 1;
@endphp

<section class="seccion" id="contacto">
    <div class="contenedor">
        <div class="seccion__cabecera" data-revelar>
            <span class="eyebrow">{{ $contacto['eyebrow'] ?? 'Contacto' }}</span>
            <h2>{{ $contacto['titulo'] ?? 'Ven a verlo' }}</h2>
            <p class="lead">{{ $contacto['lead'] ?? 'Pásate cuando quieras. La primera visita incluye una vuelta por la sala.' }}</p>
        </div>

        <div class="contacto">
            <div class="contacto__datos" data-revelar data-revelar-grupo>
                @if (!$multisede && ($gym->address || $gym->city))
                    <div class="dato">
                        <span class="dato__icono"><x-icono nombre="ubicacion" /></span>
                        <div>
                            <b>Dirección</b>
                            <span>{{ $gym->address }}{{ $gym->city ? ', ' . $gym->city : '' }}</span>
                        </div>
                    </div>
                @endif

                @if ($gym->phone)
                    <div class="dato">
                        <span class="dato__icono"><x-icono nombre="telefono" /></span>
                        <div>
                            <b>Teléfono</b>
                            <a href="tel:{{ preg_replace('/\s+/', '', $gym->phone) }}">{{ $gym->phone }}</a>
                        </div>
                    </div>
                @endif

                @if ($gym->whatsapp)
                    <div class="dato">
                        <span class="dato__icono"><x-icono nombre="whatsapp" /></span>
                        <div>
                            <b>WhatsApp</b>
                            <a href="https://wa.me/{{ preg_replace('/\D+/', '', $gym->whatsapp) }}" target="_blank" rel="noopener">{{ $gym->whatsapp }}</a>
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

                {{-- Trampa para robots --}}
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

        {{-- Sedes: carrusel si hay más de una, mapa simple si solo hay una --}}
        @if (count($todas) === 1 && ($todas[0]['latitude'] ?? null) && ($todas[0]['longitude'] ?? null))
            <div class="mapa" style="margin-top: var(--e-8)" data-revelar>
                <iframe
                    src="https://www.openstreetmap.org/export/embed.html?bbox={{ $todas[0]['longitude'] - 0.01 }}%2C{{ $todas[0]['latitude'] - 0.008 }}%2C{{ $todas[0]['longitude'] + 0.01 }}%2C{{ $todas[0]['latitude'] + 0.008 }}&amp;layer=mapnik&amp;marker={{ $todas[0]['latitude'] }}%2C{{ $todas[0]['longitude'] }}"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    title="Ubicación de {{ $todas[0]['name'] ?? $gym->name }}"></iframe>
            </div>
        @elseif ($multisede)
            <div class="ubicaciones" style="margin-top: var(--e-8)" data-revelar>
                <div class="ubicaciones__pista ubicaciones__pista--carrusel" data-carrusel="3500">
                    @foreach ($todas as $i => $ubi)
                        <article class="ubicacion tarjeta" style="scroll-snap-align:start">
                            <div class="ubicacion__encabezado">
                                <span class="ubicacion__etiqueta">{{ $ubi['name'] ?? 'Sede' }}</span>
                                @if ($ubi['city'] ?? null)
                                    <span class="ubicacion__ciudad">{{ $ubi['city'] }}</span>
                                @endif
                            </div>

                            <div class="ubicacion__datos">
                                @if ($ubi['address'] ?? null)
                                    <div class="ubicacion__dato">
                                        <x-icono nombre="ubicacion" />
                                        <span>{{ $ubi['address'] }}{{ $ubi['city'] ? ', ' . $ubi['city'] : '' }}</span>
                                    </div>
                                @endif
                                @if ($ubi['phone'] ?? null)
                                    <div class="ubicacion__dato">
                                        <x-icono nombre="telefono" />
                                        <a href="tel:{{ preg_replace('/\s+/', '', $ubi['phone']) }}">{{ $ubi['phone'] }}</a>
                                    </div>
                                @endif
                            </div>

                            @if (($ubi['latitude'] ?? null) && ($ubi['longitude'] ?? null))
                                <div class="ubicacion__mapa">
                                    <iframe
                                        src="https://www.openstreetmap.org/export/embed.html?bbox={{ $ubi['longitude'] - 0.01 }}%2C{{ $ubi['latitude'] - 0.008 }}%2C{{ $ubi['longitude'] + 0.01 }}%2C{{ $ubi['latitude'] + 0.008 }}&amp;layer=mapnik&amp;marker={{ $ubi['latitude'] }}%2C{{ $ubi['longitude'] }}"
                                        loading="lazy"
                                        referrerpolicy="no-referrer-when-downgrade"
                                        title="Ubicación de {{ $ubi['name'] ?? 'Sede' }}"></iframe>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>

                {{-- Puntos de paginación --}}
                <div class="ubicaciones__puntos" data-carrusel-puntos>
                    @foreach ($todas as $i => $_)
                        <button class="punto {{ $i === 0 ? 'is-activo' : '' }}" data-carrusel-punto aria-label="Sede {{ $i + 1 }}"></button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
