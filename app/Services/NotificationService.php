<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberMeasurement;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Servicio central de notificaciones (campanita + toasts).
 *
 * Un solo punto de entrada para crear, leer y limpiar notificaciones; los
 * eventos de cada módulo lo llaman en vez de escribir filas a mano. Reglas:
 *
 * - Dedupe: si ya hay una fila SIN LEER del mismo (usuario, tipo, sujeto),
 *   se refresca (título/cuerpo/prioridad) en lugar de duplicar. Como el id
 *   no cambia, el cursor del polling no la vuelve a entregar → sin re-toast.
 * - El actor no recibe su propia notificación: su feedback es el toast flash.
 * - Vigencia: toda consulta filtra por `vigentes()` (< 24 h) y el comando
 *   `notificaciones:limpiar` borra lo vencido.
 * - Multi-gimnasio: `BelongsToGym` aísla por sede activa; el admin lee todas
 *   sus sedes con `sinFiltroDeGimnasio()` (mismo criterio que la mensajería).
 */
class NotificationService
{
    /**
     * ¿Vale la pena notificar en este contexto? Los seeds y comandos de
     * consola no notifican (no hay a quién avisar y solo ensucian la tabla);
     * los tests sí, para poder ejercitar los emisores (PHPUnit también corre
     * en consola, de ahí runningUnitTests()).
     */
    public static function enContextoWeb(): bool
    {
        return ! app()->runningInConsole() || app()->runningUnitTests();
    }

    public function disparar(
        User $destinatario,
        string $type,
        string $title,
        string $body,
        string $icon = 'campana',
        string $prioridad = 'media',
        ?int $subjectId = null,
        ?string $actionUrl = null,
        array $data = [],
    ): Notification {
        $valores = [
            'title'      => $title,
            'body'       => $body,
            'icon'       => $icon,
            'priority'   => $prioridad,
            'action_url' => $actionUrl,
            'data'       => $data ?: null,
        ];

        $existente = Notification::query()
            ->where('user_id', $destinatario->id)
            ->where('type', $type)
            ->where('subject_id', $subjectId)
            ->whereNull('read_at')
            ->latest('id')
            ->first();

        if ($existente) {
            $existente->update($valores);

            return $existente;
        }

        return Notification::create([
            'user_id'    => $destinatario->id,
            'type'       => $type,
            'subject_id' => $subjectId,
            ...$valores,
        ]);
    }

    /** Dispara a varios destinatarios (cada uno con su propio dedupe). */
    public function dispararA(iterable $destinatarios, string $type, string $title, string $body, string $icon = 'campana', string $prioridad = 'media', ?int $subjectId = null, ?string $actionUrl = null, array $data = []): void
    {
        foreach ($destinatarios as $destinatario) {
            if ($destinatario instanceof User) {
                $this->disparar($destinatario, $type, $title, $body, $icon, $prioridad, $subjectId, $actionUrl, $data);
            }
        }
    }

