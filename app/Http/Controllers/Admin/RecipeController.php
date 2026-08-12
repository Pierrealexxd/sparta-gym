<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Biblioteca de recetas peruanas (Fase 3 de PLAN_NUTRICION_PROGRESO.md).
 * Sin gym_id: una receta nueva queda en la biblioteca global, visible desde
 * cualquier sede (ver Recipe::scopeDisponibles) — mismo patrón que
 * ExerciseController. Gestión solo de administración, a diferencia de la
 * biblioteca de ejercicios que también gestiona el entrenador.
 */
class RecipeController extends Controller
{
    /** Los cuatro tipos de porción que soporta toda receta. */
    public const TIPOS_PORCION = [
        'palma'  => 'Palma (proteína)',
        'puno'   => 'Puño (verduras)',
        'cuenco' => 'Cuenco (carbohidratos)',
        'pulgar' => 'Pulgar (grasas)',
    ];

    public function index(Request $request): View
    {
        return view('admin.contenido.recetas.index', [
            'recetas' => Recipe::query()
                ->buscar($request->get('q'))
                ->with('portions')
                ->orderBy('name')
                ->paginate(12)
                ->onEachSide(1)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.contenido.recetas.form', ['receta' => new Recipe()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validarDatos($request);
        $datos['slug'] = $this->generarSlug($datos['name']);

        $receta = Recipe::create($datos);
        $this->guardarPorciones($request, $receta);

        return redirect()->route('admin.recetas.index')->with('exito', 'Receta creada.');
    }

    public function edit(Recipe $receta): View
    {
        $receta->load('portions');

        return view('admin.contenido.recetas.form', ['receta' => $receta]);
    }

    public function update(Request $request, Recipe $receta): RedirectResponse
    {
        $datos = $this->validarDatos($request);

        $receta->update($datos);
        $this->guardarPorciones($request, $receta);

        return redirect()->route('admin.recetas.index')->with('exito', 'Receta actualizada.');
    }

    /** Borra la receta definitivamente (sus porciones van con ella, cascada). Ocultar pasó a ocultar(). */
    public function destroy(Recipe $receta): RedirectResponse
    {
        $receta->delete();

        return back()->with('exito', 'Receta eliminada.');
    }

    /** Oculta una receta publicada — la biblioteca deja de mostrarla, sin borrarla. */
    public function ocultar(Recipe $receta): RedirectResponse
    {
        $receta->update(['is_active' => false]);

        return back()->with('exito', 'Receta despublicada.');
    }

    /** Publica (o república) una receta oculta — complemento de ocultar(). */
    public function publicar(Recipe $receta): RedirectResponse
    {
        $receta->update(['is_active' => true]);

        return back()->with('exito', 'Receta publicada.');
    }

    private function validarDatos(Request $request): array
    {
        $datos = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'description'   => ['nullable', 'string', 'max:1000'],
            'ingredients'   => ['nullable', 'string', 'max:2000'],
            'steps'         => ['nullable', 'string', 'max:2000'],
            'prep_minutes'  => ['nullable', 'integer', 'min:1', 'max:600'],
            'servings'      => ['nullable', 'integer', 'min:1', 'max:50'],
            'tags'          => ['nullable', 'string'],
        ]);

        // Igual que "muscle_groups" en ejercicios: una línea, una etiqueta.
        $datos['tags'] = collect(explode("\n", $datos['tags'] ?? ''))
            ->map(fn ($l) => trim($l))
            ->filter()
            ->values()
            ->all();

        $datos['is_active'] = $request->boolean('is_active', true);

        return $datos;
    }

    private function guardarPorciones(Request $request, Recipe $receta): void
    {
        $porciones = $request->input('porciones', []);

        foreach (array_keys(self::TIPOS_PORCION) as $tipo) {
            $receta->portions()->updateOrCreate(
                ['recipe_id' => $receta->id, 'portion_type' => $tipo],
                [
                    'count'     => (int) ($porciones[$tipo]['count'] ?? 0),
                    'food_name' => $porciones[$tipo]['food_name'] ?? null,
                ],
            );
        }
    }

    private function generarSlug(string $name): string
    {
        $slugBase = Str::slug($name);
        $slug     = $slugBase;
        $sufijo   = 1;

        while (Recipe::where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . (++$sufijo);
        }

        return $slug;
    }
}
