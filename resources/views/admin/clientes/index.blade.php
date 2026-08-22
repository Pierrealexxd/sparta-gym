@extends('layouts.panel')

@section('titulo', 'Clientes')
@section('subtitulo', $clientes->total() . ' registrados en total')

@section('acciones')
    @php $todas = \App\Support\GymContext::id() === null; @endphp
    <div style="display:flex;gap:var(--e-3)">
        {{-- Abre el modal de cliente nuevo (ver abajo), mismo patrón que
              "Nueva matrícula" — antes era su propia pantalla
              (admin.clientes.create, ya no existe como ruta).
              La matrícula ya no vive acá: se matricula desde la caja,
              pestaña Registros de /admin/ventas. --}}
        <button class="btn btn--vidrio" type="button"
            @if($todas) disabled data-title="Elige una sede primero" @else @click="window.dispatchEvent(new CustomEvent('abrir-cliente'))" @endif>
            <x-icono nombre="agregar" /> Nuevo cliente
        </button>
    </div>
@endsection

@section('contenido')
    @php
        $modoTodas = \App\Support\GymContext::id() === null;
        $puedeEliminar = auth()->user()->tienePermiso('clientes.eliminar');
        $colspan = 6 + ($modoTodas ? 1 : 0) + ($puedeEliminar ? 1 : 0);
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

    {{-- Pestañas del listado, misma línea gráfica que Ventas. La pestaña
         activa vive en la URL (?tipo=…) y sobrevive a búsqueda, filtros y
         paginación: el formulario lleva un hidden tipo y el paginador usa
         withQueryString(). Cambiar de pestaña conserva lo ya filtrado;
         solo descarta la página actual. --}}
    @php
        $basePestana = request()->query();
        unset($basePestana['page']);
        $urlPestana = fn (string $pestana): string => request()->url() . '?' . http_build_query([...$basePestana, 'tipo' => $pestana]);
        $vacios = [
            'todos'      => 'No se encontraron clientes.',
            'rutinas'    => 'Nadie con pase diario vigente.',
            'membresias' => 'Nadie con membresía vigente.',
            'por-vencer' => 'Nadie con membresía próxima a vencer.',
        ];
    @endphp
    <nav class="pestanas__nav pestanas__nav--ancha" style="margin-bottom:var(--e-5)" aria-label="Filtrar clientes por tipo de plan">
        <a class="pestanas__enlace" href="{{ $urlPestana('todos') }}" aria-current="{{ $tipo === 'todos' ? 'true' : 'false' }}">Todos</a>
        <a class="pestanas__enlace" href="{{ $urlPestana('rutinas') }}" aria-current="{{ $tipo === 'rutinas' ? 'true' : 'false' }}">Rutinas diarias</a>
        <a class="pestanas__enlace" href="{{ $urlPestana('membresias') }}" aria-current="{{ $tipo === 'membresias' ? 'true' : 'false' }}">Membresías</a>
        <a class="pestanas__enlace" href="{{ $urlPestana('por-vencer') }}" aria-current="{{ $tipo === 'por-vencer' ? 'true' : 'false' }}">Por vencer</a>
    </nav>

    {{-- Toolbar adaptativo: cada pestaña muestra los filtros que le
         corresponden. La búsqueda y la pestaña siempre están. Los demás
         campos viajan como hidden cuando la pestaña no los usa, para que
         el paginador (withQueryString) no pierda valores irrelevantes. --}}
    <form class="panel__toolbar" method="GET">
        <div class="panel__busqueda">
            <x-icono nombre="lupa" />
            <input class="campo__control" type="search" name="q" value="{{ request('q') }}"
                   placeholder="Buscar por nombre, código o documento…">
        </div>

        {{-- Dropdown de estado: solo en Todos, Rutinas y Membresías.
             En "Por vencer" se oculta porque la pestaña ya aísla por
             definición las membresías próximas a expirar. --}}
        @if ($tipo !== 'por-vencer')
            <select class="campo__control" name="estado" onchange="this.form.submit()">
                <option value="">Todos los estados</option>
                @foreach (['activo' => 'Activo', 'inactivo' => 'Inactivo', 'suspendido' => 'Suspendido'] as $v => $l)
                    <option value="{{ $v }}" @selected(request('estado') === $v)>{{ $l }}</option>
                @endforeach
            </select>
        @else
            <input type="hidden" name="estado" value="">
        @endif

        {{-- Dropdown de periodicidad: solo en la pestaña Membresías.
             Permite segmentar por duración del plan (mensual, trimestral,
             semestral, anual). En las demás pestañas viaja como hidden. --}}
        @if ($tipo === 'membresias')
            <select class="campo__control" name="periodicidad" onchange="this.form.submit()">
                <option value="">Todas las membresías</option>
                <option value="mensual" @selected(request('periodicidad') === 'mensual')>Mensual</option>
                <option value="trimestral" @selected(request('periodicidad') === 'trimestral')>Trimestral</option>
                <option value="semestral" @selected(request('periodicidad') === 'semestral')>Semestral</option>
                <option value="anual" @selected(request('periodicidad') === 'anual')>Anual</option>
            </select>
        @else
            <input type="hidden" name="periodicidad" value="">
        @endif

        {{-- Botón "Asistieron hoy": solo en Todos y Rutinas diarias.
             En Membresías y Por vencer no aplica (los pases diarios van
             por su propia pestaña). --}}
        @if (in_array($tipo, ['todos', 'rutinas'], true))
            <button class="btn btn--vidrio" type="button"
                    @if ($planDiario) data-title="Pase diario: {{ $planDiario->name }}" @endif
                    onclick="this.form.elements.asistencia.value=this.form.elements.asistencia.value==='hoy'?'':'hoy';this.form.submit()">
                <x-icono nombre="entrada" /> {{ request('asistencia') === 'hoy' ? 'Ver todos' : 'Asistieron hoy' }}
            </button>
        @endif
        <input type="hidden" name="asistencia" value="{{ request('asistencia') === 'hoy' ? 'hoy' : '' }}">

        {{-- La pestaña viaja con el formulario: filtrar o buscar no la pierde. --}}
        <input type="hidden" name="tipo" value="{{ $tipo }}">
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
                        <th>Cliente</th><th class="tabla__oculta-movil">Código</th>@if ($modoTodas)<th>Sede</th>@endif<th>Membresía</th>@if ($tipo === 'por-vencer')<th class="tabla__oculta-movil">Faltan</th>@else<th class="tabla__oculta-movil">Última asistencia</th>@endif<th>Estado</th><th></th>
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
                            @if ($tipo === 'por-vencer')
                                @php $dias = $cliente->currentMembership?->dias_restantes; @endphp
                                <td class="tabla__oculta-movil" data-etiqueta="Faltan">
                                    @if ($dias !== null)
                                        <span class="estado" style="color:var(--brasa)">{{ $dias }} días</span>
                                    @else
                                        <span style="color:var(--humo)">—</span>
                                    @endif
                                </td>
                            @else
                                <td class="tabla__oculta-movil" data-etiqueta="Última asistencia">
                                    @if ($cliente->attendances->count())
                                        <span class="estado" style="color:var(--ok)">{{ $cliente->attendances->first()->checked_in_at->format('d/m/Y H:i') }}</span>
                                    @else
                                        <span style="color:var(--humo)">—</span>
                                    @endif
                                </td>
                            @endif
                            <td data-etiqueta="Estado"><span class="estado estado--{{ $cliente->status }}">{{ ucfirst($cliente->status) }}</span></td>
                            <td data-etiqueta="nada">
                                <div style="display:flex;gap:var(--e-2)">
                                    @if ($tipo === 'por-vencer' && $cliente->phone)
                                        @php
                                            $nom = $cliente->first_name;
                                            $msg = "Hola {$nom}\nTe informamos que tu membresía en Sparta GYM está próximo a vencer.\nNos encantaría que puedas renovarla y seguir entrenando con nosotros 💪🔥\nPara nosotros es un verdadero placer tenerte como parte de la familia Spartana.\n¡Te esperamos para seguir alcanzando tus objetivos juntos!";
                                            $wa = 'https://wa.me/' . preg_replace('/\D+/', '', $cliente->phone) . '?text=' . rawurlencode($msg);
                                        @endphp
                                        <a class="btn btn--desnudo" href="{{ $wa }}" target="_blank" rel="noopener" title="Enviar recordatorio por WhatsApp">
                                            <x-icono nombre="whatsapp" />
                                        </a>
                                    @endif
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
                        <tr><td colspan="{{ $colspan }}" class="tabla__vacio"><x-estado-vacio icono="usuarios" texto="{{ $vacios[$tipo] }}" /></td></tr>
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

@endsection
