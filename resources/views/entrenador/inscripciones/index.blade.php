@extends('layouts.panel')

{{-- Inscripciones (matricular clientes) — vive en /entrenador/rutinas, el
     nombre que también usa el enlace del menú. La tabla lista las membresías
     que el propio entrenador registró; el botón "Ver" solo aparece para los
     clientes que están a su cargo (la ficha lo exige). --}}
@section('titulo', 'Rutinas')

@section('acciones')
    <button class="btn btn--fuego" type="button" @click="window.dispatchEvent(new CustomEvent('abrir-inscripcion'))">
        <x-icono nombre="agregar" /> Nueva inscripción
    </button>
@endsection

@section('contenido')
    <div class="kpis" data-revelar data-revelar-grupo>
        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="usuarios" /></span>
            <b class="kpi__valor"><span data-contador="{{ $kpis['clientesACargo'] }}">0</span></b>
            <span class="kpi__etiqueta">Clientes a tu cargo</span>
        </article>
        <article class="tarjeta kpi tarjeta--interactiva">
            <span class="kpi__icono"><x-icono nombre="agregar" /></span>
            <b class="kpi__valor"><span data-contador="{{ $kpis['inscripcionesMes'] }}">0</span></b>
            <span class="kpi__etiqueta">Inscripciones este mes</span>
        </article>
    </div>

    {{-- "¿Cómo va mi captación?" — solo lo que este entrenador inscribió. --}}
    <article class="tarjeta grafico" data-revelar>
        <div class="grafico__cabecera">
            <h3 style="font-size:var(--t-lg)">Mis inscripciones · últimos 6 meses</h3>
        </div>
        @if (array_sum($graficoInscripciones['datasets'][0]['data']) > 0)
            <div class="grafico__lienzo">
                <canvas data-grafico="{{ json_encode(['tipo' => 'bar'] + $graficoInscripciones) }}"></canvas>
            </div>
        @else
            <x-estado-vacio icono="usuarios" texto="Todavía no registraste inscripciones." />
        @endif
    </article>

    <div class="tabla-envoltorio" data-revelar>
        <table class="tabla tabla--tarjetas">
            <thead><tr><th>Cliente</th><th>Plan</th><th>Fecha</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse ($inscripciones as $m)
                    <tr>
                        <td class="es-fuerte" data-etiqueta="Cliente">{{ $m->member?->full_name ?? '—' }}</td>
                        <td data-etiqueta="Plan">{{ $m->plan_name }}</td>
                        <td data-etiqueta="Fecha">{{ $m->created_at->format('d M Y') }}</td>
                        <td data-etiqueta="Estado"><span class="estado estado--{{ $m->status === 'activa' ? 'activo' : 'inactivo' }}">{{ ucfirst($m->status) }}</span></td>
                        <td data-etiqueta="nada">
                            @if ($m->member && $aCargo->contains($m->member->id))
                                <a class="btn btn--desnudo" href="{{ route('entrenador.clientes.show', $m->member) }}">Ver</a>
                            @else
                                <span style="color:var(--humo)">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="tabla__vacio" data-etiqueta=""><x-estado-vacio icono="usuarios" texto="Sin inscripciones todavía." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="paginacion">{{ $inscripciones->links() }}</div>

    {{-- Wizard de inscripción (cliente nuevo + plan + pago), como modal de
         esta pantalla — antes era su propia página. Se abre solo: por el
         botón de arriba, o de entrada si la última sumisión trajo errores
         de validación (para no perder lo tecleado). --}}
    <div x-data="{ abierta: {{ $errors->any() && old('plan_id') !== null ? 'true' : 'false' }} }"
         x-on:abrir-inscripcion.window="abierta = true"
         @keydown.escape.window="abierta = false">
        <div class="modal__fondo" x-show="abierta" x-cloak>
            <div class="tarjeta modal__caja wizard"
                 x-data="inscripcion()" x-init="init()" @click.outside="abierta = false">
                <div class="modal__cabecera">
                    <h3>Nueva inscripción</h3>
                    <button class="modal__cerrar" type="button" @click="abierta = false" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
                </div>

                @if ($errors->any())
                    <div class="aviso aviso--error" role="alert" style="margin-bottom:var(--e-5)">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="aviso" style="margin-bottom:var(--e-5)">
                    Si el cliente ya existe, búscalo abajo para no volver a teclear sus datos.
                </div>

                {{-- Cabecera de pasos --}}
                <nav class="wizard__pasos" aria-label="Progreso de inscripción">
                    <button type="button" class="wizard__paso" :class="{ 'is-activo': paso === 1, 'is-hecho': paso > 1 }" @click="irA(1)">
                        <span>1</span> Cliente
                    </button>
                    <button type="button" class="wizard__paso" :class="{ 'is-activo': paso === 2, 'is-hecho': paso > 2 }" @click="irA(2)">
                        <span>2</span> Plan
                    </button>
                    <button type="button" class="wizard__paso" :class="{ 'is-activo': paso === 3 }" @click="irA(3)">
                        <span>3</span> Pago y confirmación
                    </button>
                </nav>

                <form method="POST" action="{{ route('entrenador.inscripciones.store') }}" @submit="enviando = true">
                    @csrf

                    {{-- ---------- PASO 1: CLIENTE ---------- --}}
                    <div x-show="paso === 1" x-cloak class="formulario-panel">
                        <input type="hidden" name="member_id" :value="clienteExistenteId">

                        <x-buscador-cliente bloqueado-cuando="clienteExistenteId" />

                        <div x-show="clienteExistenteId" x-cloak class="aviso" style="display:flex;justify-content:space-between;align-items:center;gap:var(--e-3)">
                            <span>Cliente existente seleccionado — se usa su ficha, no se crea una nueva.</span>
                            <button type="button" class="btn btn--desnudo" @click="quitarClienteExistente()">Quitar</button>
                        </div>

                        <div class="formulario-panel__fila">
                            <label class="campo"><span class="campo__etiqueta">Nombres</span>
                                <input class="campo__control" type="text" name="first_name" x-model="nuevo.first_name" :disabled="clienteExistenteId"></label>
                            <label class="campo"><span class="campo__etiqueta">Apellidos</span>
                                <input class="campo__control" type="text" name="last_name" x-model="nuevo.last_name" :disabled="clienteExistenteId"></label>
                            <label class="campo"><span class="campo__etiqueta">Documento</span>
                                <input class="campo__control" type="text" name="document" x-model="nuevo.document" :disabled="clienteExistenteId"></label>
                            <label class="campo"><span class="campo__etiqueta">Teléfono</span>
                                <input class="campo__control" type="text" name="phone" x-model="nuevo.phone" :disabled="clienteExistenteId"></label>
                            <label class="campo"><span class="campo__etiqueta">Correo</span>
                                <input class="campo__control" type="email" name="email" x-model="nuevo.email" :disabled="clienteExistenteId"></label>
                            <label class="campo"><span class="campo__etiqueta">Altura (cm) — opcional</span>
                                <input class="campo__control" type="number" name="height_cm" min="100" max="260" x-model="nuevo.height_cm" :disabled="clienteExistenteId"></label>
                        </div>

                        <div class="formulario-panel__acciones">
                            <button type="button" class="btn btn--fuego" @click="siguiente()" :disabled="!puedeAvanzarPaso1()">Siguiente</button>
                        </div>
                    </div>

                    {{-- ---------- PASO 2: PLAN ---------- --}}
                    <div x-show="paso === 2" x-cloak class="formulario-panel">
                        <div class="formulario-panel__fila">
                            @foreach ($planes as $plan)
                                <label class="tarjeta tarjeta--interactiva" style="padding:var(--e-4);cursor:pointer"
                                       :style="planId == {{ $plan->id }} ? 'border-color:var(--sangre-viva)' : ''">
                                    <input type="radio" name="plan_id" value="{{ $plan->id }}" x-model="planId" style="display:none">
                                    <b style="display:block;font-family:var(--f-display);font-size:var(--t-lg)">{{ $plan->name }}</b>
                                    <span style="color:var(--bronce);font-family:var(--f-mono)">S/ {{ number_format($plan->price, 0) }}</span>
                                    <span style="display:block;color:var(--humo);font-size:var(--t-sm)">{{ $plan->duracion_legible }}</span>
                                </label>
                            @endforeach
                        </div>

                        <div class="formulario-panel__fila">
                            <label class="campo"><span class="campo__etiqueta">Inicio</span>
                                <input class="campo__control" type="date" name="starts_at" x-model="startsAt"></label>
                            <label class="campo"><span class="campo__etiqueta">Descuento (S/)</span>
                                <input class="campo__control" type="number" step="0.01" name="discount" x-model="discount"></label>
                        </div>

                        <div class="formulario-panel__acciones">
                            <button type="button" class="btn btn--vidrio" @click="paso = 1">Atrás</button>
                            <button type="button" class="btn btn--fuego" @click="siguiente()" :disabled="!planId">Siguiente</button>
                        </div>
                    </div>

                    {{-- ---------- PASO 3: PAGO Y CONFIRMACIÓN ---------- --}}
                    <div x-show="paso === 3" x-cloak class="formulario-panel">
                        <label class="campo"><span class="campo__etiqueta">
                            <input type="checkbox" name="registrar_pago" value="1" x-model="registrarPago"> Registrar pago ahora
                        </span></label>

                        <div x-show="registrarPago" x-cloak class="formulario-panel__fila">
                            <label class="campo"><span class="campo__etiqueta">Método de pago</span>
                                <select class="campo__control" name="method" x-model="method">
                                    @foreach (config('sparta.metodos_pago') as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                                </select></label>
                            <label class="campo"><span class="campo__etiqueta">Referencia</span>
                                <input class="campo__control" type="text" name="reference" x-model="reference"></label>
                        </div>

                        {{-- Matricular NO da acceso a /cliente por sí solo — son dos
                             trámites distintos (ver docs). Este checkbox los junta. --}}
                        <label class="campo" style="margin-top:var(--e-4)"><span class="campo__etiqueta">
                            <input type="checkbox" name="crear_login" value="1" x-model="crearLogin"> Crear login a "Mi cuenta" para este cliente
                        </span></label>
                        <div x-show="crearLogin" x-cloak class="formulario-panel__fila">
                            <label class="campo"><span class="campo__etiqueta">Correo para el login</span>
                                <input class="campo__control" type="email" name="access_email" x-model="accessEmail" placeholder="correo@ejemplo.com"></label>
                            <p style="color:var(--ceniza);font-size:var(--t-sm);align-self:end;margin-bottom:var(--e-3)">
                                Se genera una contraseña aleatoria que se muestra una sola vez al confirmar.
                            </p>
                        </div>

                        {{-- Resumen final: nada de sorpresas al confirmar --}}
                        <div class="tarjeta" style="padding:var(--e-5);background:var(--metal)">
                            <div class="ficha__dato"><span>Cliente</span><span x-text="nuevo.first_name + ' ' + nuevo.last_name"></span></div>
                            <div class="ficha__dato"><span>Plan</span><span x-text="nombrePlan()"></span></div>
                            <div class="ficha__dato"><span>A pagar hoy</span><span x-text="registrarPago ? ('S/ ' + montoFinal()) : 'No se registra pago'"></span></div>
                        </div>

                        <div class="formulario-panel__acciones">
                            <button type="button" class="btn btn--vidrio" @click="paso = 2">Atrás</button>
                            <button class="btn btn--fuego btn--bloque" type="submit" :disabled="enviando">
                                <span x-show="!enviando">Confirmar inscripción</span>
                                <span x-show="enviando" x-cloak>Guardando…</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
function inscripcion() {
    return {
        paso: 1, enviando: false,
        nuevo: { first_name: '', last_name: '', document: '', phone: '', email: '', height_cm: '' },
        planId: null, startsAt: new Date().toISOString().slice(0, 10), discount: 0,
        registrarPago: true, method: 'efectivo', reference: '',
        crearLogin: false, accessEmail: '',
        planes: @json($planes->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'price' => (float) $p->price])),

        // Selector de cliente existente — elegir uno rellena el paso 1 solo.
        buscarQ: '', resultados: [], clienteExistenteId: null,

        init() {},

        buscarCliente() {
            if (this.buscarQ.trim().length < 2) { this.resultados = []; return; }
            fetch('{{ route('entrenador.inscripciones.buscar') }}?q=' + encodeURIComponent(this.buscarQ))
                .then(r => r.json())
                .then(d => this.resultados = d)
                .catch(() => this.resultados = []);
        },
        elegirCliente(m) {
            this.clienteExistenteId = m.id;
            this.nuevo = { first_name: m.first_name, last_name: m.last_name, document: m.document ?? '', phone: m.phone ?? '', email: m.email ?? '', height_cm: '' };
            this.buscarQ = m.first_name + ' ' + m.last_name + (m.code ? ' (' + m.code + ')' : '');
            this.resultados = [];
        },
        quitarClienteExistente() {
            this.clienteExistenteId = null;
            this.buscarQ = '';
            this.nuevo = { first_name: '', last_name: '', document: '', phone: '', email: '' };
        },

        puedeAvanzarPaso1() {
            return this.nuevo.first_name.trim() && this.nuevo.last_name.trim();
        },
        siguiente() {
            if (this.paso === 2 && !this.accessEmail) {
                this.accessEmail = this.nuevo.email;
            }
            if (this.paso < 3) this.paso++;
        },
        irA(n) { if (n < this.paso || (n === 2 && this.puedeAvanzarPaso1()) || (n === 3 && this.planId)) this.paso = n; },

        nombrePlan() { return this.planes.find(p => p.id == this.planId)?.name ?? '—'; },
        montoFinal() {
            const plan = this.planes.find(p => p.id == this.planId);
            const precio = plan ? plan.price : 0;
            return (precio - (Number(this.discount) || 0)).toFixed(2);
        },
    };
}
</script>
@endpush
