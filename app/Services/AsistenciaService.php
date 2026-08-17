<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceEditRequest;
use App\Models\Member;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Regla de negocio de las asistencias en un solo lugar, compartida por el
 * torno (admin/recepción) y el registro del entrenador. Los controladores
 * buscan al socio y comprueban permisos; aquí vive el "puede entrar o no".
 *
 * Las fechas siempre son las del servidor (now()): ningún formulario manda
 * horas al registrar — las correcciones sí las mandan, y pasan por
 * aprobación antes de aplicarse.
 */
class AsistenciaService
{
    public function registrarEntrada(Member $member, User $registrador, string $method = 'manual'): Attendance
    {
        if ($member->status !== 'activo') {
            throw ValidationException::withMessages(['asistencia' => 'El socio no está activo.']);
        }

        if (! $member->is_up_to_date) {
            throw ValidationException::withMessages(['asistencia' => "{$member->full_name} no tiene una membresía vigente."]);
        }

        if ($member->attendances()->dentro()->exists()) {
            throw ValidationException::withMessages(['asistencia' => "{$member->full_name} ya tiene una asistencia abierta."]);
        }

        // La sede la rellena BelongsToGym al crear: el registrador nunca manda gym_id.
        return $member->attendances()->create([
            'checked_in_at' => now(),
            'method'        => $method,
            'registered_by' => $registrador->id,
        ]);
    }

    public function marcarSalida(Attendance $attendance): Attendance
    {
        if ($attendance->checked_out_at === null) {
            $attendance->update(['checked_out_at' => now()]);
        }

        return $attendance;
    }

    /**
     * Toggle de entrada/salida laboral del staff (hoy solo entrenadores).
     * La misma regla para la marcación manual del panel y la por QR: aquí
     * vive la decisión "entrar o salir", nunca en el frontend.
     *
     * $gymId: en el flujo manual se deja null y `BelongsToGym` rellena el
     * gimnasio del contexto; en el flujo QR la sucursal la define el token,
     * así que se fuerza explícita y la consulta de la abierta atraviesa el
     * aislamiento a propósito.
     *
     * $metodo: 'manual' (panel), 'qr' (escaneo) o 'geo' (geolocalización del
     * navegador) — solo para auditoría/reportes, la regla de entrada/salida
     * es la misma para las tres vías. $lat/$lng solo se usan con 'geo' y
     * quedan NULL en las otras dos.
     *
     * Devuelve ['tipo' => 'entrada'|'salida', 'marcacion' => StaffAttendance].
     */
    public function marcarStaff(
        User $usuario,
        string $turno,
        ?int $gymId = null,
        bool $porQr = false,
        ?float $lat = null,
        ?float $lng = null,
    ): array {
        $metodo = $porQr ? 'qr' : ($lat !== null || $lng !== null ? 'geo' : 'manual');

        // Anti-doble-escaneo: dos lecturas seguidas del mismo QR no deben
        // abrir y cerrar un turno de un segundo. No aplica al clic manual.
        if ($porQr) {
            $ultima = StaffAttendance::sinFiltroDeGimnasio()
                ->where('user_id', $usuario->id)
                ->latest('clocked_in_at')
                ->first();

            if ($ultima && $ultima->clocked_in_at->greaterThan(now()->subSeconds(30))) {
                throw ValidationException::withMessages(['asistencia' => 'Marcación demasiado reciente. Esperá unos segundos.']);
            }
        }

        $consulta = StaffAttendance::where('user_id', $usuario->id)->dentro();
        if ($gymId) {
            $consulta->sinFiltroDeGimnasio()->where('gym_id', $gymId);
        }

        $abierta = $consulta->first();

        // Solo se cierra la abierta de hoy. Una abierta de otro día no se
        // toca: escribir la hora de hoy en la fila de ayer sería corregir
        // sin pasar por aprobación; queda para el flujo de corrección.
        if ($abierta && $abierta->clocked_in_at->isToday()) {
            // La ubicación de salida es la última conocida: si entró por QR
            // o manual y ahora cierra por geo, igual vale la pena guardarla.
            $abierta->update([
                'clocked_out_at' => now(),
                'location_lat'   => $lat ?? $abierta->location_lat,
                'location_lng'   => $lng ?? $abierta->location_lng,
            ]);

            return ['tipo' => 'salida', 'marcacion' => $abierta];
        }

        $valido = Validator::make(['turno' => $turno], [
            'turno' => ['required', 'in:manana,tarde,doble'],
        ])->validate();

        $marcacion = StaffAttendance::create([
            'user_id'       => $usuario->id,
            'gym_id'        => $gymId,
            'clocked_in_at' => now(),
            'turno'         => $valido['turno'],
            'method'        => $metodo,
            'location_lat'  => $lat,
            'location_lng'  => $lng,
        ]);

        return ['tipo' => 'entrada', 'marcacion' => $marcacion];
    }

