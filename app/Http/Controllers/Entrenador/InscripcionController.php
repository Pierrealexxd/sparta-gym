<?php

namespace App\Http\Controllers\Entrenador;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Trainer;
use App\Models\User;
use App\Services\MatriculaService;
use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Versión reducida de Admin\MatriculaController para el panel del
 * entrenador: mismo trámite guiado. La lógica de negocio vive en
 * MatriculaService — no hay dos formas distintas de matricular a alguien
 * según quién esté detrás del teclado.
 *
 * Paso 1 admite además elegir un cliente que ya existe en el sistema (el
 * buscador de buscar()): ahí se salta Member::create y se usa
 * MatriculaService::renovarMembresia, para no duplicar el registro de
 * alguien que un admin o recepción ya dio de alta antes.
 *
 * El formulario vive como modal de index() (botón "Nueva inscripción"),
 * mismo patrón que Admin\MatriculaController — no hay pantalla propia.
 */
class InscripcionController extends Controller
{
    public function __construct(private readonly MatriculaService $matricula) {}

    public function index(Request $request): View
    {
        // "Sus" inscripciones: membresías nuevas (no renovaciones) que él
        // mismo registró — el mismo dato que usa el KPI de este módulo.
        $inscripciones = Membership::with('member')
            ->where('created_by', $request->user()->id)
            ->whereNull('renewed_from')
            ->latest()
            ->paginate(10)
            ->onEachSide(1);

        $entrenador = Trainer::where('user_id', $request->user()->id)->first();

        // Solo se muestra el botón "Ver" (ficha del cliente) para los que
        // realmente están a su cargo: la ficha exige un TrainerAssignment
        // activo, e inscribir a alguien NO lo asigna automáticamente.
        $aCargo = $entrenador?->activeMembers()->pluck('members.id') ?? collect();

        // Indicadores del módulo (antes vivían en el "Resumen" aparte, que
        // se dio de baja — ver decisión del 13-08-2026): lo justo para este
        // apartado, sin repetir el detalle de la tabla de abajo.
        $kpis = [
            'clientesACargo'   => $aCargo->count(),
            'inscripcionesMes' => Membership::where('created_by', $request->user()->id)
                ->whereNull('renewed_from')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return view('entrenador.inscripciones.index', [
            'inscripciones'         => $inscripciones,
            'planes'                => Plan::activos()->orderBy('price')->get(),
            'aCargo'                => $aCargo,
            'kpis'                  => $kpis,
            'graficoInscripciones'  => $this->inscripcionesPorMes($request->user()->id, 6),
        ]);
    }

    /**
     * "¿Cómo va mi captación?" — inscripciones que ÉL registró, por mes.
     * Solo las suyas (created_by = el entrenador logueado): un entrenador
     * nunca ve el agregado de otros ni del gimnasio entero. Una sola
     * consulta agrupada, no una por mes en un loop.
     */
    private function inscripcionesPorMes(int $userId, int $meses): array
    {
        $desde = now()->subMonthsNoOverflow($meses - 1)->startOfMonth();

        $filas = Membership::where('created_by', $userId)
            ->whereNull('renewed_from')
            ->where('created_at', '>=', $desde)
            ->selectRaw('YEAR(created_at) as anio, MONTH(created_at) as mes, COUNT(*) as total')
            ->groupBy('anio', 'mes')
            ->get()
            ->keyBy(fn ($f) => $f->anio . '-' . $f->mes);

        $etiquetas = $datos = [];

        for ($i = $meses - 1; $i >= 0; $i--) {
            $m = now()->subMonthsNoOverflow($i);
            $clave = $m->year . '-' . $m->month;
            $etiquetas[] = $m->translatedFormat('M');
            $datos[] = (int) ($filas[$clave]->total ?? 0);
        }

        return [
            'labels' => $etiquetas,
            'datasets' => [['label' => 'Inscripciones', 'data' => $datos, 'token' => '--brasa']],
        ];
    }

    /** Selector de cliente existente para el paso 1 del modal. */
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

    public function store(Request $request): RedirectResponse
    {
        $reglas = [
            'member_id'      => ['nullable', 'integer'],
            'plan_id'        => ['required', 'exists:plans,id'],
            'starts_at'      => ['required', 'date'],
            'discount'       => ['nullable', 'numeric', 'min:0'],
            'method'         => ['required', 'in:efectivo,transferencia,yape,plin,tarjeta,otro'],
            'reference'      => ['nullable', 'string', 'max:120'],
            'registrar_pago' => ['nullable', 'boolean'],
            // Mismo bug que Admin\MatriculaController (PLAN-CORRECCIONES-
            // TECNICAS.md 1.4): validación y ejecución debían usar el mismo
            // nombre. Unificado a 'crear_login', que es el name real del
            // checkbox en el blade.
            'crear_login'    => ['nullable', 'boolean'],
            'first_name'     => ['required_without:member_id', 'nullable', 'string', 'max:80'],
            'last_name'      => ['required_without:member_id', 'nullable', 'string', 'max:120'],
            'document'       => ['nullable', 'string', 'max:20'],
            'phone'          => ['nullable', 'string', 'max:40'],
            'email'          => ['nullable', 'email', 'max:180'],
            // Mismo arreglo que Admin\MatriculaController: opcional a
            // propósito, no puede bloquear una inscripción en el mostrador
            // (PROMPT-EJECUCION-MI-RUTINA.md, Parte 2).
            'height_cm'      => ['nullable', 'integer', 'min:100', 'max:260'],
        ];

        if ($request->boolean('crear_login')) {
            $reglas += ['access_email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')]];
        }

