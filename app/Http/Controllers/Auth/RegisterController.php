<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Support\GymContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Alta pública: cualquiera puede crearse su propia cuenta de socio desde la
 * web. A propósito solo da rol 'cliente' — no hay forma de que este
 * formulario cree un admin o un entrenador, esas cuentas se dan de alta
 * desde dentro del panel (Admin → Usuarios / Entrenadores).
 *
 * Crea Member + User en la misma transacción: sin esto, quien se registra
 * quedaría con login pero sin ficha de socio (el mismo hueco que resolvimos
 * a mano para las matrículas antiguas).
 */
class RegisterController extends Controller
{
    public function mostrar(): View
    {
        return view('auth.registro', [
            // Solo se pide si hay más de una: con una sola sede, elegir es
            // ruido — se sigue asignando sola como antes.
            'gyms' => Gym::orderBy('name')->get(),
        ]);
    }

    public function registrar(Request $request): RedirectResponse
    {
        $gyms = Gym::orderBy('name')->get();

        $reglas = [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name'  => ['required', 'string', 'max:120'],
            'email'      => ['required', 'email', 'max:180', Rule::unique('users', 'email')],
            'phone'      => ['nullable', 'string', 'max:40'],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
        ];

        if ($gyms->count() > 1) {
            $reglas['gym_id'] = ['required', Rule::in($gyms->pluck('id')->all())];
        }

        $datos = $request->validate($reglas, [
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'gym_id.required' => 'Elige de qué sede eres.',
        ]);

        // Con una sola sede, sigue resolviéndose sola (GymContext); con
        // varias, la eligió el propio usuario en el formulario — antes
        // siempre caía en la sede por defecto de .env sin importar cuál
        // era la real.
        $gymId = $gyms->count() > 1 ? (int) $datos['gym_id'] : GymContext::id();
        abort_if($gymId === null, 503, 'No hay ningún gimnasio configurado.');

        $usuario = DB::transaction(function () use ($datos, $gymId) {
            $socio = Member::create([
                'gym_id'     => $gymId,
                'first_name' => $datos['first_name'],
                'last_name'  => $datos['last_name'],
                'email'      => $datos['email'],
                'phone'      => $datos['phone'] ?? null,
                'status'     => 'activo',
                'code'       => Member::generarCodigo(),
            ]);

            $usuario = User::create([
                'gym_id'   => $gymId,
                'role_id'  => Role::where('slug', 'cliente')->value('id'),
                'name'     => $socio->full_name,
                'email'    => $datos['email'],
                'password' => $datos['password'],
                'is_active'=> true,
            ]);

            $socio->update(['user_id' => $usuario->id]);

            return $usuario;
        });

        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()->route($usuario->rutaDeInicio())
            ->with('bienvenida', '¡Bienvenido a Sparta Gym! Tu cuenta ya está lista.');
    }
}
