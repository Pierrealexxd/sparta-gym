<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * CRUD de programas de la landing (Parte 1 de PLAN-PROGRAMAS.md). Mismo
 * patrón que FaqController: listado con modal único de crear/editar
 * (ver admin/contenido/programas/index.blade.php), no páginas separadas.
 */
class ProgramController extends Controller
{
    public function index(Request $request): View
    {
        $termino = $request->get('q');

        return view('admin.contenido.programas.index', [
            'programas' => Program::query()
                ->when($termino, fn ($q) => $q->where(function ($sub) use ($termino) {
                    $t = '%' . trim($termino) . '%';
                    $sub->where('name', 'like', $t)->orWhere('tagline', 'like', $t);
                }))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(10)
                ->onEachSide(1)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validarDatos($request);
        $datos['slug'] = $this->generarSlug($datos['name']);

        Program::create($datos);

        return redirect()->route('admin.programas.index')->with('exito', 'Programa creado.');
    }

    public function update(Request $request, Program $programa): RedirectResponse
    {
        $datos = $this->validarDatos($request);

        // El slug solo se regenera si cambió el nombre — así no se rompen
        // enlaces ya compartidos (mismo criterio que ExerciseController).
        if ($datos['name'] !== $programa->name) {
            $datos['slug'] = $this->generarSlug($datos['name'], $programa->id);
        }

        $programa->update($datos);

        return redirect()->route('admin.programas.index')->with('exito', 'Programa actualizado.');
    }

    public function destroy(Program $programa): RedirectResponse
    {
        $programa->delete();

        return back()->with('exito', 'Programa eliminado.');
    }

    /** Oculta un programa publicado — la landing deja de mostrarlo, sin borrarlo. */
    public function ocultar(Program $programa): RedirectResponse
    {
        $programa->update(['is_active' => false]);

        return back()->with('exito', 'Programa despublicado.');
    }

    /** Publica (o república) un programa oculto — complemento de ocultar(). */
    public function publicar(Program $programa): RedirectResponse
    {
        $programa->update(['is_active' => true]);

        return back()->with('exito', 'Programa publicado.');
    }

    private function validarDatos(Request $request): array
    {
        $datos = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'tagline'        => ['nullable', 'string', 'max:200'],
            'objective'      => ['required', 'in:ganar_masa,perder_grasa,fuerza,resistencia,salud,otro'],
            'description'    => ['required', 'string', 'max:4000'],
            'highlights'     => ['nullable', 'string'],
            'icon'           => ['nullable', 'string', 'max:40'],
            'accent_color'   => ['nullable', 'string', 'max:9'],
            'duration_weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'difficulty'     => ['required', 'in:principiante,intermedio,avanzado'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
            // FASE 3 de PLAN-GUIAS-EJERCICIO.md: recomendaciones que ve el
            // cliente en "Mi Rutina" — mismo patrón de "uno por línea" que
            // highlights, convertidas a array JSON abajo.
            'nutrition_tips'   => ['nullable', 'string'],
            'recovery_tips'    => ['nullable', 'string'],
            'hydration_tips'   => ['nullable', 'string'],
            'supplements_tips' => ['nullable', 'string'],
        ]);

        // Un rasgo/tip por línea, igual que "features" en Plan y
        // "muscle_groups" en Exercise — así el admin no batalla con un
        // editor de arrays JSON.
        $lineasAArray = fn (?string $texto) => collect(explode("\n", $texto ?? ''))
            ->map(fn ($l) => trim($l))
            ->filter()
            ->values()
            ->all();

        $datos['highlights']        = $lineasAArray($datos['highlights'] ?? '');
        $datos['nutrition_tips']    = $lineasAArray($datos['nutrition_tips'] ?? '') ?: null;
        $datos['recovery_tips']     = $lineasAArray($datos['recovery_tips'] ?? '') ?: null;
        $datos['hydration_tips']    = $lineasAArray($datos['hydration_tips'] ?? '') ?: null;
        $datos['supplements_tips']  = $lineasAArray($datos['supplements_tips'] ?? '') ?: null;

        $datos['sort_order'] = $datos['sort_order'] ?? 0;
        $datos['is_active'] = $request->boolean('is_active', true);
        $datos['is_public'] = $request->boolean('is_public', true);

        return $datos;
    }

    private function generarSlug(string $name, ?int $ignorarId = null): string
    {
        $slugBase = Str::slug($name);
        $slug     = $slugBase;
        $sufijo   = 1;

        while (Program::where('slug', $slug)->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))->exists()) {
            $slug = $slugBase . '-' . (++$sufijo);
        }

        return $slug;
    }
}
