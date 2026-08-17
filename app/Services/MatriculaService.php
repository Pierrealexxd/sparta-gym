<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Lógica de matrícula/renovación, antes triplicada en
 * Admin\MatriculaController, Entrenador\InscripcionController y
 * Admin\MembershipController. Matrícula = cliente nuevo (nuevaMatricula);
 * renovación = cliente existente (renovarMembresia) — nunca se mezclan.
 * El pago, si corresponde, se registra como Sale (tipo membresía), no Payment.
 */
class MatriculaService
{
    /**
     * Da de alta un cliente nuevo con su primera membresía. No hay
     * `renewed_from`: por definición no existe una membresía anterior.
     */
    public function nuevaMatricula(array $datosSocio, Plan $plan, array $datosMembresia, User $registradoPor): array
    {
        return DB::transaction(function () use ($datosSocio, $plan, $datosMembresia, $registradoPor) {
            $socio = Member::create([
                'first_name' => $datosSocio['first_name'],
                'last_name'  => $datosSocio['last_name'],
                'document'   => $datosSocio['document'] ?? null,
                'phone'      => $datosSocio['phone'] ?? null,
                'email'      => $datosSocio['email'] ?? null,
                // Opcional: si el mostrador no la tiene a mano, la matrícula
                // se completa igual (ver PROMPT-EJECUCION-MI-RUTINA.md,
                // Parte 2 — sin esto el IMC queda mudo en Mi progreso).
                'height_cm'  => $datosSocio['height_cm'] ?? null,
                'status'     => 'activo',
                'code'       => Member::generarCodigo(),
            ]);

            $membresia = $this->crearMembresia($socio, $plan, $datosMembresia, $registradoPor, renewedFrom: null);

            $venta = null;
            if ($datosMembresia['registrar_pago'] ?? false) {
                $venta = $this->registrarVenta($socio, $membresia, $plan, $datosMembresia, $registradoPor);
            }

            $this->notificarMatricula($socio, $membresia, $registradoPor, renovacion: false);

            return ['member' => $socio, 'membership' => $membresia, 'sale' => $venta];
        });
    }

    /**
     * Renueva la membresía de un cliente que ya existe. La anterior (si la
     * hay y sigue activa) queda encadenada vía `renewed_from` y pasa a vencida.
     */
    public function renovarMembresia(Member $socio, Plan $plan, array $datosMembresia, User $registradoPor): array
    {
        return DB::transaction(function () use ($socio, $plan, $datosMembresia, $registradoPor) {
            $anterior = $socio->currentMembership;

            $membresia = $this->crearMembresia($socio, $plan, $datosMembresia, $registradoPor, renewedFrom: $anterior?->id);

            $anterior?->update(['status' => 'vencida']);

            if ($socio->status !== 'activo') {
                $socio->update(['status' => 'activo']);
            }

            $venta = null;
            if ($datosMembresia['registrar_pago'] ?? true) {
                $venta = $this->registrarVenta($socio, $membresia, $plan, $datosMembresia, $registradoPor);
            }

            $this->notificarMatricula($socio, $membresia, $registradoPor, renovacion: true);

            return ['member' => $socio, 'membership' => $membresia, 'sale' => $venta];
        });
    }

    /**
     * Crea las credenciales de login para un socio que aún no tiene cuenta.
     * Es un trámite aparte de matricular/renovar a propósito — si el socio
     * ya tiene cuenta, no hace nada y devuelve null.
     */
    public function crearLogin(Member $socio, string $email): ?array
    {
        if ($socio->user_id) {
            return null;
        }

        $password = Str::password(12);

        $usuario = User::create([
            'gym_id'    => $socio->gym_id,
            'role_id'   => Role::where('slug', 'cliente')->value('id'),
            'name'      => $socio->full_name,
            'email'     => $email,
            'password'  => $password,
            'is_active' => true,
        ]);

        $socio->update(['user_id' => $usuario->id]);

        return ['email' => $usuario->email, 'password' => $password];
    }

    private function crearMembresia(Member $socio, Plan $plan, array $datos, User $registradoPor, ?int $renewedFrom): Membership
    {
        $inicio = Carbon::parse($datos['starts_at']);

        // El fin manual es opt-in: en blanco, se calcula igual que siempre
        // (inicio + duración del plan). Cubre inscripciones fuera del
        // sistema con un periodo distinto al del plan. Defensa en
        // profundidad además de la validación del controlador.
        $fin = $inicio->copy()->addDays($plan->duration_days);
        if (! empty($datos['ends_at'])) {
            $finManual = Carbon::parse($datos['ends_at']);

            throw_if($finManual->lt($inicio), ValidationException::withMessages([
                'ends_at' => 'La fecha de fin no puede ser anterior al inicio.',
            ]));

            $fin = $finManual;
        }

        return $socio->memberships()->create([
            'plan_id'      => $plan->id,
            'created_by'   => $registradoPor->id,
            'renewed_from' => $renewedFrom,
            'plan_name'    => $plan->name,
            'price'        => $plan->price,
            'discount'     => $datos['discount'] ?? 0,
            'starts_at'    => $inicio,
            'ends_at'      => $fin,
            'status'       => 'activa',
        ]);
    }

    /**
     * Matrícula/renovación hecha por alguien del mostrador o un entrenador:
     * el resto del staff de la sede se entera. Si el trámite lo hizo un
     * admin/recepción, no sobra ningún aviso (lo vieron en pantalla).
     */
    private function notificarMatricula(Member $socio, Membership $membresia, User $registradoPor, bool $renovacion): void
    {
        if (! NotificationService::enContextoWeb()) {
            return;
        }

        $servicio = app(NotificationService::class);

        $servicio->dispararA(
            $servicio->staffDeSede($socio->gym_id, $registradoPor->id),
            $renovacion ? 'matricula.renovada' : 'matricula.nueva',
            $renovacion ? 'Membresía renovada' : 'Nuevo cliente matriculado',
            $renovacion
                ? "{$socio->full_name} renovó {$membresia->plan_name} hasta el " . $membresia->ends_at->translatedFormat('d M')
                : "{$socio->full_name} se matriculó en {$membresia->plan_name}",
            'usuarios',
            'baja',
            $socio->id,
            route('admin.clientes.show', $socio),
        );
    }

    private function registrarVenta(Member $socio, Membership $membresia, Plan $plan, array $datos, User $registradoPor): Sale
    {
        return Sale::create([
            'member_id'     => $socio->id,
            'sale_type'     => 'membresia',
            'membership_id' => $membresia->id,
            'sold_by'       => $registradoPor->id,
            'number'        => Sale::siguienteNumero(),
            'subtotal'      => $plan->price,
            'discount'      => $datos['discount'] ?? 0,
            'total'         => $plan->price - ($datos['discount'] ?? 0),
            'concept'       => "Membresía {$plan->name}",
            'reference'     => $datos['reference'] ?? null,
            'method'        => $datos['method'],
            'status'        => 'completada',
            'sold_at'       => now(),
        ]);
    }
}
