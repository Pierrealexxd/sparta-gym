<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberMeasurement;
use App\Models\Plan;
use App\Services\NotificationService;
use App\Support\GymContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * El CRUD central del negocio: la ficha del cliente.
 *
 * `show()` es deliberadamente la vista más cargada de datos de todo el
 * panel —trae membresía, medidas, pagos y asistencia de un tirón— porque
 * es la pantalla que recepción abre cien veces al día y no debería
 * obligar a saltar de pestaña en pestaña para ver el cuadro completo.
 */
class MemberController extends Controller
{
    public function index(Request $request): View
    {
        // Pestañas sobre el listado (mismo patrón que Ventas): la pestaña
        // activa vive en la URL y cualquier valor fuera de la lista blanca
        // cae a 'todos'. El pase diario se identifica por duración (= 1
        // día), nunca por nombre; 'membresias' es todo lo de duración
        // mayor (mensual en adelante). Un socio con pase diario Y
        // membresía vigentes aparece legítimamente en ambas pestañas.
        $tipo = in_array($request->get('tipo'), ['todos', 'rutinas', 'membresias', 'por-vencer'], true)
            ? $request->get('tipo')
            : 'todos';

        $asistieronHoy = $request->get('asistencia') === 'hoy';

        // Periodicidad del plan: solo aplica en la pestaña "membresías".
        // Valores válidos: mensual (≤ 30d), trimestral (31–90d),
        // semestral (91–180d), anual (> 180d). Cualquier otro cae a null
        // (sin filtro de periodicidad).
        $periodicidad = in_array($request->get('periodicidad'), ['mensual', 'trimestral', 'semestral', 'anual'], true)
            ? $request->get('periodicidad')
            : null;

        // "Asistieron hoy" = registro de entrada del DÍA EN CURSO. Recepción
        // tiene dos pruebas del ingreso y basta una:
        //   1. Check-in físico: asistencia fechada hoy.
        //   2. Pase diario cobrado HOY (venta completada con sold_at de hoy):
        //      comprar el pase ES entrar ese día, y la marca de asistencia
        //      puede llegar después o fallar (QR en construcción).
        // El pase diario se identifica por duración (= 1 día), nunca por
        // nombre, y su VENTANA debe cubrir hoy aunque el flag de estado diga
        // otra cosa: una renovación o el cron pueden marcarlo 'vencida'
        // antes de que expire de verdad (status ≠ verdad, fechas sí).
        // La venta cuenta solo si está completada: una anulada no es entrada.
        // El OR va agrupado para no filtrar las condiciones de pestaña; así,
        // en "Todos" un socio mensual que asistió hoy aparece igual — el
        // requisito de plan diario lo pone la pestaña Rutinas, no el botón.
        $inicioDia = now()->startOfDay();
        $finDia    = now()->endOfDay();

        $socios = Member::query()
            ->buscar($request->get('q'))
            ->when($request->get('estado'), fn ($q, $estado) => $q->where('status', $estado))
            // Rutinas diarias: pase diario vigente (ya empezado y sin vencer).
            ->when($tipo === 'rutinas', fn ($q) => $q->whereHas('memberships', fn ($m) => $m
                ->vigentes()
                ->whereDate('starts_at', '<=', today())
                ->whereHas('plan', fn ($p) => $p->where('duration_days', 1))))
            // Membresías: plan de mediana/larga duración vigente (> 1 día).
            ->when($tipo === 'membresias', fn ($q) => $q->whereHas('memberships', fn ($m) => $m
                ->vigentes()
                ->whereDate('starts_at', '<=', today())
                ->whereHas('plan', function ($p) use ($periodicidad) {
                    $p->where('duration_days', '>', 1);
                    // Filtro de periodicidad: acota por rango de días del
                    // plan asociado a la membresía vigente.
                    match ($periodicidad) {
                        'mensual'    => $p->where('duration_days', '<=', 30),
                        'trimestral' => $p->whereBetween('duration_days', [31, 90]),
                        'semestral'  => $p->whereBetween('duration_days', [91, 180]),
                        'anual'      => $p->where('duration_days', '>', 180),
                        default      => null,
                    };
                })))
            // Por vencer: membresía activa cuyo vencimiento cae en los
            // próximos N días (mismo umbral que el cron y el botón de
            // WhatsApp en el detalle del cliente). EXCLUYE pases diarios
            // de raíz: un pase de 1 día vendido hoy vence mañana y sin
            // este corte saturaba la pestaña con ruido que ya tiene su
            // propia pestaña (Rutinas diarias). Quedan las membresías de
            // duración real: semanales, mensuales, trimestrales,
            // semestrales y anuales.
            ->when($tipo === 'por-vencer', fn ($q) => $q->whereHas('memberships', fn ($m) => $m
                ->vencenEn((int) config('sparta.aviso_vencimiento_dias', 7))
                ->whereHas('plan', fn ($p) => $p->where('duration_days', '>', 1))))
            ->when($asistieronHoy, fn ($q) => $q->where(function ($q) use ($inicioDia, $finDia) {
                $q->whereHas('attendances', fn ($a) => $a->whereBetween('checked_in_at', [$inicioDia, $finDia]))
                  ->orWhereHas('memberships', fn ($m) => $m
                      ->whereDate('starts_at', '<=', today())
                      ->whereDate('ends_at', '>=', today())
                      ->whereHas('plan', fn ($p) => $p->where('duration_days', 1))
                      // La venta que respalda el pase: registrada y cobrada
                      // en el día en curso (paid_at/sold_at real de caja).
                      ->whereHas('sales', fn ($s) => $s
                          ->where('status', 'completada')
                          ->whereDate('sold_at', today())));
            }))
            ->with('currentMembership')
            ->with(['attendances' => fn ($q) => $q->latest('checked_in_at')->take(1)])
            ->when(GymContext::id() === null, fn ($q) => $q->with('gym'))
            ->orderBy('last_name')
            ->paginate(10)
            ->onEachSide(1)
            ->withQueryString();

        return view('admin.clientes.index', [
            'clientes' => $socios,
            // Pestaña activa para la barra y el hidden del formulario.
            'tipo'     => $tipo,
            // Periodicidad seleccionada (para preseleccionar el option en el
            // dropdown de la pestaña "Membresías").
            'periodicidad' => $periodicidad,
            // Para el modal "Nueva matrícula" — vive en esta pantalla en vez
            // de una página propia (ver MatriculaController::store).
            'planes'   => auth()->user()->tienePermiso('clientes.crear')
                ? Plan::activos()->orderBy('price')->get()
                : collect(),
            // Nombre del pase diario de la sede para el tooltip del filtro.
            'planDiario' => Plan::where('duration_days', 1)->first(),
        ]);
    }

