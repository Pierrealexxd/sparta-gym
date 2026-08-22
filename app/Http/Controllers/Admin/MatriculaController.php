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
 * Matrícula guiada: cliente + plan + productos de consumo inmediato + pago,
 * todo en un mismo comprobante (MatriculaService).
 *
 * Paso 1 del modal admite elegir un cliente que ya existe (ver
 * MemberController::buscar, el selector) para no volver a teclear sus
 * datos — ahí se salta Member::create y se usa
 * MatriculaService::renovarMembresia. Sin elegir a nadie, sigue siendo
 * alta de cliente nuevo (nuevaMatricula).
 *
 * El formulario vive como modal en la pestaña Registros de /admin/ventas
 * (ver SaleController::index, que le pasa $planes y $productos) — acá solo
 * queda el store.
 */
class MatriculaController extends Controller
{
    public function __construct(private readonly MatriculaService $matricula) {}

    public function store(Request $request): RedirectResponse
    {
        // Mismo freno que TrainerController/ProductController/PlanController:
        // un cliente nuevo (Member) exige gym_id y no puede quedar sin sede
        // porque el admin estaba viendo "Todas las sedes" cuando lo registró.
        if ($bloqueo = $this->exigirSedeEspecifica('un cliente')) {
            return $bloqueo;
        }

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
            // Consumo inmediato en el mismo ticket: el stock y la sede se
            // validan dentro de MatriculaService (lockForUpdate), acá solo
            // se comprueba la forma.
            'productos'               => ['nullable', 'array'],
            'productos.*.product_id'  => ['required', 'integer'],
            'productos.*.quantity'    => ['required', 'integer', 'min:1'],
        ];

        if ($request->boolean('crear_login')) {
            $reglas += ['access_email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')]];
        }

        $datos = $request->validate($reglas);
        $plan  = Plan::findOrFail($datos['plan_id']);

        // Capa pasiva anti-duplicados: el modal ya avisa en vivo (ver
        // MemberController::verificar), pero si el operador ignoró el aviso,
        // escribió a mano tras descartarlo o llegó sin JS, acá se frena igual.
        // Solo aplica al alta nueva: con member_id presente es una renovación
        // del cliente elegido y por definición no crea nadie duplicado.
        if (blank($datos['member_id'] ?? null)) {
            $duplicado = Member::query()
                ->where(function ($q) use ($datos) {
                    $documento = trim((string) ($datos['document'] ?? ''));
                    if ($documento !== '') {
                        $q->orWhere('document', $documento);
                    }

                    $nombres   = Member::normalizarNombre($datos['first_name'] ?? '');
                    $apellidos = Member::normalizarNombre($datos['last_name'] ?? '');
                    if ($nombres !== '' && $apellidos !== '') {
                        $q->orWhere(fn ($x) => $x
                            ->whereRaw('LOWER(TRIM(first_name)) = ?', [$nombres])
                            ->whereRaw('LOWER(TRIM(last_name)) = ?', [$apellidos]));
                    }
                })
                ->first();

            if ($duplicado) {
                throw ValidationException::withMessages([
                    'first_name' => "Este cliente ya está registrado: {$duplicado->full_name}"
                        . " (código {$duplicado->code})."
                        . ' Úsalo desde «¿Ya es cliente? Buscarlo» para renovarle el plan en vez de crear otro registro.',
                ]);
            }
        }

        // El global scope de BelongsToGym ya aísla por sede: si el id no
        // pertenece a la activa, Member::find() simplemente no lo encuentra.
        $existente = $datos['member_id'] ?? null ? Member::find($datos['member_id']) : null;
        if (($datos['member_id'] ?? null) && ! $existente) {
            throw ValidationException::withMessages(['member_id' => 'Ese cliente no está disponible.']);
        }

        if ($existente) {
            $resultado = $this->matricula->renovarMembresia($existente, $plan, $datos, $request->user(), $datos['productos'] ?? []);
        } else {
            $resultado = $this->matricula->nuevaMatricula($datos, $plan, $datos, $request->user(), $datos['productos'] ?? []);
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

        // De vuelta a donde se inició el trámite: la pestaña Registros de
        // ventas. El mensaje lleva el código para que el operador ubique al
        // cliente sin salir de la caja.
        return redirect()
            ->route('admin.ventas.index', ['tipo' => 'membresia'])
            ->with('exito', $mensaje);
    }
}
