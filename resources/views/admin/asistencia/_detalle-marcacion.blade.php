{{-- Modal de detalle de una marcación laboral individual. Se abre desde la
     vista Lista y desde el Calendario (evento "abrir-detalle" con la URL
     de admin.asistencia.detalle) y carga los datos vía fetch — no navega
     a otra página. Incluye dos pestañas: Resumen (datos de la marcación)
     y Ubicación GPS (coordenadas, enlace y mapa con Leaflet). --}}
<div class="modal__fondo" x-data="detalleMarcacion()" x-show="abierto" x-cloak
     @keydown.escape.window="cerrar()" @abrir-detalle.window="abrir($event.detail.url)">
    <div class="tarjeta modal__caja" style="max-width:36rem" @click.outside="cerrar()">
        <div class="modal__cabecera">
            <h3 style="font-size:var(--t-lg)">Detalle de marcación</h3>
            <button class="modal__cerrar" type="button" @click="cerrar()"><x-icono nombre="cerrar" /></button>
        </div>

        <template x-if="cargando">
            <p style="color:var(--ceniza);padding:var(--e-4) 0">Cargando…</p>
        </template>

        <template x-if="!cargando && !datos">
            <p style="color:var(--alerta);padding:var(--e-4) 0">No se pudo cargar el detalle de la marcación.</p>
        </template>

        <template x-if="!cargando && datos">
            <div style="display:grid;gap:0">
                {{-- Pestañas --}}
                <nav class="pestanas__nav pestanas__nav--ancha" style="margin-bottom:var(--e-4)">
                    <button type="button" class="pestanas__enlace"
                            :aria-current="tab === 'resumen' ? 'true' : 'false'"
                            @click="tab = 'resumen'">Resumen</button>
                    <button type="button" class="pestanas__enlace"
                            :aria-current="tab === 'gps' ? 'true' : 'false'"
                            @click="tab = 'gps'">Ubicación GPS</button>
                </nav>

                {{-- Pestaña: Resumen --}}
                <div x-show="tab === 'resumen'" x-cloak style="display:grid;gap:var(--e-4)">
                    {{-- Cabecera con nombre y sede --}}
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--e-2)">
                        <div>
                            <p style="font-size:var(--t-lg);font-weight:700;color:var(--hueso)" x-text="datos.entrenador"></p>
                            <p style="font-size:var(--t-sm);color:var(--ceniza);font-family:var(--f-mono)" x-show="datos.dni"
                               x-text="'DNI ' + datos.dni"></p>
                        </div>
                        <span class="etiqueta" x-text="datos.sede"></span>
                    </div>

                    {{-- Datos de la marcación --}}
                    <dl class="calendario__detalle-lista">
                        <div>
                            <dt>Entrada</dt>
                            <dd x-text="datos.entrada" style="color:var(--hueso)"></dd>
                        </div>
                        <div>
                            <dt>Salida</dt>
                            <dd>
                                <span x-text="datos.salida || 'En curso'" :style="datos.salida ? 'color:var(--hueso)' : 'color:var(--brasa)'"></span>
                            </dd>
                        </div>
                        <div>
                            <dt>Duración</dt>
                            <dd x-text="datos.duracion || '—'" style="color:var(--hueso)"></dd>
                        </div>
                        <div>
                            <dt>Turno</dt>
                            <dd x-text="datos.turno" style="color:var(--hueso)"></dd>
                        </div>
                        <div>
                            <dt>Método</dt>
                            <dd><span class="estado" x-text="datos.metodo"></span></dd>
                        </div>
                    </dl>

                    {{-- Indicador rápido de ubicación --}}
                    <div style="display:flex;align-items:center;gap:var(--e-2);font-size:var(--t-sm)">
                        <template x-if="datos.tiene_ubicacion">
                            <span style="color:var(--ok)">📍 Ubicación GPS registrada</span>
                        </template>
                        <template x-if="!datos.tiene_ubicacion">
                            <span style="color:var(--humo)">Sin ubicación GPS</span>
                        </template>
                        <template x-if="datos.tiene_ubicacion">
                            <button type="button" class="btn btn--desnudo"
                                    style="font-size:var(--t-xs);color:var(--brasa);text-decoration:underline;margin-left:auto"
                                    @click="tab = 'gps'">Ver en mapa →</button>
                        </template>
                    </div>
                </div>

                {{-- Pestaña: Ubicación GPS --}}
                <div x-show="tab === 'gps'" x-cloak style="display:grid;gap:var(--e-4)">
                    <template x-if="datos.tiene_ubicacion">
                        <div style="display:grid;gap:var(--e-4)">
                            {{-- Coordenadas --}}
                            <dl class="calendario__detalle-lista">
                                <div>
                                    <dt>Latitud</dt>
                                    <dd x-text="datos.lat.toFixed(8)" style="font-family:var(--f-mono);font-size:var(--t-sm)"></dd>
                                </div>
                                <div>
                                    <dt>Longitud</dt>
                                    <dd x-text="datos.lng.toFixed(8)" style="font-family:var(--f-mono);font-size:var(--t-sm)"></dd>
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

                            {{-- Mapa Leaflet --}}
                            <div id="mapa-detalle" style="height:220px;border-radius:var(--r-md);border:1px solid var(--acero)"></div>
                        </div>
                    </template>

                    <template x-if="!datos.tiene_ubicacion">
                        <div style="text-align:center;padding:var(--e-6) 0">
                            <p style="color:var(--humo);font-size:var(--t-sm)">
                                Esta marcación no tiene ubicación GPS registrada.
                            </p>
                            <p style="color:var(--ceniza);font-size:var(--t-xs);margin-top:var(--e-2)">
                                La ubicación se captura automáticamente al marcar por QR.
                            </p>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</div>

