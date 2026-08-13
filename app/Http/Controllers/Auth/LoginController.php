<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Marca;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function mostrar(): View
    {
        return view('auth.login', ['logoUrl' => Marca::logoPublico()]);
    }

    public function entrar(Request $request): RedirectResponse
    {
        $credenciales = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required'    => 'Escribe tu correo.',
            'email.email'       => 'Ese correo no parece válido.',
            'password.required' => 'Escribe tu contraseña.',
        ]);

        // is_active en las credenciales: una cuenta desactivada no entra,
        // y el mensaje es el mismo que el de contraseña incorrecta para no
        // revelar qué correos existen en el sistema.
        if (! Auth::attempt($credenciales + ['is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Esas credenciales no coinciden con ninguna cuenta.',
            ]);
        }

        $request->session()->regenerate();   // corta cualquier fijación de sesión

        $usuario = $request->user();
        $usuario->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route($usuario->rutaDeInicio()))
            ->with('bienvenida', "¡Bienvenido de nuevo, {$usuario->name}!");
    }

    public function salir(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    }
}
