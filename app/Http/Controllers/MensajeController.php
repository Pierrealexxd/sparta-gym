<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\GymContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mensajería interna del gimnasio, compartida por los tres paneles.
 *
 * Chats 1:1 entre usuarios de la misma sede. El front hace polling sobre
 * los endpoints JSON (sin websockets ni dependencias nuevas): listar hilos,
 * abrir un hilo, enviar y marcar leído. Cada contacto ofrece además un
 * atajo a WhatsApp por si la vía interna no basta.
 */
class MensajeController extends Controller
{
    public function index(Request $request): View
    {
        $yo = $request->user();

        return view('mensajes.index', [
            'conversaciones' => $this->serializarLista($this->listaConversaciones($yo), $yo),
            'abrir'          => (int) $request->query('con'),
        ]);
    }

    public function lista(Request $request): JsonResponse
    {
        $yo = $request->user();

        return response()->json([
            'conversaciones' => $this->serializarLista($this->listaConversaciones($yo), $yo),
        ]);
    }

    /** Mensajes de un hilo a partir de un id, marcando los míos como leídos. */
    public function listaMensajes(Request $request, Conversation $conversacion): JsonResponse
    {
        $yo = $request->user();
        abort_unless($conversacion->esParticipante($yo->id), 403);

        $desde = (int) $request->query('desde', 0);

        $mensajes = $conversacion->messages()
            ->where('id', '>', $desde)
            ->orderBy('id')
            ->get();

        $this->marcarLeidas($conversacion, $yo);

        // La campanita también marca como leídas sus filas de esta conversación:
        // abrir el hilo es leerlo (ver plan-notificaciones-toast.md).
        app(NotificationService::class)->marcarLeidasDeTipo($yo, 'mensaje.nuevo', $conversacion->id);

        return response()->json([
            'ultimo_id' => $mensajes->last()?->id ?? $desde,
            'mensajes'  => $mensajes->map(fn (Message $m) => $this->serializarMensaje($m, $yo)),
            'contacto'  => $this->serializarContacto($conversacion, $yo),
        ]);
    }

    public function enviar(Request $request, Conversation $conversacion): JsonResponse
    {
        $yo = $request->user();
        abort_unless($conversacion->esParticipante($yo->id), 403);

        $datos = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        // Mismo bug de fondo que conversar(): messages.gym_id tampoco admite
        // NULL y dependía de BelongsToGym + GymContext::id() (null con el
        // admin en "Todas las sedes"). El hilo ya tiene un gym_id resuelto
        // desde que se creó — un mensaje nuevo hereda el de su conversación,
        // no el del contexto ambiguo de quien lo escribe.
        $mensaje = $conversacion->messages()->create([
            'gym_id'    => $conversacion->gym_id,
            'sender_id' => $yo->id,
            'body'      => trim($datos['body']),
        ]);
        $conversacion->touch();
        $this->marcarLeidas($conversacion, $yo);

        return response()->json([
            'mensaje' => $this->serializarMensaje($mensaje, $yo),
        ]);
    }

