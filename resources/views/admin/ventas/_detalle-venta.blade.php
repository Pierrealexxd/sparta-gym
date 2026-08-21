{{-- Modal de detalle de una venta individual. Se abre desde la tabla de
     Productos y Registros (evento "abrir-detalle-venta" con la URL
     de admin.ventas.detalle) y carga los datos vía fetch.
     Colores via tokens: funciona en tema oscuro y claro. --}}
<div class="modal__fondo" x-data="detalleVenta()" x-show="abierto" x-cloak
     @keydown.escape.window="cerrar()" @abrir-detalle-venta.window="abrir($event.detail.url)">
    <div class="tarjeta modal__caja" style="max-width:36rem" @click.outside="cerrar()">
        <div class="modal__cabecera">
            <h3 style="font-size:var(--t-lg)">Detalle de venta</h3>
            <button class="modal__cerrar" type="button" @click="cerrar()"><x-icono nombre="cerrar" /></button>
        </div>

        <template x-if="cargando">
            <p style="color:var(--ceniza);padding:var(--e-4) 0">Cargando…</p>
        </template>

        <template x-if="!cargando && !datos">
            <p style="color:var(--alerta);padding:var(--e-4) 0">No se pudo cargar el detalle de la venta.</p>
        </template>

        <template x-if="!cargando && datos">
            <div style="display:grid;gap:var(--e-4)">
                {{-- Cabecera: número y estado --}}
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:var(--e-2)">
                    <div>
                        <p style="font-size:var(--t-lg);font-weight:700;color:var(--hueso);font-family:var(--f-mono)" x-text="datos.number"></p>
                        <p style="font-size:var(--t-sm);color:var(--ceniza)" x-text="datos.fecha"></p>
                    </div>
                    <span class="estado" :class="'estado--' + datos.estado" x-text="datos.estado === 'completada' ? 'Completada' : 'Anulada'"></span>
                </div>

                {{-- Cliente --}}
                <div x-show="datos.cliente" class="detalle-venta__bloque">
                    <span class="detalle-venta__etiqueta">Cliente</span>
                    <p style="color:var(--hueso);font-weight:600;margin-top:var(--e-1)" x-text="datos.cliente?.full_name"></p>
                    <p style="font-size:var(--t-sm);color:var(--ceniza);font-family:var(--f-mono)" x-text="'Código ' + datos.cliente?.code"></p>
                </div>

                {{-- Items (productos) --}}
                <div x-show="datos.items.length > 0">
                    <span class="detalle-venta__etiqueta" style="display:block;margin-bottom:var(--e-2)">Productos</span>
                    <div style="display:grid;gap:var(--e-2)">
                        <template x-for="(item, i) in datos.items" :key="i">
                            <div class="detalle-venta__item">
                                <div>
                                    <span style="color:var(--hueso)" x-text="item.producto"></span>
                                    <span style="color:var(--ceniza);font-size:var(--t-sm)" x-text="' × ' + item.cantidad"></span>
                                </div>
                                <span style="font-family:var(--f-mono);color:var(--bronce)" x-text="'S/ ' + item.total.toFixed(2)"></span>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Membresía (si aplica) --}}
                <div x-show="datos.membresia" class="detalle-venta__bloque">
                    <span class="detalle-venta__etiqueta">Membresía</span>
                    <p style="color:var(--hueso);font-weight:600;margin-top:var(--e-1)" x-text="datos.membresia?.plan"></p>
                    <p style="font-size:var(--t-sm);color:var(--ceniza)" x-text="datos.membresia?.inicio + ' → ' + datos.membresia?.fin"></p>
                    <p style="font-size:var(--t-sm);color:var(--ceniza)" x-show="datos.membresia?.dias !== null"
                       x-text="datos.membresia?.dias + ' días restantes'"></p>
                </div>

                {{-- Concepto (registros) --}}
                <div x-show="datos.concepto" class="detalle-venta__bloque">
                    <span class="detalle-venta__etiqueta">Concepto</span>
                    <p style="color:var(--hueso);margin-top:var(--e-1)" x-text="datos.concepto"></p>
                </div>

                {{-- Resumen financiero --}}
                <div class="detalle-venta__bloque" style="display:grid;gap:var(--e-1)">
                    <div style="display:flex;justify-content:space-between;font-size:var(--t-sm)">
                        <span style="color:var(--ceniza)">Subtotal</span>
                        <span style="color:var(--hueso);font-family:var(--f-mono)" x-text="'S/ ' + datos.subtotal.toFixed(2)"></span>
                    </div>
                    <div x-show="datos.descuento > 0" style="display:flex;justify-content:space-between;font-size:var(--t-sm)">
                        <span style="color:var(--ceniza)">Descuento</span>
                        <span style="color:var(--sangre);font-family:var(--f-mono)" x-text="'- S/ ' + datos.descuento.toFixed(2)"></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:var(--t-lg);font-weight:700;padding-top:var(--e-2);border-top:1px solid var(--acero)">
                        <span style="color:var(--hueso)">Total</span>
                        <span style="color:var(--bronce);font-family:var(--f-mono)" x-text="'S/ ' + datos.total.toFixed(2)"></span>
                    </div>
                </div>

                {{-- Método de pago y vendedor --}}
                <div style="display:flex;gap:var(--e-4);font-size:var(--t-sm)">
                    <div>
                        <span style="color:var(--humo)">Método</span>
                        <p style="color:var(--hueso)" x-text="datos.metodo"></p>
                    </div>
                    <div>
                        <span style="color:var(--humo)">Vendido por</span>
                        <p style="color:var(--hueso)" x-text="datos.vendido_por"></p>
                    </div>
                </div>

                {{-- Notas --}}
                <div x-show="datos.notas" class="detalle-venta__bloque">
                    <span class="detalle-venta__etiqueta">Notas</span>
                    <p style="color:var(--hueso);margin-top:var(--e-1);font-size:var(--t-sm)" x-text="datos.notas"></p>
                </div>
            </div>
        </template>
    </div>
</div>

@push('scripts')
<script>
function detalleVenta() {
    return {
        abierto: false,
        cargando: false,
        datos: null,

        async abrir(url) {
            this.abierto = true;
            this.cargando = true;
            this.datos = null;
            try {
                const res = await fetch(url);
                this.datos = await res.json();
            } catch (e) {
                this.datos = null;
            } finally {
                this.cargando = false;
            }
        },

        cerrar() {
            this.abierto = false;
            this.datos = null;
        },
    };
}
</script>
@endpush