    /**
     * Selector de cliente existente para el modal "Nueva matrícula": elegir
     * uno rellena el paso 1 solo, para no volver a teclear los datos de
     * alguien que ya está en el sistema (ver MatriculaController::store,
     * que usa MatriculaService::renovarMembresia cuando llega member_id).
     */
    public function buscar(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q'));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $socios = Member::buscar($q)->take(8)->get();

        return response()->json($socios->map(fn (Member $m) => [
            'id'         => $m->id,
            // full_name: lo que pinta el desplegable (x-buscador-cliente);
            // el resto queda para autorrellenar el formulario al elegirlo.
            'full_name'  => $m->full_name,
            'first_name' => $m->first_name,
            'last_name'  => $m->last_name,
            'document'   => $m->document,
            'phone'      => $m->phone,
            'email'      => $m->email,
            'code'       => $m->code,
        ]));
    }

    /**
     * Chequeo anti-duplicados en vivo del modal "Nueva matrícula": mientras
     * el operador teclea documento, correo o nombres y apellidos, el paso 1
     * consulta acá. Misma regla que frena MatriculaController::store — pero
     * avisando ANTES de llenar plan y pago, no al chocar con el error.
     *
     * Devuelve a lo más un cliente: primero gana el documento exacto, luego
     * el correo exacto y recién después la coincidencia de nombres completos
     * (comparación laxa a mayúsculas y espacios, vía Member::normalizarNombre).
     */
    public function verificar(Request $request): JsonResponse
    {
        $documento = trim((string) $request->get('document'));
        $correo    = trim((string) $request->get('email'));
        $nombres   = Member::normalizarNombre($request->get('first_name'));
        $apellidos = Member::normalizarNombre($request->get('last_name'));

        $encontrado = null;
        $motivo     = null;

        if (mb_strlen($documento) >= 4) {
            $encontrado = Member::where('document', $documento)->first();
            $motivo     = 'documento';
        }

        if (! $encontrado && $correo !== '') {
            $encontrado = Member::where('email', $correo)->first();
            $motivo     = 'correo';
        }

        if (! $encontrado && mb_strlen($nombres) >= 3 && mb_strlen($apellidos) >= 3) {
            // LOWER/TRIM en SQL para no depender del collation de la tabla;
            // el lado PHP ya viene normalizado igual desde normalizarNombre.
            $encontrado = Member::whereRaw('LOWER(TRIM(first_name)) = ?', [$nombres])
                ->whereRaw('LOWER(TRIM(last_name)) = ?', [$apellidos])
                ->first();
            $motivo = 'nombre';
        }

        if (! $encontrado) {
            return response()->json(['coincide' => false]);
        }

        return response()->json([
            'coincide' => true,
            'motivo'   => $motivo,
            'cliente'  => [
                'id'         => $encontrado->id,
                'full_name'  => $encontrado->full_name,
                'code'       => $encontrado->code,
                'first_name' => $encontrado->first_name,
                'last_name'  => $encontrado->last_name,
                'document'   => $encontrado->document,
                'phone'      => $encontrado->phone,
                'email'      => $encontrado->email,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validarDatos($request);
        $datos['photo_path'] = $this->guardarFoto($request);
        $datos['code'] = Member::generarCodigo();

        $socio = Member::create($datos);

        return redirect()->route('admin.clientes.show', $socio)->with('exito', 'Cliente registrado.');
    }

    public function show(Request $request, Member $member): View
    {
        $member->load([
            'currentMembership.plan',
            'currentAssignment.trainer.user',
            // Sin límite a propósito: graficoPeso() necesita el historial
            // completo para la curva de peso, no solo la página visible de
            // la tabla de abajo (esa usa $medidasPag, aparte).
            'measurements' => fn ($q) => $q->orderBy('measured_at'),
            'goals' => fn ($q) => $q->activos(),
            'memberships' => fn ($q) => $q->with('createdBy')->latest('starts_at'),
            'sales' => fn ($q) => $q->completadas()->with('items')->latest('sold_at')->take(10),
            'attendances' => fn ($q) => $q->latest('checked_in_at')->take(10),
        ]);

        // Nunca se interpola request('tab') crudo en la vista (viaja dentro
        // de un x-data de Alpine, que Alpine evalúa como JS — un valor con
        // comillas ahí sería inyección). Se resuelve acá contra una lista
        // blanca de las 5 pestañas reales; cualquier otra cosa cae a 'resumen'.
        $tabActiva = in_array($request->get('tab'), ['resumen', 'medidas', 'membresias', 'asistencia'], true)
            ? $request->get('tab')
            : 'resumen';

        return view('admin.clientes.show', [
            'cliente'    => $member,
            'grafico'    => $this->graficoPeso($member),
            // paginate(), no simplePaginate(): la vista de paginación propia
            // del panel (resources/views/vendor/pagination/panel.blade.php)
            // llama a $paginator->total() y usa $elements — eso solo lo
            // provee LengthAwarePaginator, con simplePaginate() habría
            // roto la pestaña con un error en cuanto alguien la abriera.
            'medidasPag' => $member->measurements()->latest('measured_at')->paginate(10)->appends(['tab' => 'medidas']),
            'tabActiva'  => $tabActiva,
        ]);
    }

    /** Vista de impresión standalone del carnet, con el QR real embebido. */
    public function carnet(Member $member): View
    {
        return view('admin.clientes.carnet', ['cliente' => $member]);
    }

    public function edit(Member $member): View
    {
        return view('admin.clientes.form', ['cliente' => $member]);
    }

    public function update(Request $request, Member $member): RedirectResponse
    {
        $datos = $this->validarDatos($request);

        if ($ruta = $this->guardarFoto($request)) {
            if ($member->photo_path) {
                Storage::disk('public')->delete($member->photo_path);
            }
            $datos['photo_path'] = $ruta;
        }

        $member->update($datos);

        return redirect()->route('admin.clientes.show', $member)->with('exito', 'Datos actualizados.');
    }

    public function destroy(Member $member): RedirectResponse
    {
        $member->delete();

        return redirect()->route('admin.clientes.index')->with('exito', 'Cliente eliminado.');
    }

    public function destroyMasivo(Request $request): RedirectResponse
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));

        if ($ids === []) {
            return back()->with('error', 'Selecciona al menos un cliente.');
        }

        $borrados = Member::whereIn('id', $ids)->delete();

        return redirect()->route('admin.clientes.index')->with('exito', "{$borrados} clientes eliminados.");
    }

