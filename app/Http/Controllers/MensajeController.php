<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
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

        $mensaje = $conversacion->messages()->create([
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

        $otro = User::query()
            ->where('gym_id', GymContext::id())
            ->where('is_active', true)
            ->findOrFail($datos['user_id']);

        abort_if($otro->id === $request->user()->id, 422, 'No puedes chatear contigo mismo.');

        $conversacion = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $otro->id))
            ->first();

        if (! $conversacion) {
            $conversacion = DB::transaction(function () use ($otro, $request) {
                $hilo = Conversation::create();
                $hilo->participants()->createMany([
                    ['user_id' => $request->user()->id],
                    ['user_id' => $otro->id],
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

        $usuarios = User::query()
            ->where('gym_id', GymContext::id())
            ->where('is_active', true)
            ->where('id', '!=', $request->user()->id)
            ->with('role')
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