{{-- Leaflet CSS + JS (CDN, sin npm — ver PLAN_AUDITORIA_ASISTENCIAS.md §2.3.5).
     Solo esta vista lo necesita, así que se carga acá y no en el layout. --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9/dist/leaflet.js"></script>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('detalleMarcacion', () => ({
        abierto: false,
        cargando: false,
        datos: null,
        tab: 'resumen',
        mapa: null,
        marker: null,

        async abrir(url) {
            this.abierto = true;
            this.cargando = true;
            this.datos = null;
            this.tab = 'resumen';

            try {
                const res = await fetch(url);
                this.datos = res.ok ? await res.json() : null;
            } catch {
                this.datos = null;
            }

            this.cargando = false;

            // Renderizar mapa después de que Alpine inserte el DOM.
            this.$nextTick(() => {
                if (this.datos?.tiene_ubicacion) {
                    this.renderMapa();
                }
            });
        },

        renderMapa() {
            const contenedor = document.getElementById('mapa-detalle');
            if (!contenedor || !window.L) return;

            // Sin internet, Leaflet no llega a cargar (window.L no existe):
            // las coordenadas y el enlace a Google Maps siguen visibles,
            // solo se omite el mapa embebido.
            if (this.mapa) {
                this.mapa.remove();
                this.mapa = null;
            }

            this.mapa = L.map('mapa-detalle').setView([this.datos.lat, this.datos.lng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
                maxZoom: 19,
            }).addTo(this.mapa);
            this.marker = L.marker([this.datos.lat, this.datos.lng]).addTo(this.mapa)
                .bindPopup(this.datos.entrenador).openPopup();

            // El contenedor estaba oculto (x-cloak/x-show) cuando Leaflet
            // midió su tamaño la primera vez — invalidateSize() lo recalcula
            // ya visible, si no el mapa queda cortado a una esquina.
            setTimeout(() => this.mapa.invalidateSize(), 100);
        },

        cerrar() {
            if (this.mapa) {
                this.mapa.remove();
                this.mapa = null;
            }
            this.abierto = false;
            this.datos = null;
            this.tab = 'resumen';
        },
    }));
});
</script>