    /**
     * Staff (admin + recepción) de una sede, para avisos operativos. Con
     * $gymId nulo (p. ej. admin mirando "todas las sedes") lista a todo el
     * staff activo. $excepto excluye al actor del aviso.
     */
    public function staffDeSede(?int $gymId, ?int $excepto = null): Collection
    {
        return User::query()
            ->when($gymId, fn ($q) => $q->where('gym_id', $gymId))
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'recepcion']))
            ->when($excepto, fn ($q) => $q->where('id', '!=', $excepto))
            ->get();
    }

    public function marcarLeida(Notification $notificacion, User $usuario): void
    {
        abort_unless($notificacion->user_id === $usuario->id, 403);

        if ($notificacion->read_at === null) {
            $notificacion->update(['read_at' => now()]);
        }
    }

    public function marcarTodasLeidas(User $usuario): int
    {
        return $this->base($usuario)->noLeidas()->update(['read_at' => now()]);
    }

    public function noLeidas(User $usuario): int
    {
        return $this->base($usuario)->noLeidas()->count();
    }

    /** Lista del cajón lateral: solo vigentes, más recientes primero. */
    public function lista(User $usuario, int $limite = 50): array
    {
        return $this->base($usuario)
            ->with('gym')
            ->orderByDesc('created_at')
            ->limit($limite)
            ->get()
            ->map(fn (Notification $n) => $this->serializar($n, $usuario))
            ->values()
            ->all();
    }

    /**
     * Filas nuevas desde un cursor, para los toasts en tiempo real.
     * Devuelve [toasts, ultimoId]. `ultimo_id` avanza con todas las filas
     * (leídas incluidas) para que el cursor no se quede clavado si el
     * usuario marcó todo en otra pestaña; solo se toastean las no leídas.
     */
    public function nuevas(User $usuario, int $desde): array
    {
        $consulta = $this->base($usuario)->where('id', '>', $desde);

        $ultimoId = (int) (clone $consulta)->max('id');

        $toasts = $consulta->noLeidas()->orderBy('id')->get()
            ->map(fn (Notification $n) => $this->serializar($n, $usuario))
            ->values()
            ->all();

        return [$toasts, $ultimoId];
    }

    /** Marca leídas las filas de un tipo+sujeto (p. ej. al abrir un chat). */
    public function marcarLeidasDeTipo(User $usuario, string $type, ?int $subjectId): void
    {
        $this->base($usuario)
            ->where('type', $type)
            ->where('subject_id', $subjectId)
            ->noLeidas()
            ->update(['read_at' => now()]);
    }

    /** Medida registrada por el staff → el socio (si tiene cuenta) se entera. */
    public function notificarMedidaStaff(MemberMeasurement $medida): void
    {
        if (! static::enContextoWeb()) {
            return;
        }

        $socio = $medida->member;
        if (! $socio?->user_id) {
            return;
        }

        $this->disparar(
            $socio->user,
            'medida.registrada',
            'Medida registrada',
            'Tu peso del ' . $medida->measured_at->translatedFormat('d M') . ' fue registrado por el staff.',
            'objetivo',
            'baja',
            $medida->id,
            route('cliente.progreso'),
        );
    }

    /** El socio registró su propia medida → su entrenador actual (si tiene). */
    public function notificarMedidaCliente(MemberMeasurement $medida): void
    {
        if (! static::enContextoWeb()) {
            return;
        }

        $socio  = $medida->member;
        $entrenador = $socio?->currentAssignment?->trainer?->user;

        if (! $entrenador || ! $entrenador->is_active) {
            return;
        }

        $this->disparar(
            $entrenador,
            'medida.registrada',
            'Medida registrada',
            $socio->full_name . ' registró su peso (' . $medida->weight_kg . ' kg).',
            'objetivo',
            'baja',
            $medida->id,
            route('entrenador.clientes.show', $socio),
        );
    }

    /** Borra lo que ya pasó la vigencia. Devuelve cuántas filas eliminó. */
    public function limpiarVencidas(): int
    {
        return Notification::sinFiltroDeGimnasio()
            ->where('created_at', '<', now()->subHours((int) config('sparta.notificaciones.vigencia_horas', 24)))
            ->delete();
    }

    /* ---------------------------------------------------------- */

    /** Consulta base del usuario: suyas, vigentes y con la sede correcta. */
    private function base(User $usuario): Builder
    {
        return Notification::query()
            ->when($usuario->esAdmin(), fn ($q) => $q->sinFiltroDeGimnasio())
            ->where('user_id', $usuario->id)
            ->vigentes();
    }

    private function serializar(Notification $n, User $usuario): array
    {
        return [
            'id'       => $n->id,
            'type'     => $n->type,
            'title'    => $n->title,
            'body'     => $n->body,
            'icon'     => $n->icon,
            'priority' => $n->priority,
            'leida'    => $n->read_at !== null,
            'url'      => $n->action_url,
            'hora'     => $n->created_at->isToday()
                ? $n->created_at->diffForHumans()
                : $n->created_at->translatedFormat('d M'),
            // El admin ve notificaciones de todas sus sedes en un mismo
            // cajón (mismo criterio que la mensajería); la etiqueta de sede
            // solo le aporta a él.
            'sede'     => $usuario->esAdmin() ? $n->gym?->name : null,
        ];
    }
}