    public function solicitarCorreccion(Attendance|StaffAttendance $registro, User $solicitante, array $datos): AttendanceEditRequest
    {
        $columna = $registro instanceof Attendance ? 'attendance_id' : 'staff_attendance_id';

        if (AttendanceEditRequest::where($columna, $registro->id)->pendientes()->exists()) {
            throw ValidationException::withMessages(['asistencia' => 'Ya hay una solicitud pendiente para este registro.']);
        }

        $valido = Validator::make($datos, [
            'checked_in_at'  => ['required', 'date'],
            'checked_out_at' => ['nullable', 'date', 'after:checked_in_at'],
            'reason'         => ['nullable', 'string', 'max:255'],
        ])->validate();

        $solicitud = AttendanceEditRequest::create([
            $columna         => $registro->id,
            'requested_by'   => $solicitante->id,
            'tipo'           => 'edicion',
            'checked_in_at'  => $valido['checked_in_at'],
            'checked_out_at' => $valido['checked_out_at'] ?? null,
            'reason'         => $valido['reason'] ?? null,
            'status'         => 'pendiente',
        ]);

        $this->notificarSolicitud($solicitud);

        return $solicitud;
    }

    /**
     * Borrar una marcación propia ya no se aplica directo — queda pendiente
     * como una solicitud más, para que el admin la vea (y la pueda auditar)
     * por la misma campanita que las correcciones. Antes se borraba al
     * toque porque "es obvio que pasó, el registro ya no está"; se decidió
     * que igual conviene que quede un aviso, como con editar.
     */
    public function solicitarEliminacion(Attendance|StaffAttendance $registro, User $solicitante, ?string $motivo = null): AttendanceEditRequest
    {
        $columna = $registro instanceof Attendance ? 'attendance_id' : 'staff_attendance_id';

        if (AttendanceEditRequest::where($columna, $registro->id)->pendientes()->exists()) {
            throw ValidationException::withMessages(['asistencia' => 'Ya hay una solicitud pendiente para este registro.']);
        }

        $solicitud = AttendanceEditRequest::create([
            $columna       => $registro->id,
            'requested_by' => $solicitante->id,
            'tipo'         => 'eliminacion',
            'reason'       => $motivo,
            'status'       => 'pendiente',
        ]);

        $this->notificarSolicitud($solicitud);

        return $solicitud;
    }

    /**
     * Aplicar la solicitud aprobada al registro real; nunca se edita/borra
     * directo. 'edicion' pisa la hora con la propuesta; 'eliminacion' borra
     * el registro — mismo objetivo, distinto efecto.
     */
    public function aprobar(AttendanceEditRequest $solicitud, User $revisor): void
    {
        abort_if($solicitud->status !== 'pendiente', 422, 'Esta solicitud ya fue revisada.');

        $objetivo = $solicitud->objetivo;

        if ($solicitud->es_eliminacion) {
            $objetivo?->delete();
        } else {
            $entrada = $objetivo instanceof Attendance ? 'checked_in_at' : 'clocked_in_at';
            $salida  = $objetivo instanceof Attendance ? 'checked_out_at' : 'clocked_out_at';

            $objetivo->update([
                $entrada => $solicitud->checked_in_at,
                $salida  => $solicitud->checked_out_at,
            ]);
        }

        $solicitud->update([
            'status'      => 'aprobada',
            'reviewed_by' => $revisor->id,
            'reviewed_at' => now(),
        ]);

        $this->notificarResolucion($solicitud, $revisor, 'aprobada');
    }

    public function rechazar(AttendanceEditRequest $solicitud, User $revisor): void
    {
        abort_if($solicitud->status !== 'pendiente', 422, 'Esta solicitud ya fue revisada.');

        $solicitud->update([
            'status'      => 'rechazada',
            'reviewed_by' => $revisor->id,
            'reviewed_at' => now(),
        ]);

        $this->notificarResolucion($solicitud, $revisor, 'rechazada');
    }

    /** Solicitud pendiente → campanita del staff que la aprueba (admin). */
    private function notificarSolicitud(AttendanceEditRequest $solicitud): void
    {
        if (! NotificationService::enContextoWeb()) {
            return;
        }

        $servicio = app(NotificationService::class);

        $servicio->dispararA(
            $servicio->staffDeSede($solicitud->gym_id),
            'asistencia.solicitud',
            'Corrección de asistencia',
            ($solicitud->requestedBy?->name ?? 'El staff')
                . ' pide ' . ($solicitud->es_eliminacion ? 'eliminar' : 'corregir')
                . ' un registro de asistencia',
            'entrada',
            'alta',
            $solicitud->id,
            route('admin.asistencia.solicitudes.index'),
        );
    }

    /** Resultado de la revisión → el solicitante (normalmente un entrenador). */
    private function notificarResolucion(AttendanceEditRequest $solicitud, User $revisor, string $resultado): void
    {
        if (! NotificationService::enContextoWeb()) {
            return;
        }

        $solicitante = $solicitud->requestedBy;

        if (! $solicitante || $solicitante->id === $revisor->id || ! $solicitante->is_active) {
            return;
        }

        // El solicitante suele ser un entrenador; si no, se aterriza en su
        // panel de inicio para no construir una URL a la que no tiene acceso.
        $destino = $solicitante->esEntrenador()
            ? route('entrenador.asistencia.mi-marcacion')
            : route($solicitante->rutaDeInicio());

        app(NotificationService::class)->disparar(
            $solicitante,
            'asistencia.resuelta',
            $resultado === 'aprobada' ? 'Solicitud aprobada' : 'Solicitud rechazada',
            'Tu solicitud de corrección de asistencia fue ' . $resultado . '.',
            $resultado === 'aprobada' ? 'check' : 'cerrar',
            'media',
            $solicitud->id,
            $destino,
        );
    }
}
