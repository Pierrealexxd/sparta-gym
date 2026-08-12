<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Perfil de la propia cuenta, compartido por los tres paneles.
 *
 * Gestiona lo que pertenece a la identidad de acceso —nombre, correo,
 * teléfono, contraseña, foto— y, para el cliente, sus datos personales
 * del expediente (nacimiento, género, altura, emergencia) porque el panel
 * de cliente es de sólo lectura salvo aquí. La ficha de socio completa
 * sigue siendo competencia de recepción.
 */
class PerfilController extends Controller
{
    public function mostrar(): View
    {
        return view('perfil.index', [
            'usuario' => auth()->user()->load(['member', 'trainer']),
        ]);
    }

    public function actualizar(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        $reglas = [
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($usuario->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'avatar' => ['nullable', 'image', 'max:3072'],
        ];

        // El cliente nace en dos tablas (user + member): su nombre vive en el
        // expediente como Nombres/Apellidos y se refleja en user.name. El resto
        // de roles sólo tiene la identidad de acceso, con un nombre completo.
        if ($usuario->member) {
            $reglas['first_name'] = ['required', 'string', 'max:80'];
            $reglas['last_name']  = ['required', 'string', 'max:120'];
            $reglas['birth_date'] = ['nullable', 'date', 'before_or_equal:today'];
            $reglas['gender']     = ['nullable', Rule::in(['M', 'F', 'O'])];
            $reglas['height_cm']  = ['nullable', 'integer', 'min:100', 'max:260'];
            $reglas['emergency_contact'] = ['nullable', 'string', 'max:120'];
            $reglas['emergency_phone']   = ['nullable', 'string', 'max:40'];
        } else {
            $reglas['name'] = ['required', 'string', 'max:120'];
        }

        $datos = $request->validate($reglas);

        DB::transaction(function () use ($request, $usuario, $datos) {
            if ($usuario->member) {
                $usuario->member->update([
                    'first_name'        => $datos['first_name'],
                    'last_name'         => $datos['last_name'],
                    'email'             => $datos['email'],
                    'phone'             => $datos['phone'] ?? null,
                    'birth_date'        => $datos['birth_date'] ?? null,
                    'gender'            => $datos['gender'] ?? null,
                    'height_cm'         => $datos['height_cm'] ?? null,
                    'emergency_contact' => $datos['emergency_contact'] ?? null,
                    'emergency_phone'   => $datos['emergency_phone'] ?? null,
                ]);
                $usuario->name = $usuario->member->full_name;
            } else {
                $usuario->name = $datos['name'];
            }

            $usuario->email = $datos['email'];
            $usuario->phone = $datos['phone'] ?? null;

            if ($request->hasFile('avatar')) {
                if ($usuario->avatar_path) {
                    Storage::disk('public')->delete($usuario->avatar_path);
                }
                $usuario->avatar_path = $request->file('avatar')->store('avatares', 'public');
            }

            $usuario->save();
        });

        return redirect()->route('perfil')->with('exito', 'Perfil actualizado.');
    }

    public function cambiarPassword(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'password_actual' => ['required', 'string'],
            'password'        => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $usuario = $request->user();

        if (! Hash::check($datos['password_actual'], $usuario->password)) {
            return back()->withErrors(['password_actual' => 'La contraseña actual no coincide.']);
        }

        $usuario->update(['password' => $datos['password']]);
        $request->session()->regenerate();

        return back()->with('exito', 'Contraseña actualizada.');
    }
}
