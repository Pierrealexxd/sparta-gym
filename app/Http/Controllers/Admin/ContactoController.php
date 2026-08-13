<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\GymContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ficha "Contacto" del módulo Contenido web. Edita el gimnasio activo — la
 * misma fila que sirve la sección de contacto de la landing — como un
 * formulario de una sola instancia, no como un CRUD.
 */
class ContactoController extends Controller
{
    public function editar(): View
    {
        $gym = GymContext::current();
        abort_unless($gym, 404);

        return view('admin.contenido.contacto.form', ['gym' => $gym]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $gym = GymContext::current();
        abort_unless($gym, 404);

        $datos = $this->validarDatos($request);

        // La cabecera de la sección (eyebrow/título/lead) vive en el JSON de
        // settings; el resto de campos son columnas propias del gimnasio.
        $settings = $gym->settings ?? [];
        $settings['contacto'] = $datos['contacto'] ?? [];
        unset($datos['contacto']);

        $gym->update([...$datos, 'settings' => $settings]);

        return redirect()->route('admin.contenido.contacto')->with('exito', 'Contacto actualizado.');
    }

    private function validarDatos(Request $request): array
    {
        $datos = $request->validate([
            'contacto.eyebrow' => ['nullable', 'string', 'max:40'],
            'contacto.titulo'  => ['nullable', 'string', 'max:120'],
            'contacto.lead'    => ['nullable', 'string', 'max:300'],

            'address'   => ['nullable', 'string', 'max:200'],
            'city'      => ['nullable', 'string', 'max:80'],
            'phone'     => ['nullable', 'string', 'max:40'],
            'email'     => ['nullable', 'email', 'max:180'],
            'whatsapp'  => ['nullable', 'string', 'max:40'],

            'schedule'          => ['nullable', 'array'],
            'schedule.*.dia'    => ['nullable', 'string', 'max:20'],
            'schedule.*.abre'   => ['nullable', 'string', 'max:10'],
            'schedule.*.cierra' => ['nullable', 'string', 'max:10'],

            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        // Filas vacías (dejadas en blanco al quitar una franja) no se guardan.
        $datos['schedule'] = collect($datos['schedule'] ?? [])
            ->filter(fn ($f) => filled($f['dia'] ?? null))
            ->values()
            ->all();

        return $datos;
    }
}
