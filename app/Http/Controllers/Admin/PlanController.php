<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    public function index(Request $request): View
    {
        $termino = $request->get('q');

        return view('admin.planes.index', ['planes' => Plan::withCount('memberships')
            ->when($termino, fn ($q) => $q->where('name', 'like', '%' . trim($termino) . '%'))
            ->orderBy('sort_order')
            ->paginate(10)
            ->onEachSide(1)
            ->withQueryString()]);
    }

    public function create(): View
    {
        return view('admin.planes.form', ['plan' => new Plan()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validarDatos($request);
        $datos['slug'] = Str::slug($datos['name']);

        Plan::create($datos);

        return redirect()->route('admin.planes.index')->with('exito', 'Plan creado.');
    }

    public function edit(Plan $plan): View
    {
        return view('admin.planes.form', ['plan' => $plan]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $datos = $this->validarDatos($request);
        $datos['slug'] = Str::slug($datos['name']);

        $plan->update($datos);

        return redirect()->route('admin.planes.index')->with('exito', 'Plan actualizado.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $plan->update(['is_active' => false]);

        return back()->with('exito', 'Plan desactivado.');
    }

    private function validarDatos(Request $request): array
    {
        $datos = $request->validate([
            'name'          => ['required', 'string', 'max:80'],
            'tagline'       => ['nullable', 'string', 'max:160'],
            'price'         => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'features'      => ['nullable', 'string'],
            // Formato exacto que siempre manda <input type="color">. Server-side
            // a propósito y estricto: accent_color se imprime en planes.blade.php
            // con @style(), que (como @class) no escapa HTML — un valor suelto
            // ahí podría romper el atributo. El regex es la única barrera real.
            'accent_color'  => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_featured'   => ['nullable', 'boolean'],
            'is_public'     => ['nullable', 'boolean'],
        ]);

        // Un textarea, una línea por beneficio: más natural en un formulario
        // que gestionar un array dinámico de inputs para algo tan simple.
        $datos['features'] = collect(explode("\n", $datos['features'] ?? ''))
            ->map(fn ($l) => trim($l))
            ->filter()
            ->values()
            ->all();

        $datos['is_featured'] = $request->boolean('is_featured');
        $datos['is_public'] = $request->boolean('is_public', true);
        $datos['is_active'] = true;

        return $datos;
    }
}
