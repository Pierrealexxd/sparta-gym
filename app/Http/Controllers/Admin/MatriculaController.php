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
        if ($request->boolean('crear_acceso')) {
            $credenciales = $this->matricula->crearAcceso($socio, $datos['access_email']);
        }

        $mensaje = "Matrícula registrada. Código {$socio->code}.";
        if ($credenciales) {
            $mensaje .= " Acceso creado — correo: <b>{$credenciales['email']}</b>, contraseña inicial: <b>{$credenciales['password']}</b> (entrégala y que la cambie en su primer ingreso).";
        }

        return redirect()
            ->route('admin.clientes.show', $socio)
            ->with('exito', $mensaje);
    }
}
