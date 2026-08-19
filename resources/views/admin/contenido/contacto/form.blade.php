@extends('layouts.panel')

@section('titulo', 'Contenido web')
@section('subtitulo', 'Contacto de la página pública')

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9/dist/leaflet.css" />

@section('acciones')
    <div style="display:flex;gap:var(--e-3)">
        <button class="btn btn--vidrio" type="button"
                @click="window.dispatchEvent(new CustomEvent('abrir-preview', { detail: { seccion: 'contacto' } }))">
            <x-icono nombre="ojo" /> Previsualizar
        </button>
    </div>
@endsection

@section('contenido')
    @include('admin.contenido._pestanas')

    <form class="tarjeta formulario-panel" method="POST" action="{{ route('admin.contenido.contacto.guardar') }}">
        @csrf

        <h3 class="contacto-admin__titulo">Cabecera de la sección</h3>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Etiqueta</span>
                <input class="campo__control" type="text" name="contacto[eyebrow]" maxlength="40"
                       value="{{ old('contacto.eyebrow', $gym->settings['contacto']['eyebrow'] ?? 'Contacto') }}">
                @error('contacto.eyebrow')<span class="campo__error">{{ $message }}</span>@enderror
            </label>
            <label class="campo"><span class="campo__etiqueta">Título</span>
                <input class="campo__control" type="text" name="contacto[titulo]" maxlength="120"
                       value="{{ old('contacto.titulo', $gym->settings['contacto']['titulo'] ?? 'Ven a verlo') }}">
                @error('contacto.titulo')<span class="campo__error">{{ $message }}</span>@enderror
            </label>
        </div>

        <label class="campo"><span class="campo__etiqueta">Texto de apoyo</span>
            <textarea class="campo__control" name="contacto[lead]" maxlength="300" style="min-height:5rem"
                      placeholder="Pásate cuando quieras. La primera visita incluye una vuelta por la sala.">{{ old('contacto.lead', $gym->settings['contacto']['lead'] ?? 'Pásate cuando quieras. La primera visita incluye una vuelta por la sala.') }}</textarea>
            @error('contacto.lead')<span class="campo__error">{{ $message }}</span>@enderror
        </label>

        <h3 class="contacto-admin__titulo">Datos</h3>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Dirección</span>
                <input class="campo__control" type="text" name="address" maxlength="200" value="{{ old('address', $gym->address) }}">
                @error('address')<span class="campo__error">{{ $message }}</span>@enderror
            </label>
            <label class="campo"><span class="campo__etiqueta">Ciudad</span>
                <input class="campo__control" type="text" name="city" maxlength="80" value="{{ old('city', $gym->city) }}">
            </label>
        </div>

        <div class="formulario-panel__fila">
            <label class="campo"><span class="campo__etiqueta">Teléfono</span>
                <input class="campo__control" type="text" name="phone" maxlength="40" value="{{ old('phone', $gym->phone) }}">
                @error('phone')<span class="campo__error">{{ $message }}</span>@enderror
            </label>
            <label class="campo"><span class="campo__etiqueta">Correo</span>
                <input class="campo__control" type="email" name="email" maxlength="180" value="{{ old('email', $gym->email) }}">
                @error('email')<span class="campo__error">{{ $message }}</span>@enderror
            </label>
        </div>

        <label class="campo"><span class="campo__etiqueta">WhatsApp</span>
            <input class="campo__control" type="text" name="whatsapp" maxlength="40" value="{{ old('whatsapp', $gym->whatsapp) }}" placeholder="+51 999 999 999">
            @error('whatsapp')<span class="campo__error">{{ $message }}</span>@enderror
        </label>

        {{-- Horario: una fila por franja (día / abre / cierra) --}}
        <div x-data="{
                filas: {{ \Illuminate\Support\Js::from(old('schedule', $gym->schedule ?: [['dia' => '', 'abre' => '', 'cierra' => '']])) }}
             }">
            <span class="campo__etiqueta">Horario</span>
            <template x-for="(fila, i) in filas" :key="i">
                <div class="fila-borrable">
                    <div class="formulario-panel__fila">
                        <label class="campo"><span class="campo__etiqueta">Día</span>
                            <input class="campo__control" type="text" :name="`schedule[${i}][dia]`" x-model="fila.dia" placeholder="Lunes a viernes"></label>
                        <label class="campo"><span class="campo__etiqueta">Abre</span>
                            <input class="campo__control" type="text" :name="`schedule[${i}][abre]`" x-model="fila.abre" placeholder="05:00"></label>
                        <label class="campo"><span class="campo__etiqueta">Cierra</span>
                            <input class="campo__control" type="text" :name="`schedule[${i}][cierra]`" x-model="fila.cierra" placeholder="23:00"></label>
                    </div>
                    <button class="btn btn--desnudo" type="button" @click="filas.splice(i, 1)" aria-label="Quitar franja">
                        <x-icono nombre="papelera" />
                    </button>
                </div>
            </template>
            <button class="btn btn--vidrio" type="button" @click="filas.push({ dia: '', abre: '', cierra: '' })">
                <x-icono nombre="agregar" /> Añadir franja
            </button>
        </div>

        {{-- Ubicación principal: mapa interactivo --}}
        <h3 class="contacto-admin__titulo">Ubicación principal</h3>

        <div x-data="mapaPick({
                latitud: {{ old('latitude', $gym->latitude) ?: 'null' }},
                longitud: {{ old('longitude', $gym->longitude) ?: 'null' }}
             })" x-init="$nextTick(() => iniciar())">
            <input type="hidden" name="latitude" :value="lat">
            <input type="hidden" name="longitude" :value="lng">

            <div class="contacto-mapabuscador">
                <input type="text" x-model="busqueda" @keydown.enter.prevent="buscar"
                       class="campo__control" placeholder="Buscar dirección..." style="flex:1">
                <button class="btn btn--vidrio" type="button" @click="buscar">Buscar</button>
            </div>

            <div x-ref="contenedor" class="contacto-mapa-contenedor" style="height:280px;border-radius:var(--r-md);border:1px solid var(--acero);z-index:0"></div>

            <p class="contacto-admin__nota" style="margin-top:var(--e-2)">
                Lat: <span x-text="lat || '—'"></span> · Lng: <span x-text="lng || '—'"></span>.
                Haz clic en el mapa o arrastra el marcador para ubicar.
            </p>
            @error('latitude')<span class="campo__error">{{ $message }}</span>@enderror
            @error('longitude')<span class="campo__error">{{ $message }}</span>@enderror
        </div>

        {{-- Ubicaciones adicionales (sedes) --}}
        <h3 class="contacto-admin__titulo">Ubicaciones adicionales</h3>
        <p style="font-size:var(--t-sm);color:var(--humo);margin-top:calc(-1 * var(--e-2));margin-bottom:var(--e-3)">
            Añade las demás sedes del gimnasio. Aparecerán en un carrusel en la página pública.
        </p>

        <div x-data="{
                ubicaciones: {{ \Illuminate\Support\Js::from(old('locations', $gym->settings['locations'] ?? [])) }},
                mostrandoMapa: false,
                indiceEditando: null,
                busquedaMapa: '',
                mapaModal: null,
                markerModal: null,
                latModal: null,
                lngModal: null,

                agregar() {
                    this.ubicaciones.push({ name: '', address: '', city: '', phone: '', latitude: null, longitude: null });
                },
                abrirMapa(i) {
                    this.indiceEditando = i;
                    this.mostrandoMapa = true;
                    const u = this.ubicaciones[i];
                    this.latModal = u.latitude || -5.194490;
                    this.lngModal = u.longitude || -80.632820;
                    this.$nextTick(() => {
                        if (this.mapaModal) { this.mapaModal.remove(); this.mapaModal = null; }
                        this.mapaModal = L.map(this.$refs.mapaModal).setView([this.latModal, this.lngModal], 14);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; OpenStreetMap'
                        }).addTo(this.mapaModal);
                        this.markerModal = L.marker([this.latModal, this.lngModal], { draggable: true }).addTo(this.mapaModal);
                        this.mapaModal.on('click', (e) => {
                            this.latModal = e.latlng.lat;
                            this.lngModal = e.latlng.lng;
                            this.markerModal.setLatLng(e.latlng);
                        });
                        this.markerModal.on('dragend', (e) => {
                            this.latModal = e.target.getLatLng().lat;
                            this.lngModal = e.target.getLatLng().lng;
                        });
                        setTimeout(() => this.mapaModal.invalidateSize(), 150);
                    });
                },
                async buscarDireccion() {
                    if (!this.busquedaMapa) return;
                    try {
                        const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(this.busquedaMapa));
                        const data = await res.json();
                        if (data.length) {
                            this.latModal = parseFloat(data[0].lat);
                            this.lngModal = parseFloat(data[0].lon);
                            this.mapaModal.setView([this.latModal, this.lngModal], 15);
                            this.markerModal.setLatLng([this.latModal, this.lngModal]);
                        }
                    } catch (_) {}
                },
                confirmarMapa() {
                    if (this.indiceEditando !== null) {
                        this.ubicaciones[this.indiceEditando].latitude = Math.round(this.latModal * 1e7) / 1e7;
                        this.ubicaciones[this.indiceEditando].longitude = Math.round(this.lngModal * 1e7) / 1e7;
                    }
                    this.cerrarMapa();
                },
                cerrarMapa() {
                    this.mostrandoMapa = false;
                    this.indiceEditando = null;
                    if (this.mapaModal) { this.mapaModal.remove(); this.mapaModal = null; }
                }
             }">
            <template x-for="(u, i) in ubicaciones" :key="i">
                <div class="fila-borrable" style="border:1px solid var(--acero);border-radius:var(--r-md);padding:var(--e-4);margin-bottom:var(--e-3)">
                    <input type="hidden" :name="`locations[${i}][name]`" :value="u.name">
                    <input type="hidden" :name="`locations[${i}][address]`" :value="u.address">
                    <input type="hidden" :name="`locations[${i}][city]`" :value="u.city">
                    <input type="hidden" :name="`locations[${i}][phone]`" :value="u.phone">
                    <input type="hidden" :name="`locations[${i}][latitude]`" :value="u.latitude">
                    <input type="hidden" :name="`locations[${i}][longitude]`" :value="u.longitude">

                    <div class="formulario-panel__fila">
                        <label class="campo"><span class="campo__etiqueta">Nombre</span>
                            <input class="campo__control" type="text" x-model="u.name" placeholder="Sede Norte"></label>
                        <label class="campo"><span class="campo__etiqueta">Dirección</span>
                            <input class="campo__control" type="text" x-model="u.address" placeholder="Av. Los Olivos 123"></label>
                    </div>
                    <div class="formulario-panel__fila">
                        <label class="campo"><span class="campo__etiqueta">Ciudad</span>
                            <input class="campo__control" type="text" x-model="u.city" placeholder="Piura"></label>
                        <label class="campo"><span class="campo__etiqueta">Teléfono</span>
                            <input class="campo__control" type="text" x-model="u.phone" placeholder="+51 900 000 000"></label>
                    </div>

                    <div class="contacto-ubicacion-acciones">
                        <button class="btn btn--vidrio" type="button" @click="abrirMapa(i)">
                            <x-icono nombre="ubicacion" /> Seleccionar en mapa
                        </button>
                        <span style="font-size:var(--t-xs);color:var(--humo)" x-show="u.latitude">
                            📍 <span x-text="u.latitude"></span>, <span x-text="u.longitude"></span>
                        </span>
                    </div>

                    <button class="btn btn--desnudo" type="button" @click="ubicaciones.splice(i, 1)" aria-label="Quitar ubicación"
                            style="position:absolute;top:var(--e-2);right:var(--e-2)">
                        <x-icono nombre="papelera" />
                    </button>
                </div>
            </template>

            <button class="btn btn--vidrio" type="button" @click="agregar()">
                <x-icono nombre="agregar" /> Añadir ubicación
            </button>

            {{-- Modal del mapa --}}
            <div x-show="mostrandoMapa" x-cloak
                 class="modal-info contacto-mapa-modal" @keydown.escape.window="cerrarMapa()" style="z-index:var(--z-modal)">
                <div class="modal-info__fondo" @click="cerrarMapa()"></div>
                <div class="modal-info__caja" style="max-width:680px" @click.outside="cerrarMapa()">
                    <button class="modal-info__cerrar" @click="cerrarMapa()" type="button">&times;</button>
                    <div class="modal-info__cuerpo">
                        <h3>Seleccionar ubicación</h3>
                        <div class="contacto-mapabuscador">
                            <input type="text" x-model="busquedaMapa" @keydown.enter.prevent="buscarDireccion"
                                   class="campo__control" placeholder="Buscar dirección..." style="flex:1">
                            <button class="btn btn--vidrio" type="button" @click="buscarDireccion">Buscar</button>
                        </div>
                        <div x-ref="mapaModal" style="height:340px;border-radius:var(--r-md);border:1px solid var(--acero)"></div>
                        <button class="btn btn--fuego" @click="confirmarMapa()">Confirmar ubicación</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="formulario-panel__acciones">
            <a class="btn btn--vidrio" href="{{ route('admin.contenido.contacto') }}">Cancelar</a>
            <button class="btn btn--fuego" type="submit">Guardar</button>
        </div>
    </form>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9/dist/leaflet.js"></script>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('mapaPick', ({ latitud, longitud }) => ({
        lat: latitud,
        lng: longitud,
        busqueda: '',
        mapa: null,
        marker: null,

        iniciar() {
            if (!this.$refs.contenedor) return;
            const centro = (this.lat && this.lng) ? [this.lat, this.lng] : [-5.194490, -80.632820];
            const zoom = (this.lat && this.lng) ? 14 : 12;

            this.mapa = L.map(this.$refs.contenedor).setView(centro, zoom);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(this.mapa);

            if (this.lat && this.lng) {
                this.marker = L.marker(centro, { draggable: true }).addTo(this.mapa);
            }

            this.mapa.on('click', (e) => {
                this.lat = Math.round(e.latlng.lat * 1e7) / 1e7;
                this.lng = Math.round(e.latlng.lng * 1e7) / 1e7;
                if (this.marker) {
                    this.marker.setLatLng(e.latlng);
                } else {
                    this.marker = L.marker(e.latlng, { draggable: true }).addTo(this.mapa);
                }
            });

            if (this.marker) {
                this.marker.on('dragend', (e) => {
                    this.lat = Math.round(e.target.getLatLng().lat * 1e7) / 1e7;
                    this.lng = Math.round(e.target.getLatLng().lng * 1e7) / 1e7;
                });
            }

            setTimeout(() => this.mapa.invalidateSize(), 200);
        },

        async buscar() {
            if (!this.busqueda) return;
            try {
                const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(this.busqueda));
                const data = await res.json();
                if (data.length) {
                    this.lat = parseFloat(data[0].lat);
                    this.lng = parseFloat(data[0].lon);
                    this.mapa.setView([this.lat, this.lng], 15);
                    if (this.marker) {
                        this.marker.setLatLng([this.lat, this.lng]);
                    } else {
                        this.marker = L.marker([this.lat, this.lng], { draggable: true }).addTo(this.mapa);
                        this.marker.on('dragend', (e) => {
                            this.lat = Math.round(e.target.getLatLng().lat * 1e7) / 1e7;
                            this.lng = Math.round(e.target.getLatLng().lng * 1e7) / 1e7;
                        });
                    }
                }
            } catch (_) {}
        }
    }));
});
</script>
@endpush
