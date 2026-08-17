<?php

namespace App\Http\Controllers;

use App\Support\GymContext;
use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    /**
     * Corta en seco un alta que crearía un registro sin sede cuando el admin
     * está viendo "Todas las sedes" (GymContext::id() === null a propósito,
     * ver EstablecerSedeActiva — necesario para que los reportes agregados
     * funcionen). Sin este freno, el alta seguía "de milagro": el registro
     * quedaba con gym_id nulo si la columna lo admitía, o el INSERT tiraba
     * un 500 crudo si no (bug real visto en producción con mensajería y con
     * un entrenador dado de alta así — ver commits de agosto 2026).
     *
     * Se llama al inicio de un store(): si devuelve algo distinto de null,
     * el controller debe retornarlo tal cual (corta el flujo con un aviso
     * amigable en vez de una página de error en blanco).
     */
    protected function exigirSedeEspecifica(string $recurso): ?RedirectResponse
    {
        if (GymContext::id() !== null) {
            return null;
        }

        return back()->withInput()->with(
            'error',
            "Estás viendo \"Todas las sedes\". Para crear {$recurso} cambia primero a una sede específica en el menú lateral (📍 arriba del todo)."
        );
    }
}
