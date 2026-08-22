@props(['productos'])

{{-- Editor de venta, compartido por /admin/ventas (ambas pestañas) y
     la pestaña Pagos de la ficha del cliente. Abre con el evento
     `abrir-editar-venta` (detail.url = endpoint de detalle JSON), trae
     las líneas y edita con steppers; el stock se ajusta por delta en
     el backend (SaleController::update).

     La regla de caja manda: solo ventas DEL DÍA admiten cambios (el
     histórico ya reportado no se reescribe — ver AGENTS.md). Una venta
     de otro día abre igual y carga sus datos, pero en modo consulta
     con el aviso de por qué no guarda.

     La lógica vive en la función editorVenta() del script de abajo, NO
     inline en x-data: un atributo HTML no escapa comillas con \" y el
     primer " suelto parte el componente en pleno render. Mismo patrón
     que matriculaVentas() en admin/ventas/index.blade.php. --}}

@php
    // Fuera de @json: Blade no parsea bien los closures con corchetes
    // dentro de una directiva.
    $catalogoJson = $productos->map(fn ($p) => [
        'id' => $p->id, 'name' => $p->name,
        'price' => (float) $p->sale_price, 'stock' => (int) $p->stock,
    ]);
@endphp

@push('scripts')
<script>
    function editorVenta(catalogo) {
        return {
            abierto: false,
            cargando: false,
            enviando: false,
            error: '',
            venta: null,
            filas: [],
            nuevoId: '',
            discount: '0',
            method: 'efectivo',
            editable: true,
            catalogo,

            abrir(e) {
                this.abierto = true;
                this.cargando = true;
                this.error = '';
                this.venta = null;
                this.filas = [];
                this.nuevoId = '';
                this.editable = true;

                fetch(e.detail.url, { headers: { Accept: 'application/json' } })
                    .then((r) => r.json())
                    .then((d) => {
                        this.venta = d;
                        this.method = d.method;
                        this.discount = String(d.descuento);
                        // El backend decide si la venta admite cambios:
                        // fuera del día de cobro el modal abre en consulta.
                        this.editable = d.editable !== false;
                        this.filas = d.items.map((i) => ({
                            product_id: i.product_id,
                            name: i.producto,
                            price: Number(i.unit_price),
                            quantity: i.cantidad,
                        }));
                    })
                    .catch(() => { this.error = 'No se pudo cargar la venta.'; })
                    .finally(() => { this.cargando = false; });
            },

            cerrar() { if (!this.enviando) this.abierto = false; },

            disponibles() {
                return this.catalogo.filter((p) =>
                    !this.filas.some((f) => String(f.product_id) === String(p.id)));
            },

            agregar() {
                if (!this.nuevoId) return;
                const p = this.catalogo.find((x) => String(x.id) === String(this.nuevoId));
                this.nuevoId = '';
                if (!p) return;
                if (p.stock < 1) { this.error = '"' + p.name + '" está sin stock.'; return; }
                this.filas.push({ product_id: p.id, name: p.name, price: p.price, quantity: 1 });
            },

            etiquetaProducto(p) {
                return `${p.name} · S/ ${Number(p.price).toFixed(2)} (stock ${p.stock})`;
            },

            get bruto() {
                return this.filas.reduce((s, f) => s + f.price * f.quantity, 0);
            },

            // Porción del total que no son productos: en una matrícula
            // mixta es el precio congelado del plan; en mostrador puro, 0.
            get porcionFija() {
                if (!this.venta) return 0;
                const lineas = this.venta.items.reduce((s, i) => s + i.unit_price * i.cantidad, 0);
                return Math.max(Number(this.venta.subtotal) - lineas, 0);
            },

            totalNuevo() {
                return Math.max(this.porcionFija + this.bruto - (Number(this.discount) || 0), 0).toFixed(2);
            },

            async guardar() {
                if (!this.editable) return;
                this.enviando = true;
                this.error = '';

                const fd = new FormData();
                fd.append('_method', 'PUT');
                fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
                fd.append('method', this.method);
                fd.append('discount', String(Number(this.discount) || 0));
                // Todas las líneas con su cantidad final; 0 = quitar del ticket.
                this.filas.forEach((f, i) => {
                    fd.append(`items[${i}][product_id]`, f.product_id);
                    fd.append(`items[${i}][quantity]`, f.quantity);
                });

                try {
                    const r = await fetch('{{ url("admin/ventas") }}/' + this.venta.id, {
                        method: 'POST',
                        headers: { Accept: 'application/json' },
                        body: fd,
                    });
                    const d = await r.json().catch(() => ({}));
                    if (!r.ok) {
                        this.error = d.errors?.items?.[0] ?? d.message ?? 'No se pudo actualizar.';
                        this.enviando = false;
                        return;
                    }
                    window.location.reload();
                } catch {
                    this.error = 'Sin conexión con el servidor.';
                    this.enviando = false;
                }
            },
        };
    }
</script>
@endpush

