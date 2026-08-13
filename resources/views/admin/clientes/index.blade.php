@extends('layouts.panel')

@section('titulo', 'Clientes')
@section('subtitulo', $clientes->total() . ' registrados en total')

@section('acciones')
    <div style="display:flex;gap:var(--e-3)">
        {{-- Abre el modal de cliente nuevo (ver abajo), mismo patrón que
             "Nueva matrícula" — antes era su propia pantalla
             (admin.clientes.create, ya no existe como ruta). --}}
        <button class="btn btn--vidrio" type="button" @click="window.dispatchEvent(new CustomEvent('abrir-cliente'))">
            <x-icono nombre="agregar" /> Nuevo cliente
        </button>
        @if (auth()->user()->tienePermiso('clientes.crear'))
            <button class="btn btn--fuego" type="button" @click="window.dispatchEvent(new CustomEvent('abrir-matricula'))">
                <x-icono nombre="agregar" /> Nueva matrícula
            </button>
        @endif
    </div>
@endsection

@section('contenido')
    @php
        $modoTodas = \App\Support\GymContext::id() === null;
        $puedeEliminar = auth()->user()->tienePermiso('clientes.eliminar');
        $colspan = 5 + ($modoTodas ? 1 : 0) + ($puedeEliminar ? 1 : 0);
        // El modal de edición (abajo) se reabre solo si el error de
        // validación vino de su formulario — ver _origen en el modal.
        $errorEditor = $errors->any() && old('_origen') === 'cliente-editar';
        $editorVacios = [
            'first_name' => '', 'last_name' => '', 'document' => '', 'phone' => '',
            'email' => '', 'birth_date' => '', 'gender' => '', 'height_cm' => '',
            'status' => 'activo', 'emergency_contact' => '', 'emergency_phone' => '',
            'medical_notes' => '', 'notes' => '',
        ];
    @endphp

    <form class="panel__toolbar" method="GET">
        <div class="panel__busqueda">
            <x-icono nombre="lupa" />
            <input class="campo__control" type="search" name="q" value="{{ request('q') }}"
                   placeholder="Buscar por nombre, código o documento…">
        </div>

        <select class="campo__control" name="estado" style="max-width:180px" onchange="this.form.submit()">
            <option value="">Todos los estados</option>
            @foreach (['activo' => 'Activo', 'inactivo' => 'Inactivo', 'suspendido' => 'Suspendido'] as $v => $l)
                <option value="{{ $v }}" @selected(request('estado') === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </form>

    <div class="tabla-bulk" x-data="{
        seleccionados: [],
        idsPagina: @js($clientes->pluck('id')->all()),
        alternar(id) {
            this.seleccionados.includes(id)
                ? this.seleccionados = this.seleccionados.filter(i => i !== id)
                : this.seleccionados.push(id);
        },
        seleccionarTodos(estado) {
            this.seleccionados = estado ? this.idsPagina.slice() : [];
        },
    }">
        <div class="tabla-envoltorio" data-revelar>
            <table class="tabla tabla--tarjetas">
                <thead>
                    <tr>
                        @if ($puedeEliminar)
                            <th class="tabla__check">
                                <input type="checkbox"
                                       :checked="seleccionados.length > 0 && idsPagina.every(i => seleccionados.includes(i))"
                                       @change="seleccionarTodos($el.checked)"
                                       aria-label="Seleccionar todos los de esta página">
                            </th>
                        @endif
                        <th>Cliente</th><th class="tabla__oculta-movil">Código</th>@if ($modoTodas)<th>Sede</th>@endif<th>Membresía</th><th>Estado</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clientes as $cliente)
                        <tr :class="{ 'is-seleccionada': seleccionados.includes({{ $cliente->id }}) }">
                            @if ($puedeEliminar)
                                <td class="tabla__check" data-etiqueta="check">
                                    <input type="checkbox"
                                           :checked="seleccionados.includes({{ $cliente->id }})"
                                           @change="alternar({{ $cliente->id }})"
                                           aria-label="Seleccionar a {{ $cliente->full_name }}">
                                </td>
                            @endif
                            <td class="es-fuerte" data-etiqueta="Cliente">
                                <a href="{{ route('admin.clientes.show', $cliente) }}">{{ $cliente->full_name }}</a>
                            </td>
                            <td class="tabla__oculta-movil" style="font-family:var(--f-mono)" data-etiqueta="Código">{{ $cliente->code }}</td>
                            @if ($modoTodas)
                                <td data-etiqueta="Sede"><span class="estado">{{ $cliente->gym?->name ?? '—' }}</span></td>
                            @endif
                            <td data-etiqueta="Membresía">
                                @if ($cliente->currentMembership)
                                    Vence {{ $cliente->currentMembership->ends_at->translatedFormat('d M Y') }}
                                @else
                                    <span style="color:var(--humo)">Sin membresía</span>
                                @endif
                            </td>
                            <td data-etiqueta="Estado"><span class="estado estado--{{ $cliente->status }}">{{ ucfirst($cliente->status) }}</span></td>
                            <td data-etiqueta="nada">
                                <div style="display:flex;gap:var(--e-2)">
                                    <button class="btn btn--desnudo" type="button"
                                            @click="window.dispatchEvent(new CustomEvent('abrir-cliente-editar', { detail: @js([
                                                'id' => $cliente->id,
                                                'first_name' => $cliente->first_name,
                                                'last_name' => $cliente->last_name,
                                                'document' => $cliente->document,
                                                'phone' => $cliente->phone,
                                                'email' => $cliente->email,
                                                'birth_date' => $cliente->birth_date?->format('Y-m-d'),
                                                'gender' => $cliente->gender,
                                                'height_cm' => $cliente->height_cm,
                                                'status' => $cliente->status,
                                                'emergency_contact' => $cliente->emergency_contact,
                                                'emergency_phone' => $cliente->emergency_phone,
                                                'medical_notes' => $cliente->medical_notes,
                                                'notes' => $cliente->notes,
                                            ]) }))">
                                        <x-icono nombre="lapiz" />
                                    </button>
                                    @if ($puedeEliminar)
                                        <button class="btn btn--desnudo" type="button"
                                                @click="$store.confirmar.abrir({
                                                    accion: '{{ route('admin.clientes.destroy', $cliente) }}',
                                                    titulo: 'Eliminar cliente',
                                                    mensaje: '¿Eliminar a {{ $cliente->full_name }}?',
                                                    etiqueta: 'Eliminar'
                                                })">
                                            <x-icono nombre="papelera" />
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $colspan }}" class="tabla__vacio"><x-estado-vacio icono="usuarios" texto="No se encontraron clientes." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($puedeEliminar)
            <div class="bulk-bar" x-show="seleccionados.length > 0" x-cloak>
            <span class="bulk-bar__info"
                  x-text="seleccionados.length + (seleccionados.length === 1 ? ' seleccionado' : ' seleccionados')"></span>
            <button class="btn btn--vidrio" type="button" @click="seleccionados = []">Limpiar</button>
            <button class="btn btn--fuego" type="button"
                    @click="$store.confirmar.abrir({
                        accion: '{{ route('admin.clientes.masivo') }}',
                        titulo: 'Eliminar seleccionados',
                        mensaje: 'Se eliminarán ' + seleccionados.length + ' clientes. Esta acción no se puede deshacer.',
                        etiqueta: 'Eliminar',
                        ids: seleccionados.slice()
                    })">
                <x-icono nombre="papelera" /> Eliminar seleccionados
            </button>
        </div>
        @endif

        <div class="paginacion">{{ $clientes->links() }}</div>
    </div>

    @if ($puedeEliminar)
        <x-modal-confirmar />
    @endif

    {{-- Ficha de cliente nuevo (sin plan/membresía — eso es "Nueva
         matrícula"), como modal de esta pantalla. Antes era su propia
         página (admin.clientes.create, ya no existe como ruta). 'status'
         es el campo que distingue si el error de validación es de este
         formulario o del de matrícula, para reabrir solo el que falló. --}}
    <div x-data="{ abierta: {{ $errors->any() && old('status') !== null && old('_origen') !== 'cliente-editar' ? 'true' : 'false' }} }"
         x-on:abrir-cliente.window="abierta = true"
         @keydown.escape.window="abierta = false">
        <div class="modal__fondo" x-show="abierta" x-cloak>
            <div class="tarjeta modal__caja formulario-panel" @click.outside="abierta = false">
                <div class="modal__cabecera">
                    <h3>Nuevo cliente</h3>
                    <button class="modal__cerrar" type="button" @click="abierta = false" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
                </div>

                <form class="formulario-panel" method="POST" action="{{ route('admin.clientes.store') }}" enctype="multipart/form-data">
                    @csrf

                    @if ($errors->any() && old('status') !== null)
                        <div class="aviso aviso--error" role="alert">{{ $errors->first() }}</div>
                    @endif

                    <div class="formulario-panel__fila">
                        <label class="campo">
                            <span class="campo__etiqueta">Nombres</span>
                            <input class="campo__control" type="text" name="first_name" required value="{{ old('first_name') }}">
                            @error('first_name')<span class="campo__error">{{ $message }}</span>@enderror
                        </label>
                        <label class="campo">
                            <span class="campo__etiqueta">Apellidos</span>
                            <input class="campo__control" type="text" name="last_name" required value="{{ old('last_name') }}">
                            @error('last_name')<span class="campo__error">{{ $message }}</span>@enderror
                        </label>
                    </div>

                    <div class="formulario-panel__fila">
                        <label class="campo">
                            <span class="campo__etiqueta">Documento</span>
                            <input class="campo__control" type="text" name="document" value="{{ old('document') }}">
                        </label>
                        <label class="campo">
                            <span class="campo__etiqueta">Teléfono</span>
                            <input class="campo__control" type="text" name="phone" value="{{ old('phone') }}">
                        </label>
                    </div>

                    <label class="campo">
                        <span class="campo__etiqueta">Correo</span>
                        <input class="campo__control" type="email" name="email" value="{{ old('email') }}">
                    </label>

                    <div class="formulario-panel__fila">
                        <label class="campo">
                            <span class="campo__etiqueta">Nacimiento</span>
                            <input class="campo__control" type="date" name="birth_date" value="{{ old('birth_date') }}">
                        </label>
                        <label class="campo">
                            <span class="campo__etiqueta">Género</span>
                            <select class="campo__control" name="gender">
                                <option value="">—</option>
                                <option value="M" @selected(old('gender') === 'M')>Masculino</option>
                                <option value="F" @selected(old('gender') === 'F')>Femenino</option>
                                <option value="O" @selected(old('gender') === 'O')>Otro</option>
                            </select>
                        </label>
                    </div>

                    <div class="formulario-panel__fila">
                        <label class="campo">
                            <span class="campo__etiqueta">Altura (cm)</span>
                            <input class="campo__control" type="number" name="height_cm" value="{{ old('height_cm') }}">
                        </label>
                        <label class="campo">
                            <span class="campo__etiqueta">Estado</span>
                            <select class="campo__control" name="status" required>
                                @foreach (['activo' => 'Activo', 'inactivo' => 'Inactivo', 'suspendido' => 'Suspendido'] as $v => $l)
                                    <option value="{{ $v }}" @selected(old('status', 'activo') === $v)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="formulario-panel__fila">
                        <label class="campo">
                            <span class="campo__etiqueta">Contacto de emergencia</span>
                            <input class="campo__control" type="text" name="emergency_contact" value="{{ old('emergency_contact') }}">
                        </label>
                        <label class="campo">
                            <span class="campo__etiqueta">Teléfono de emergencia</span>
                            <input class="campo__control" type="text" name="emergency_phone" value="{{ old('emergency_phone') }}">
                        </label>
                    </div>

                    <label class="campo">
                        <span class="campo__etiqueta">Notas médicas</span>
                        <textarea class="campo__control" name="medical_notes">{{ old('medical_notes') }}</textarea>
                    </label>

                    <label class="campo">
                        <span class="campo__etiqueta">Fotografía</span>
                        <input class="campo__control" type="file" name="foto" accept="image/*">
                    </label>

                    <label class="campo">
                        <span class="campo__etiqueta">Notas</span>
                        <textarea class="campo__control" name="notes">{{ old('notes') }}</textarea>
                    </label>

                    <div class="formulario-panel__acciones">
                        <button class="btn btn--vidrio" type="button" @click="abierta = false">Cancelar</button>
                        <button class="btn btn--fuego" type="submit">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Editar cliente como modal de esta pantalla (antes: admin.clientes.form).
         El botón de la fila despacha el registro por el evento abrir-cliente-editar
         y el formulario se rellena solo; si la validación falla, _origen distingue
         este formulario de los otros dos modales y lo vuelve a abrir con lo tecleado. --}}
    <div class="modal__fondo"
         x-data="editorGenerico(@js([
            'abierta'   => $errorEditor,
            'editando'  => (bool) old('id'),
            'crearUrl'  => route('admin.clientes.store'),
            'editarUrl' => route('admin.clientes.update', '__ID__'),
            'base'      => $editorVacios,
            'fila'      => $errorEditor ? old() : $editorVacios,
         ]))"
         x-show="abierta" x-cloak
         @abrir-cliente-editar.window="abrir($event.detail)"
         @keydown.escape.window="cerrar()">
        <div class="tarjeta modal__caja formulario-panel" @click.outside="cerrar()">
            <div class="modal__cabecera">
                <h3 x-text="editando ? 'Editar cliente' : 'Nuevo cliente'"></h3>
                <button class="modal__cerrar" type="button" @click="cerrar()" aria-label="Cerrar"><x-icono nombre="cerrar" /></button>
            </div>

            <form class="formulario-panel" method="POST" :action="accion" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_method" :value="editando ? 'PUT' : ''">
                <input type="hidden" name="_origen" value="cliente-editar">
                <input type="hidden" name="id" :value="fila.id">

                @if ($errors->any() && old('_origen') === 'cliente-editar')
                    <div class="aviso aviso--error" role="alert">{{ $errors->first() }}</div>
                @endif

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Nombres</span>
                        <input class="campo__control" type="text" name="first_name" required x-model="fila.first_name"></label>
                    <label class="campo"><span class="campo__etiqueta">Apellidos</span>
                        <input class="campo__control" type="text" name="last_name" required x-model="fila.last_name"></label>
                </div>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Documento</span>
                        <input class="campo__control" type="text" name="document" x-model="fila.document"></label>
                    <label class="campo"><span class="campo__etiqueta">Teléfono</span>
                        <input class="campo__control" type="text" name="phone" x-model="fila.phone"></label>
                </div>

                <label class="campo"><span class="campo__etiqueta">Correo</span>
                    <input class="campo__control" type="email" name="email" x-model="fila.email"></label>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Nacimiento</span>
                        <input class="campo__control" type="date" name="birth_date" x-model="fila.birth_date"></label>
                    <label class="campo"><span class="campo__etiqueta">Género</span>
                        <select class="campo__control" name="gender" x-model="fila.gender">
                            <option value="">—</option>
                            <option value="M">Masculino</option>
                            <option value="F">Femenino</option>
                            <option value="O">Otro</option>
                        </select></label>
                </div>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Altura (cm)</span>
                        <input class="campo__control" type="number" name="height_cm" x-model="fila.height_cm"></label>
                    <label class="campo"><span class="campo__etiqueta">Estado</span>
                        <select class="campo__control" name="status" required x-model="fila.status">
                            @foreach (['activo' => 'Activo', 'inactivo' => 'Inactivo', 'suspendido' => 'Suspendido'] as $v => $l)
                                <option value="{{ $v }}">{{ $l }}</option>
                            @endforeach
                        </select></label>
                </div>

                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Contacto de emergencia</span>
                        <input class="campo__control" type="text" name="emergency_contact" x-model="fila.emergency_contact"></label>
                    <label class="campo"><span class="campo__etiqueta">Teléfono de emergencia</span>
                        <input class="campo__control" type="text" name="emergency_phone" x-model="fila.emergency_phone"></label>
                </div>

                <label class="campo"><span class="campo__etiqueta">Notas médicas</span>
                    <textarea class="campo__control" name="medical_notes" style="min-height:6rem" x-model="fila.medical_notes"></textarea></label>

                <label class="campo"><span class="campo__etiqueta">Fotografía <span style="color:var(--humo)">(reemplaza la actual)</span></span>
                    <input class="campo__control" type="file" name="foto" accept="image/*"></label>

                <label class="campo"><span class="campo__etiqueta">Notas</span>
                    <textarea class="campo__control" name="notes" style="min-height:6rem" x-model="fila.notes"></textarea></label>

                <div class="formulario-panel__acciones">
                    <button class="btn btn--vidrio" type="button" @click="cerrar()">Cancelar</button>
                    <button class="btn btn--fuego" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    @if (auth()->user()->tienePermiso('clientes.crear'))
        {{-- Wizard de matrícula (cliente nuevo + plan + pago), como modal de
             esta pantalla — antes era una página propia (admin.matricula.create).
             Se abre solo: por el botón de arriba, o de entrada si la última
             sumisión trajo errores de validación (para no perder lo tecleado). --}}
        <div x-data="{ abierta: {{ $errors->any() && old('plan_id') !== null ? 'true' : 'false' }} }"
             x-on:abrir-matricula.window="abierta = true"
             @keydown.escape.window="abierta = false">
            <div class="modal__fondo" x-show="abierta" x-cloak>
                <div class="tarjeta modal__caja wizard"
                     x-data="matricula()" x-init="init()" @click.outside="abierta = false">
                    <div class="modal__cabecera">
                        <h3>Nueva matrícula</h3>
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
                    <nav class="wizard__pasos" aria-label="Progreso de matrícula">
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

                    <form method="POST" action="{{ route('admin.matricula.store') }}" @submit="enviando = true">
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
                                    <span x-show="!enviando">Confirmar matrícula</span>
                                    <span x-show="enviando" x-cloak>Guardando…</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
function matricula() {
    return {
        paso: 1, enviando: false,
        nuevo: { first_name: '', last_name: '', document: '', phone: '', email: '' },
        planId: null, startsAt: new Date().toISOString().slice(0, 10), discount: 0,
        registrarPago: true, method: 'efectivo', reference: '',
        crearAcceso: false, accessEmail: '',
        planes: @json($planes->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'price' => (float) $p->price])),

        // Selector de cliente existente — elegir uno rellena el paso 1 solo.
        buscarQ: '', resultados: [], clienteExistenteId: null,

        init() {},

        buscarCliente() {
            if (this.buscarQ.trim().length < 2) { this.resultados = []; return; }
            fetch('{{ route('admin.clientes.buscar') }}?q=' + encodeURIComponent(this.buscarQ))
                .then(r => r.json())
                .then(d => this.resultados = d)
                .catch(() => this.resultados = []);
        },
        elegirCliente(m) {
            this.clienteExistenteId = m.id;
            this.nuevo = { first_name: m.first_name, last_name: m.last_name, document: m.document ?? '', phone: m.phone ?? '', email: m.email ?? '' };
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