        $datos = $request->validate($reglas);
        $plan  = Plan::findOrFail($datos['plan_id']);

        // El global scope de BelongsToGym ya aísla por sede: si el id no
        // pertenece a la suya, Member::find() simplemente no lo encuentra.
        $existente = $datos['member_id'] ?? null ? Member::find($datos['member_id']) : null;
        if (($datos['member_id'] ?? null) && ! $existente) {
            throw ValidationException::withMessages(['member_id' => 'Ese cliente no está disponible.']);
        }

        if ($existente) {
            $resultado = $this->matricula->renovarMembresia($existente, $plan, $datos, $request->user());
        } else {
            $resultado = $this->matricula->nuevaMatricula($datos, $plan, $datos, $request->user());
        }
        $socio = $resultado['member'];

        $credenciales = null;
        if ($request->boolean('crear_login')) {
            $credenciales = $this->matricula->crearLogin($socio, $datos['access_email']);
        }

        $mensaje = "Inscripción registrada. Código {$socio->code}.";
        if ($credenciales) {
            $mensaje .= " Login creado — correo: <b>{$credenciales['email']}</b>, contraseña inicial: <b>{$credenciales['password']}</b> (entrégala y que la cambie en su primer ingreso).";
        }

        return redirect()
            ->route('entrenador.inscripciones.index')
            ->with('exito', $mensaje);
    }

    public function update(Request $request, Membership $membership): RedirectResponse
    {
        $datos = $request->validate([
            'plan_id'   => ['required', 'exists:plans,id'],
            'starts_at' => ['required', 'date'],
            'ends_at'   => ['nullable', 'date', 'after_or_equal:starts_at'],
            'discount'  => ['nullable', 'numeric', 'min:0'],
            'price'     => ['required', 'numeric', 'min:0'],
        ]);

        $plan = Plan::findOrFail($datos['plan_id']);

        $membership->update([
            'plan_id'   => $plan->id,
            'plan_name' => $plan->name,
            'price'     => $datos['price'],
            'discount'  => $datos['discount'] ?? 0,
            'starts_at' => $datos['starts_at'],
            'ends_at'   => $datos['ends_at'] ?? $membership->ends_at,
        ]);

        app(NotificationService::class)->dispararA(
            User::where('role_id', Role::where('slug', 'admin')->value('id'))->get(),
            'inscripcion.editada',
            'Inscripción editada',
            "El entrenador {$request->user()->name} editó la inscripción de {$membership->member->full_name} ({$plan->name})",
            'usuarios',
            'media',
            $membership->id,
            route('admin.clientes.show', $membership->member),
        );

        return redirect()
            ->route('entrenador.inscripciones.index')
            ->with('exito', "Inscripción de {$membership->member->full_name} actualizada.");
    }
}