    /** Crea (o reutiliza) el hilo con otro usuario y devuelve su id. */
    public function conversar(Request $request): JsonResponse
    {
        $datos = $request->validate(['user_id' => ['required', 'exists:users,id']]);

        // Corrección de bug real (producción, agosto 2026): un admin viendo
        // "Todas las sedes" tiene GymContext::id() === null a propósito (ver
        // EstablecerSedeActiva) — filtrar acá por ese null convertía el
        // where en un "gym_id IS NULL" (Laravel interpreta where(col, null)
        // como whereNull) y de ahí, o no encontraba al destinatario (404) o
        // encontraba solo cuentas con gym_id nulo por error de datos. El
        // directorio ya listó a este usuario sin ese filtro roto (ver
        // directorio() más abajo) — acá debe poder encontrarlo igual.
        $otro = User::query()
            ->when(GymContext::id(), fn ($q) => $q->where('gym_id', GymContext::id()))
            ->where('is_active', true)
            ->findOrFail($datos['user_id']);

        abort_if($otro->id === $request->user()->id, 422, 'No puedes chatear contigo mismo.');

        // Regla de la mensajería: un cliente no puede escribirle a otro
        // cliente (solo a staff — admin/recepción/entrenador). El resto de
        // roles no tiene restricción entre sí. Se valida acá (no solo en el
        // directorio) para que no se pueda forzar por fuera de la UI.
        abort_if($request->user()->esCliente() && $otro->esCliente(), 403, 'Los clientes no pueden escribirse entre sí.');

        $conversacion = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $otro->id))
            ->first();

        if (! $conversacion) {
            $conversacion = DB::transaction(function () use ($otro, $request) {
                // Conversation::create() dependía de que BelongsToGym rellenara
                // gym_id desde GymContext::id() — con el admin en "Todas las
                // sedes" ese valor es null a propósito, y como la columna no
                // admite NULL, el INSERT tiraba
                // "SQLSTATE[HY000]: 1364 Field 'gym_id' doesn't have a default
                // value" (500 real visto en producción). Se resuelve un
                // gym_id explícito: el del destinatario primero (con quién
                // se conversa manda), y solo si tampoco lo tiene, el del
                // gimnasio por defecto de la config.
                $gymId = $otro->gym_id
                    ?? $request->user()->gym_id
                    ?? GymContext::id()
                    ?? \App\Models\Gym::where('slug', config('sparta.gym_slug'))->value('id');

                $hilo = Conversation::create(['gym_id' => $gymId]);

                // Mismo problema, una tabla más abajo: conversation_participants
                // también exige gym_id y también dependía del mismo
                // BelongsToGym + GymContext::id() (null en "Todas las sedes").
                // Se usa el gym_id que ya quedó resuelto arriba en $hilo, no
                // GymContext, para no repetir el mismo bug fila por fila.
                $hilo->participants()->createMany([
                    ['user_id' => $request->user()->id, 'gym_id' => $hilo->gym_id],
                    ['user_id' => $otro->id, 'gym_id' => $hilo->gym_id],
                ]);

                return $hilo;
            });
        }

        return response()->json(['id' => $conversacion->id]);
    }

    /** Directorio de la sede: busca usuarios por rol y nombre para iniciar un chat. */
    public function directorio(Request $request): JsonResponse
    {
        $rol = $request->query('rol', '');
        $termino = trim((string) $request->query('q'));

        // Mismo bug que en conversar(): con GymContext::id() null (admin en
        // "Todas las sedes"), un where('gym_id', null) sin querer se lee
        // como "gym_id IS NULL" — de ahí que el filtro "Admin" saliera
        // vacío ("nadie con ese filtro") aunque sí hubiera admins/recepción
        // reales, y que "Todos" mostrara únicamente cuentas con gym_id nulo
        // por error de datos en vez de todo el mundo. Con GymContext::id()
        // presente (sede específica elegida) el filtro sigue igual que antes.
        $usuarios = User::query()
            ->when(GymContext::id(), fn ($q) => $q->where('gym_id', GymContext::id()))
            ->where('is_active', true)
            ->where('id', '!=', $request->user()->id)
            ->with('role')
            // Un cliente no ve a otros clientes en el directorio (solo puede
            // escribirle a staff) — mismo criterio que valida conversar().
            ->when($request->user()->esCliente(), fn ($q) => $q->whereHas('role', fn ($rq) => $rq->where('slug', '!=', 'cliente')))
            ->when($rol !== '', function ($q) use ($rol) {
                $slugs = match ($rol) {
                    'admin' => ['admin', 'recepcion'],
                    default => [$rol],
                };
                $q->whereHas('role', fn ($rq) => $rq->whereIn('slug', $slugs));
            })
            ->when($termino !== '', function ($q) use ($termino) {
                $t = '%' . $termino . '%';
                $q->where('name', 'like', $t);
            })
            ->orderBy('name')
            ->limit(50)
            ->get();

        return response()->json([
            'usuarios' => $usuarios->map(fn (User $u) => $this->serializarUsuario($u)),
        ]);
    }

    public function noLeidas(Request $request): JsonResponse
    {
        return response()->json([
            'total' => Conversation::noLeidasTotales($request->user()->id, $request->user()->esAdmin()),
        ]);
    }

    /* ---------------------------------------------------------- */
    /* Internos                                                  */
    /* ---------------------------------------------------------- */

    /**
     * El admin gestiona varias sedes a la vez: sus hilos aparecen sin
     * importar cuál tenga activa en el selector, para que un mensaje de
     * otra sucursal no quede "escondido" por estar mirando el dashboard
     * de otra — mismo criterio que Conversation::noLeidasTotales().
     * Recepción/entrenador/cliente siguen viendo solo lo de su propia sede.
     */
    private function listaConversaciones(User $yo): Collection
    {
        return Conversation::query()
            ->when($yo->esAdmin(), fn ($q) => $q->sinFiltroDeGimnasio())
            ->whereHas('participants', fn ($q) => $q->where('user_id', $yo->id))
            ->with(['participants.user', 'ultimoMensaje'])
            ->withCount(['messages as no_leidas' => fn ($q) => $q->noLeidas($yo->id)])
            ->orderByDesc('updated_at')
            ->get();
    }

    private function serializarLista(Collection $conversaciones, User $yo): array
    {
        // El admin ve hilos de varias sedes en un mismo listado (ver
        // listaConversaciones) — sin esta etiqueta, un mensaje de Cruceta se
        // mezclaría sin aviso entre los de la sede que tiene activa.
        $mostrarSede = $yo->esAdmin();

        return $conversaciones->map(function (Conversation $c) use ($yo, $mostrarSede) {
            $otro = $c->participants->firstWhere('user_id', '!=', $yo->id)?->user;
            $ultimo = $c->ultimoMensaje;

            return [
                'id'        => $c->id,
                'nombre'    => $otro?->name ?? 'Sin contacto',
                'iniciales' => $otro?->iniciales ?? '?',
                'avatar'    => $otro?->avatar_path ? asset('storage/' . $otro->avatar_path) : null,
                'rol'       => $otro?->role?->name,
                'sede'      => $mostrarSede ? $otro?->gym?->name : null,
                'ultimo'    => $ultimo?->body,
                'hora'      => $ultimo
                    ? ($ultimo->created_at->isToday()
                        ? $ultimo->created_at->format('H:i')
                        : $ultimo->created_at->translatedFormat('d M'))
                    : '',
                'no_leidas' => (int) $c->no_leidas,
            ];
        })->values()->all();
    }

    private function serializarMensaje(Message $m, User $yo): array
    {
        return [
            'id'     => $m->id,
            'cuerpo' => $m->body,
            'mio'    => $m->sender_id === $yo->id,
            'hora'   => $m->created_at->isToday()
                ? $m->created_at->format('H:i')
                : $m->created_at->translatedFormat('d M · H:i'),
            'leido'  => $m->read_at !== null,
        ];
    }

    private function serializarContacto(Conversation $conversacion, User $yo): array
    {
        $otro = $conversacion->participants->firstWhere('user_id', '!=', $yo->id)?->user;

        return $this->serializarUsuario($otro);
    }

    private function serializarUsuario(?User $usuario): array
    {
        if (! $usuario) {
            return ['id' => null, 'nombre' => 'Sin contacto', 'iniciales' => '?', 'avatar' => null, 'rol' => null, 'phone' => null, 'whatsapp' => null];
        }

        return [
            'id'        => $usuario->id,
            'nombre'    => $usuario->name,
            'iniciales' => $usuario->iniciales,
            'avatar'    => $usuario->avatar_path ? asset('storage/' . $usuario->avatar_path) : null,
            'rol'       => $usuario->role?->name,
            'phone'     => $usuario->phone,
            'whatsapp'  => $usuario->phone ? 'https://wa.me/' . preg_replace('/\D+/', '', $usuario->phone) : null,
        ];
    }

    private function marcarLeidas(Conversation $conversacion, User $yo): void
    {
        $conversacion->messages()
            ->where('sender_id', '!=', $yo->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
