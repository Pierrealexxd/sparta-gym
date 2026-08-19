{{-- Modal de detalle de una marcación laboral propia. Se abre desde la
     vista "Mi marcación" (evento "abrir-detalle-mi" con la URL de
     entrenador.asistencia.detalle) y carga los datos vía fetch — no
     navega a otra página. Incluye la ubicación GPS capturada en el
     escaneo QR: coordenadas, enlace a Google Maps y mapa embebido con
     Leaflet (CDN, sin dependencia npm). --}}
<div class="modal__fondo" x-data="detalleMiMarcacion()" x-show="abierto" x-cloak
     @keydown.escape.window="cerrar()" @abrir-detalle-mi.window="abrir($event.detail.url)">
    <div class="tarjeta modal__caja" style="max-width:32rem" @click.outside="cerrar()">
        <div class="modal__cabecera">
            <h3 style="font-size:var(--t-lg)">Detalle de marcación</h3>
            <button class="modal__cerrar" type="button" @click="cerrar()"><x-icono nombre="cerrar" /></button>
        </div>

        <template x-if="cargando">
            <p style="color:var(--ceniza)">Cargando…</p>
        </template>

        <template x-if="!cargando && !datos">
            <p style="color:var(--alerta)">No se pudo cargar el detalle de la marcación.</p>
        </template>

        <template x-if="!cargando && datos">
            <div style="display:grid;gap:var(--e-4)">
                <dl class="calendario__detalle-lista">
                    <div>
                        <dt>Sede</dt>
                        <dd x-text="datos.sede"></dd>
                    </div>
                    <div>
                        <dt>Entrada</dt>
                        <dd x-text="datos.entrada"></dd>
                    </div>
                    <div>
                        <dt>Salida</dt>
                        <dd x-text="datos.salida || 'En curso'"></dd>
                    </div>
                    <div>
                        <dt>Duración</dt>
                        <dd x-text="datos.duracion || '—'"></dd>
                    </div>
                    <div>
                        <dt>Turno</dt>
                        <dd x-text="datos.turno"></dd>
                    </div>
                    <div>
                        <dt>Método</dt>
                        <dd x-text="datos.metodo"></dd>
                    </div>
                </dl>

                <template x-if="datos.tiene_ubicacion">
                    <div class="calendario__detalle">
                        <p style="font-size:var(--t-sm);color:var(--ceniza);margin-bottom:var(--e-3)">
                            Ubicación GPS de la marcación
                        </p>
                        <dl class="calendario__detalle-lista" style="margin-bottom:var(--e-3)">
                            <div>
                                <dt>Latitud</dt>
                                <dd x-text="datos.lat.toFixed(8)"></dd>
                            </div>
                            <div>
                                <dt>Longitud</dt>
                                <dd x-text="datos.lng.toFixed(8)"></dd>
                            </div>
                            <div>
                                <dt>Enlace</dt>
                                <dd>
                                    <a :href="'https://www.google.com/maps?q=' + datos.lat + ',' + datos.lng"
                                       target="_blank" rel="noopener"
                                       style="color:var(--brasa);text-decoration:underline">
                                        Abrir en Google Maps
                                    </a>
                                </dd>
                            </div>
                        </dl>

                        <div id="mapa-detalle-mi" style="height:200px;border-radius:var(--r-md);border:1px solid var(--acero)"></div>
                    </div>
                </template>

                <template x-if="!datos.tiene_ubicacion">
                    <p class="calendario__detalle" style="color:var(--humo);font-size:var(--t-sm)">
                        Esta marcación no tiene ubicación GPS registrada.
                    </p>
                </template>
            </div>
        </template>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9/dist/leaflet.js"></script>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('detalleMiMarcacion', () => ({
        abierto: false,
        cargando: false,
        datos: null,
        mapa: null,

        async abrir(url) {
            this.abierto = true;
            this.cargando = true;
            this.datos = null;

            try {
                const res = await fetch(url);
                this.datos = res.ok ? await res.json() : null;
            } catch {
                this.datos = null;
            }

            this.cargando = false;

            this.$nextTick(() => {
                if (this.datos?.tiene_ubicacion) {
                    this.renderMapa();
                }
            });
        },

        renderMapa() {
            const contenedor = document.getElementById('mapa-detalle-mi');
            if (!contenedor || !window.L) return;

            if (this.mapa) {
                this.mapa.remove();
                this.mapa = null;
            }

            this.mapa = L.map('mapa-detalle-mi').setView([this.datos.lat, this.datos.lng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
                maxZoom: 19,
            }).addTo(this.mapa);
            L.marker([this.datos.lat, this.datos.lng]).addTo(this.mapa)
                .bindPopup('Tu marcación').openPopup();

            setTimeout(() => this.mapa.invalidateSize(), 100);
        },

        cerrar() {
            if (this.mapa) {
                this.mapa.remove();
                this.mapa = null;
            }
            this.abierto = false;
            this.datos = null;
        },
    }));
});
</script>
