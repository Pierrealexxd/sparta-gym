<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SedeActivaController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['sede_id' => ['required', 'string']]);

        $seleccion = $request->input('sede_id');

        if ($seleccion === 'todas') {
            if ($request->user()->tienePermiso('sedes.ver-todas')) {
                $request->session()->put('sede_activa_id', 'todas');
            }

            return back();
        }

        // Solo se guarda un id que de verdad sea una sede del usuario: el
        // middleware EstablecerSedeActiva ya amortigua ids inválidos, pero
        // no hace falta ni siquiera probar ese camino con basura en sesión.
        $sede = $request->user()->sedesDisponibles()->firstWhere('id', $seleccion);

        if ($sede) {
            $request->session()->put('sede_activa_id', $sede->id);
        }

        return back();
    }
}
