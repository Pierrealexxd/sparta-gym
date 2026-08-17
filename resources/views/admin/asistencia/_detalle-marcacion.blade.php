{{-- Modal de detalle de una marcación laboral individual. Se abre desde la
     vista Lista (evento "abrir-detalle" con la URL de admin.asistencia.detalle)
     y carga los datos vía fetch — no navega a otra página. Incluye la
     ubicación GPS capturada en el escaneo QR (ver AsistenciaService, sin
     cambios): coordenadas, enlace a Google Maps y mapa embebido con
     Leaflet (CDN, sin dependencia npm — ver nota al pie del archivo). --}}
<div class="modal__fondo" x-data="detalleMarcacion()" x-show="abierto" x-cloak
     @keydown.escape.window="cerrar()" @abrir-detalle.window="abrir($event.detail.url)">
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
                {{-- Datos principales --}}
                <dl class="calendario__detalle-lista">
                    <div>
                        <dt>Entrenador</dt>
                        <dd x-text="datos.entrenador"></dd>
                    </div>
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

                {{-- Ubicación GPS --}}
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

                        {{-- Mapa Leaflet: id fijo porque solo hay un modal de
                             detalle por página. height literal porque no hay
                             token de tamaño para un widget embebido de
                             terceros (igual que .calendario__celda min-height
                             o .modal__caja width en panel.css). --}}
                        <div id="mapa-detalle" style="height:200px;border-radius:var(--r-md);border:1px solid var(--acero)"></div>
                    </div>
                </template>

                {{-- Sin ubicación --}}
                <template x-if="!datos.tiene_ubicacion">
                    <p class="calendario__detalle" style="color:var(--humo);font-size:var(--t-sm)">
                        Esta marcación no tiene ubicación GPS registrada.
                    </p>
                </template>
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
        mapa: null,
        marker: null,

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
        },
    }));
});
</script>
