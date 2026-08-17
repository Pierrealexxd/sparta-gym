<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Plan;
use App\Services\MatriculaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Matrícula guiada: cliente + plan + pago en un solo trámite.
 *
 * Paso 1 del modal admite elegir un cliente que ya existe (ver
 * MemberController::buscar, el selector) para no volver a teclear sus
 * datos — ahí se salta Member::create y se usa
 * MatriculaService::renovarMembresia. Sin elegir a nadie, sigue siendo
 * alta de cliente nuevo (nuevaMatricula).
 *
 * El formulario vive como modal dentro de admin.clientes.index (ver
 * MemberController::index, que le pasa $planes) — acá solo queda el store.
 */
class MatriculaController extends Controller
{
    public function __construct(private readonly MatriculaService $matricula) {}

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
            // Nombre unificado a 'crear_login': el checkbox del blade manda
            // ese name y la ejecución de abajo también lo lee. Con
            // 'crear_acceso' aquí, access_email nunca entraba a $datos y la
            // línea 76 tiraba 500 (Undefined array key) apenas alguien
            // marcaba la casilla (PLAN-CORRECCIONES-TECNICAS.md 1.3).
            'crear_login'    => ['nullable', 'boolean'],
            'first_name'     => ['required_without:member_id', 'nullable', 'string', 'max:80'],
            'last_name'      => ['required_without:member_id', 'nullable', 'string', 'max:120'],
            'document'       => ['nullable', 'string', 'max:20'],
            'phone'          => ['nullable', 'string', 'max:40'],
            'email'          => ['nullable', 'email', 'max:180'],
            // Causa raíz del IMC mudo (PROMPT-EJECUCION-MI-RUTINA.md, Parte
            // 2): el paso 1 nunca pedía altura. Opcional a propósito — no
            // puede bloquear un alta en el mostrador. Misma regla que
            // PerfilController::actualizar.
            'height_cm'      => ['nullable', 'integer', 'min:100', 'max:260'],
        ];

        if ($request->boolean('crear_login')) {
            $reglas += ['access_email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')]];
        }

        $datos = $request->validate($reglas);
        $plan  = Plan::findOrFail($datos['plan_id']);

        // El global scope de BelongsToGym ya aísla por sede: si el id no
        // pertenece a la activa, Member::find() simplemente no lo encuentra.
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

        $mensaje = "Matrícula registrada. Código {$socio->code}.";
        if ($credenciales) {
            $mensaje .= " Login creado — correo: <b>{$credenciales['email']}</b>, contraseña inicial: <b>{$credenciales['password']}</b> (entrégala y que la cambie en su primer ingreso).";
        }

        return redirect()
            ->route('admin.clientes.show', $socio)
            ->with('exito', $mensaje);
    }
}
