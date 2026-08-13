<?php

namespace App\Http\Controllers\Entrenador;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Trainer;
use App\Services\MatriculaService;
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
        // mismo registró — el mismo dato que usa el KPI del dashboard.
        $inscripciones = Membership::with('member')
            ->where('created_by', $request->user()->id)
            ->whereNull('renewed_from')
            ->latest()
            ->paginate(10)
            ->onEachSide(1);

        // Solo se muestra el botón "Ver" (ficha del cliente) para los que
        // realmente están a su cargo: la ficha exige un TrainerAssignment
        // activo, e inscribir a alguien NO lo asigna automáticamente.
        $aCargo = Trainer::where('user_id', $request->user()->id)
            ->first()
            ?->activeMembers()
            ->pluck('members.id')
            ?? collect();

        return view('entrenador.inscripciones.index', [
            'inscripciones' => $inscripciones,
            'planes'        => Plan::activos()->orderBy('price')->get(),
            'aCargo'        => $aCargo,
        ]);
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
            'crear_acceso'   => ['nullable', 'boolean'],
            'first_name'     => ['required_without:member_id', 'nullable', 'string', 'max:80'],
            'last_name'      => ['required_without:member_id', 'nullable', 'string', 'max:120'],
            'document'       => ['nullable', 'string', 'max:20'],
            'phone'          => ['nullable', 'string', 'max:40'],
            'email'          => ['nullable', 'email', 'max:180'],
        ];

        if ($request->boolean('crear_acceso')) {
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
}