<div class="modal__fondo"
     x-data="editorVenta({{ Js::from($catalogoJson) }})"
     x-show="abierto" x-cloak
     @abrir-editar-venta.window="abrir($event)"
     @keydown.escape.window="cerrar()">
    <div class="tarjeta modal__caja wizard" @click.outside="cerrar()">
        <div class="modal__cabecera">
            <h3>Editar venta</h3>
            <button class="modal__cerrar" type="button" @click="cerrar()" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
        </div>

        <p x-show="cargando">Cargando venta…</p>

        {{-- El contenido completo va tras x-cloak para que no haya
             parpadeo entre el parseo y la inicialización de Alpine --}}
        <div x-show="!cargando && venta" x-cloak>
            <div class="aviso">
                Venta <b x-text="venta?.number"></b> · <span x-text="venta?.cliente?.full_name ?? 'sin cliente'"></span>
                <span x-show="porcionFija > 0" style="font-family:var(--f-mono)" x-text="' · plan fijo S/ ' + porcionFija.toFixed(2)"></span>
            </div>

            {{-- Regla de caja: el histórico de otros días no se reescribe.
                 El modal abre igual (los datos se consultan), pero sin guardar. --}}
            <div x-show="venta && !editable" x-cloak class="aviso aviso--info" role="alert">
                Esta venta es de otro día: solo se editan las ventas de hoy para que la caja siga cuadrando por fecha real de cobro. Para el pasado, anúlala y vuelve a registrarla.
            </div>

            <div x-show="error" x-cloak class="aviso aviso--error" role="alert" x-text="error"></div>

            {{-- Todo lo editable cuelga de este wrapper: en una venta de
                 otro día queda INERT (sin clic ni foco) y el modal se lee
                 como ficha de consulta. --}}
            <div :inert="venta && !editable">

            <div class="tabla-envoltorio" style="max-height:14rem;overflow-y:auto">
                <table class="tabla tabla--tarjetas">
                    <thead><tr><th>Producto</th><th style="text-align:right">Precio</th><th style="text-align:center">Cant.</th><th style="text-align:right">Importe</th></tr></thead>
                    <tbody>
                        <template x-for="(f, i) in filas" :key="i">
                            <tr>
                                <td class="es-fuerte" data-etiqueta="Producto">
                                    <span :style="f.quantity === 0 ? 'text-decoration:line-through;color:var(--humo)' : ''" x-text="f.name"></span>
                                    <span x-show="f.quantity === 0" style="color:var(--brasa);font-size:var(--t-xs)"> se quita</span>
                                </td>
                                <td data-etiqueta="Precio" style="text-align:right;font-family:var(--f-mono)" x-text="'S/ ' + Number(f.price).toFixed(2)"></td>
                                <td data-etiqueta="Cant." style="text-align:center">
                                    <div class="stepper">
                                        <button class="stepper__btn" type="button" aria-label="Quitar uno"
                                                :disabled="f.quantity <= 0"
                                                @click="f.quantity = Math.max(f.quantity - 1, 0)">−</button>
                                        <span class="stepper__cifra" x-text="f.quantity"></span>
                                        <button class="stepper__btn" type="button" aria-label="Agregar uno"
                                                :disabled="f.quantity >= 99"
                                                @click="f.quantity = Math.min(f.quantity + 1, 99)">+</button>
                                    </div>
                                </td>
                                <td data-etiqueta="Importe" style="text-align:right;font-family:var(--f-mono)"
                                    x-text="'S/ ' + (f.price * f.quantity).toFixed(2)"></td>
                            </tr>
                        </template>
                        <tr x-show="filas.length === 0" x-cloak>
                            <td colspan="4" class="tabla__vacio" data-etiqueta="">Esta venta no lleva productos.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <label class="campo" style="margin-top:var(--e-3)"><span class="campo__etiqueta">Agregar producto</span>
                <select class="campo__control" x-model="nuevoId" @change="agregar()">
                    <option value="">— Elegir del inventario —</option>
                    <template x-for="p in disponibles()" :key="p.id">
                        <option :value="p.id" x-text="etiquetaProducto(p)" :disabled="p.stock < 1"></option>
                    </template>
                </select></label>

            <div class="matriz" style="margin-top:var(--e-4)">
                <div class="matriz__fila matriz__fila--dos">
                    <label class="campo"><span class="campo__etiqueta">Método de pago</span>
                        <select class="campo__control" x-model="method">
                            @foreach (config('sparta.metodos_pago') as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                        </select></label>
                    <label class="campo"><span class="campo__etiqueta">Descuento (S/)</span>
                        <input class="campo__control" type="number" step="0.01" min="0" x-model="discount"></label>
                </div>
                <div class="matriz__total">
                    <span>Total actualizado</span>
                    <b x-text="'S/ ' + totalNuevo()"></b>
                </div>
            </div>

            <div class="formulario-panel__acciones">
                <button class="btn btn--fuego btn--bloque" type="button" @click="guardar()" :disabled="enviando || cargando">
                    <span x-show="!enviando">Guardar cambios</span>
                    <span x-show="enviando" x-cloak>Guardando…</span>
                </button>
            </div>
            </div>{{-- cierra el wrapper :inert --}}
        </div>
    </div>
</div>