    /* ---------------------------------------------------------- */

    public function guardarMedida(Request $request, Member $member): RedirectResponse
    {
        $datos = $request->validate([
            'measured_at'    => ['required', 'date'],
            'weight_kg'      => ['required', 'numeric', 'min:20', 'max:400'],
            // Opcional a propósito: en blanco, MemberMeasurement::altura cae
            // a la del socio (ver el modelo). Mismo rango que height_cm en
            // Member::validarDatos, para no aceptar acá lo que se rechaza allá.
            'height_cm'      => ['nullable', 'integer', 'min:100', 'max:250'],
            'body_fat_pct'   => ['nullable', 'numeric', 'min:2', 'max:70'],
            'chest_cm'       => ['nullable', 'numeric', 'min:30', 'max:200'],
            'waist_cm'       => ['nullable', 'numeric', 'min:30', 'max:200'],
            'hip_cm'         => ['nullable', 'numeric', 'min:30', 'max:200'],
            'arm_cm'         => ['nullable', 'numeric', 'min:10', 'max:80'],
            'thigh_cm'       => ['nullable', 'numeric', 'min:20', 'max:120'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);        $medida = $member->measurements()->create($datos + ['recorded_by' => $request->user()->id]);

        app(NotificationService::class)->notificarMedidaStaff($medida);

        return back()->with('exito', 'Medida registrada.');
    }

    public function guardarObjetivo(Request $request, Member $member): RedirectResponse
    {
        $datos = $request->validate([
            'type'         => ['required', 'in:perder_peso,ganar_musculo,fuerza,resistencia,salud,otro'],
            'title'        => ['required', 'string', 'max:120'],
            'description'  => ['nullable', 'string', 'max:500'],
            'target_value' => ['nullable', 'numeric'],
            'unit'         => ['nullable', 'string', 'max:20'],
            'target_date'  => ['nullable', 'date'],
        ]);

        $member->goals()->create($datos);

        return back()->with('exito', 'Objetivo agregado.');
    }

    /* ---------------------------------------------------------- */

    private function validarDatos(Request $request): array
    {
        $datos = $request->validate([
            'first_name'        => ['required', 'string', 'max:80'],
            'last_name'         => ['required', 'string', 'max:120'],
            'document'          => ['nullable', 'string', 'max:20'],
            'email'             => ['nullable', 'email', 'max:180'],
            'phone'             => ['nullable', 'string', 'max:40'],
            'birth_date'        => ['nullable', 'date', 'before:today'],
            'gender'            => ['nullable', 'in:M,F,O'],
            'height_cm'         => ['nullable', 'integer', 'min:100', 'max:250'],
            'emergency_contact' => ['nullable', 'string', 'max:120'],
            'emergency_phone'   => ['nullable', 'string', 'max:40'],
            'medical_notes'     => ['nullable', 'string', 'max:1000'],
            'status'            => ['required', 'in:activo,inactivo,suspendido'],
            'notes'             => ['nullable', 'string', 'max:1000'],
            'foto'              => ['nullable', 'image', 'max:3072'],
        ]);

        // 'foto' se procesa aparte (guardarFoto) y no es una columna real.
        unset($datos['foto']);

        return $datos;
    }

    private function guardarFoto(Request $request): ?string
    {
        if (! $request->hasFile('foto')) {
            return null;
        }

        return $request->file('foto')->store('socios', 'public');
    }

    private function graficoPeso(Member $member): array
    {
        return [
            'labels' => $member->measurements->map(fn (MemberMeasurement $m) => $m->measured_at->format('d/m'))->all(),
            'data'   => $member->measurements->map(fn (MemberMeasurement $m) => (float) $m->weight_kg)->all(),
        ];
    }
}
