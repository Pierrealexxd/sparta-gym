<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Role;
use App\Models\Trainer;
use App\Models\User;
use App\Support\GymContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Cuentas de acceso: dar de alta recepción, clientes y entrenadores,
 * asignarles rol y activarlos/desactivarlos. Solo el administrador llega
 * hasta acá (permiso 'usuarios.gestionar', que ningún otro rol tiene).
 *
 * Cambiar el rol de alguien es sensible — determina qué panel ve y qué
 * puede hacer — así que update() renueva la contraseña cada vez que el rol
 * cambia (misma lógica que dar de alta) y, si el nuevo rol es entrenador,
 * le crea la ficha profesional (Trainer) si no la tenía, para que el panel
 * de entrenador le funcione de inmediato. La contraseña se muestra una
 * sola vez en el mensaje de éxito: el proyecto no tiene correo para
 * invitaciones, así que la entrega es en persona, en pantalla.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $termino = $request->get('q');

        $usuarios = User::with(['role', 'gym', 'member'])
            ->when(GymContext::id(), fn ($q, $gymId) => $q->where('gym_id', $gymId))
            ->when($termino, fn ($q) => $q->where(function ($sub) use ($termino) {
                $t = '%' . trim($termino) . '%';
                $sub->where('name', 'like', $t)
                    ->orWhere('email', 'like', $t)
                    ->orWhereHas('role', fn ($r) => $r->where('name', 'like', $t));
            }))
            ->orderBy('name')
            ->paginate(10)
            ->onEachSide(1)
            ->withQueryString();

        return view('admin.usuarios.index', [
            'usuarios'     => $usuarios,
            // El index también abre el editor en modal, así que necesita las
            // mismas piezas que el formulario (roles, sedes del creador).
            'roles'        => Role::orderBy('level', 'desc')->get(),
            'sedesCreador' => auth()->user()->sedesDisponibles(),
        ]);
    }

    public function create(): View
    {
        return $this->formulario(new User());
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validarDatos($request);
        $password = Str::password(12);

        $usuario = User::create([
            'gym_id'   => $datos['gym_id'] ?? GymContext::id(),
            'role_id'  => $datos['role_id'],
            'name'     => $datos['name'],
            'email'    => $datos['email'],
            'password' => $password,
            'is_active'=> $request->boolean('is_active', true),
        ]);

        if ($datos['role_id'] === Role::where('slug', 'cliente')->value('id')) {
            Member::findOrFail($datos['member_id'])->update(['user_id' => $usuario->id]);
        }

        if ($datos['role_id'] === Role::where('slug', 'entrenador')->value('id')) {
            Trainer::create(['user_id' => $usuario->id, 'is_public' => false, 'is_active' => true]);
        }

        return redirect()
            ->route('admin.usuarios.index')
            ->with('exito', "Cuenta creada. Contraseña inicial: <b>{$password}</b> — entrégala y que la cambie en su primer ingreso.");
    }

    public function edit(User $usuario): View
    {
        return $this->formulario($usuario);
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $datos = $this->validarDatos($request, $usuario);
        $activa = $request->boolean('is_active', true);

        // Último admin activo: no se puede degradar de rol ni desactivar
        // desde aquí — el mismo candado que destroy(), para que editar no
        // sea una puerta trasera para dejar el gimnasio sin administrador.
        if ($usuario->tieneRol('admin') && ((int) $datos['role_id'] !== $usuario->role_id || ! $activa)) {
            $otrosAdminsActivos = User::where('role_id', $usuario->role_id)
                ->where('id', '!=', $usuario->id)
                ->where('is_active', true)
                ->exists();

            if (! $otrosAdminsActivos) {
                return back()->with('error', 'No puedes degradar ni desactivar al último administrador activo.')->withInput();
            }
        }

        $rolCliente = Role::where('slug', 'cliente')->value('id');
        $eraCliente = $usuario->role_id === $rolCliente;
        $esCliente  = (int) $datos['role_id'] === $rolCliente;

        $rolEntrenador = Role::where('slug', 'entrenador')->value('id');
        $rolCambio     = (int) $datos['role_id'] !== $usuario->role_id;
        $pasaAEntrenador = $rolCambio && (int) $datos['role_id'] === $rolEntrenador;
        $dejaEntrenador  = $rolCambio && $usuario->role_id === $rolEntrenador;

        // El rol decide a qué panel entra y qué puede hacer — cambiarlo es
        // tan sensible como dar de alta una cuenta nueva, así que se renueva
        // la contraseña cada vez (nunca en un guardado que no toca el rol).
        $passwordNueva = $rolCambio ? Str::password(12) : null;

        $usuario->update([
            'gym_id'   => $datos['gym_id'] ?? $usuario->gym_id,
            'role_id'  => $datos['role_id'],
            'name'     => $datos['name'],
            'email'    => $datos['email'],
            'is_active'=> $activa,
            ...($passwordNueva ? ['password' => $passwordNueva] : []),
        ]);

        if ($esCliente) {
            // Desengancha el miembro anterior si el cambio de rol trae uno
            // distinto: un usuario "cliente" solo puede apuntar a un socio.
            Member::sinFiltroDeGimnasio()
                ->where('user_id', $usuario->id)
                ->where('id', '!=', $datos['member_id'])
                ->update(['user_id' => null]);

            Member::sinFiltroDeGimnasio()->where('id', $datos['member_id'])->update(['user_id' => $usuario->id]);
        } elseif ($eraCliente) {
            // Dejó de ser cliente: el socio queda sin cuenta, no se borra.
            Member::sinFiltroDeGimnasio()->where('user_id', $usuario->id)->update(['user_id' => null]);
        }

        if ($pasaAEntrenador) {
            // Pasa a entrenador: si ya tuvo una ficha antes (soft-deleted),
            // se reactiva en vez de duplicar; si no, se crea vacía — el
            // panel de entrenador ya le funciona, el admin completa
            // specialty/bio después desde Entrenadores.
            $ficha = Trainer::withTrashed()->where('user_id', $usuario->id)->first();
            if ($ficha) {
                $ficha->restore();
                $ficha->update(['is_active' => true]);
            } else {
                Trainer::create(['user_id' => $usuario->id, 'is_public' => false, 'is_active' => true]);
            }
        } elseif ($dejaEntrenador) {
            // Deja de ser entrenador: la ficha se apaga, no se borra —
            // conserva el historial de rutinas/asignaciones que ya tenía.
            Trainer::where('user_id', $usuario->id)->update(['is_active' => false]);
        }

        $mensaje = 'Cuenta actualizada.';
        if ($passwordNueva) {
            $mensaje .= " El rol cambió, así que se generó una contraseña nueva: <b>{$passwordNueva}</b> (entrégala y que la cambie en su primer ingreso).";
        }

        return redirect()->route('admin.usuarios.index')->with('exito', $mensaje);
    }

    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        if ($usuario->tieneRol('admin') && User::where('role_id', $usuario->role_id)->where('is_active', true)->count() <= 1) {
            return back()->with('error', 'No puedes desactivar al último administrador activo.');
        }

        $usuario->update(['is_active' => false]);

        return back()->with('exito', "Cuenta de {$usuario->name} desactivada.");
    }

    /** Buscador del caso "cliente": clientes de la sede activa sin cuenta todavía. */
    public function buscarClientes(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q'));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $clientes = Member::sinFiltroDeGimnasio()
            ->where('gym_id', GymContext::id())
            ->whereNull('user_id')
            ->buscar($q)
            ->take(8)
            ->get();

        return response()->json($clientes->map(fn (Member $m) => [
            'id'        => $m->id,
            'full_name' => $m->full_name,
            'code'      => $m->code,
            'document'  => $m->document,
        ]));
    }

    private function formulario(User $usuario): View
    {
        return view('admin.usuarios.form', [
            'usuario'     => $usuario,
            'roles'       => Role::orderBy('level', 'desc')->get(),
            'sedesCreador'=> auth()->user()->sedesDisponibles(),
        ]);
    }

    private function validarDatos(Request $request, ?User $usuario = null): array
    {
        $esCliente = (int) $request->get('role_id') === (int) Role::where('slug', 'cliente')->value('id');

        $reglas = [
            'name'    => ['required', 'string', 'max:120'],
            'email'   => ['required', 'email', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'role_id' => ['required', Rule::in(Role::pluck('id')->all())],
            'is_active' => ['nullable', 'boolean'],
        ];

        if (auth()->user()->sedesDisponibles()->count() > 1 && GymContext::id() === null) {
            $reglas['gym_id'] = ['required', Rule::in(auth()->user()->sedesDisponibles()->pluck('id')->all())];
        }

        if ($esCliente) {
            $reglas['member_id'] = ['required', 'exists:members,id'];
        }

        $datos = $request->validate($reglas);

        if ($esCliente) {
            $socio = Member::sinFiltroDeGimnasio()->where('id', $datos['member_id'])->firstOrFail();

            // Único por socio: puede seguir apuntando a SU propio usuario al
            // editar, pero no "robarle" la cuenta a otro socio ya enlazado.
            abort_if(
                ($socio->user_id !== null && $socio->user_id !== $usuario?->id)
                    || $socio->gym_id !== (int) ($datos['gym_id'] ?? $usuario?->gym_id ?? GymContext::id()),
                422,
                'Ese socio ya tiene cuenta o pertenece a otra sede.'
            );
        }

        return $datos;
    }
}
