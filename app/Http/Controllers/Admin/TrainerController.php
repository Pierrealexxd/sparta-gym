<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Trainer;
use App\Models\User;
use App\Support\GymContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Alta de entrenadores. Crea a la vez el User (para que pueda entrar a su
 * panel) y el Trainer (su ficha pública): son dos tablas por diseño, pero
 * desde el formulario se ven y se guardan como una sola cosa.
 */
class TrainerController extends Controller
{
    public function index(Request $request): View
    {
        $termino = $request->get('q');

        return view('admin.entrenadores.index', [
            'entrenadores' => Trainer::with('user')
                ->when($termino, fn ($q) => $q->where(function ($sub) use ($termino) {
                    $t = '%' . trim($termino) . '%';
                    $sub->whereHas('user', fn ($u) => $u->where('name', 'like', $t))
                        ->orWhere('specialty', 'like', $t);
                }))
                ->orderBy('sort_order')
                ->paginate(10)
                ->onEachSide(1)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.entrenadores.form', ['entrenador' => new Trainer()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validarDatos($request);

        $user = User::create([
            'gym_id'   => GymContext::id(),
            'role_id'  => Role::where('slug', 'entrenador')->value('id'),
            'name'     => $datos['name'],
            'email'    => $datos['email'],
            'password' => $datos['password'] ?? Str::password(12),
            'is_active'=> true,
        ]);

        Trainer::create([
            'user_id'          => $user->id,
            'specialty'        => $datos['specialty'] ?? null,
            'bio'              => $datos['bio'] ?? null,
            'years_experience' => $datos['years_experience'] ?? null,
            'is_public'        => $request->boolean('is_public', true),
            'is_active'        => true,
        ]);

        return redirect()->route('admin.entrenadores.index')->with('exito', 'Entrenador registrado.');
    }

    public function edit(Trainer $entrenador): View
    {
        return view('admin.entrenadores.form', ['entrenador' => $entrenador->load('user')]);
    }

    public function update(Request $request, Trainer $entrenador): RedirectResponse
    {
        $datos = $this->validarDatos($request, $entrenador);

        $entrenador->user->update(['name' => $datos['name'], 'email' => $datos['email']]);

        $entrenador->update([
            'specialty'        => $datos['specialty'] ?? null,
            'bio'              => $datos['bio'] ?? null,
            'years_experience' => $datos['years_experience'] ?? null,
            'is_public'        => $request->boolean('is_public', true),
        ]);

        return redirect()->route('admin.entrenadores.index')->with('exito', 'Entrenador actualizado.');
    }

    public function destroy(Trainer $entrenador): RedirectResponse
    {
        $entrenador->update(['is_active' => false]);
        $entrenador->user->update(['is_active' => false]);

        return back()->with('exito', 'Entrenador desactivado.');
    }

    private function validarDatos(Request $request, ?Trainer $entrenador = null): array
    {
        return $request->validate([
            'name'             => ['required', 'string', 'max:120'],
            'email'            => ['required', 'email', Rule::unique('users', 'email')->ignore($entrenador?->user_id)],
            'password'         => ['nullable', 'string', 'min:8'],
            'specialty'        => ['nullable', 'string', 'max:120'],
            'bio'              => ['nullable', 'string', 'max:600'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
        ]);
    }
}
