<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Alta de sedes nuevas. Antes de esto, la única forma de crear un
 * gimnasio era por tinker — ver docs/multi-sede.md, Parte A.5.
 */
class GymController extends Controller
{
    public function index(Request $request): View
    {
        $termino = $request->get('q');

        return view('admin.sedes.index', ['sedes' => Gym::query()
            ->when($termino, fn ($q) => $q->where(function ($sub) use ($termino) {
                $t = '%' . trim($termino) . '%';
                $sub->where('name', 'like', $t)
                    ->orWhere('city', 'like', $t);
            }))
            ->orderBy('name')
            ->paginate(10)
            ->onEachSide(1)
            ->withQueryString()]);
    }

    public function create(): View
    {
        return view('admin.sedes.form', ['sede' => new Gym()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validarDatos($request);
        $datos['slug'] = $this->generarSlug($datos['name']);

        if ($ruta = $this->guardarLogo($request)) {
            $datos['logo_path'] = $ruta;
        }

        Gym::create($datos);

        return redirect()->route('admin.sedes.index')->with('exito', 'Sede creada.');
    }

    public function edit(Gym $sede): View
    {
        return view('admin.sedes.form', ['sede' => $sede]);
    }

    public function update(Request $request, Gym $sede): RedirectResponse
    {
        // El slug NO se regenera al editar, a propósito: es el identificador
        // que usa GymContext (config('sparta.gym_slug')) para resolver qué
        // sede sirve la web pública. Si renombrar "Sparta Gym" a "Sparta
        // Gym Las Lomas" le cambiara el slug por debajo, la landing dejaría
        // de encontrar el gimnasio y respondería 503 — ya pasó una vez.
        $datos = $this->validarDatos($request);

        if ($ruta = $this->guardarLogo($request)) {
            if ($sede->logo_path) {
                Storage::disk('public')->delete($sede->logo_path);
            }
            $datos['logo_path'] = $ruta;
        }

        $sede->update($datos);

        return redirect()->route('admin.sedes.index')->with('exito', 'Sede actualizada.');
    }

    public function destroy(Gym $sede): RedirectResponse
    {
        $sede->update(['is_active' => false]);

        return back()->with('exito', 'Sede desactivada.');
    }

    private function validarDatos(Request $request): array
    {
        $datos = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'tagline'     => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'address'     => ['nullable', 'string', 'max:200'],
            'city'        => ['nullable', 'string', 'max:80'],
            'phone'       => ['nullable', 'string', 'max:40'],
            'email'       => ['nullable', 'email', 'max:180'],
            'logo'        => ['nullable', 'image', 'max:2048'],

            'schedule'          => ['nullable', 'array'],
            'schedule.*.dia'    => ['nullable', 'string', 'max:20'],
            'schedule.*.abre'   => ['nullable', 'string', 'max:10'],
            'schedule.*.cierra' => ['nullable', 'string', 'max:10'],

            'socials.instagram' => ['nullable', 'string', 'max:255'],
            'socials.facebook'  => ['nullable', 'string', 'max:255'],
            'socials.tiktok'    => ['nullable', 'string', 'max:255'],
            'socials.youtube'   => ['nullable', 'string', 'max:255'],
        ]);

        unset($datos['logo']);

        // Filas vacías (dejadas en blanco al quitar una franja) no se guardan.
        $datos['schedule'] = collect($datos['schedule'] ?? [])
            ->filter(fn ($f) => filled($f['dia'] ?? null))
            ->values()
            ->all();

        $datos['socials'] = array_filter($datos['socials'] ?? []);

        $datos['is_active'] = $request->boolean('is_active', true);

        return $datos;
    }

    private function guardarLogo(Request $request): ?string
    {
        if (! $request->hasFile('logo')) {
            return null;
        }

        return $request->file('logo')->store('sedes', 'public');
    }

    /** Sólo se llama al crear — ver la nota en update() sobre por qué el slug es estable. */
    private function generarSlug(string $name): string
    {
        $slugBase = Str::slug($name);
        $slug     = $slugBase;
        $sufijo   = 1;

        // Evita colisión de slug entre sedes sin depender de que el dueño
        // recuerde no repetir nombres: "Sparta Norte" dos veces produce
        // sparta-norte y sparta-norte-2, no un error de validación.
        while (Gym::where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . (++$sufijo);
        }

        return $slug;
    }
}
