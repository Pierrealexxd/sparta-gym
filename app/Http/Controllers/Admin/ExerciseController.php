<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\RoutineExercise;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Biblioteca de ejercicios de la landing ("La biblioteca · Aprende antes de
 * cargar"). Sin gym_id: un ejercicio nuevo queda en la biblioteca global,
 * visible desde cualquier sede (ver Exercise::scopeDisponibles) — no hace
 * falta que cada gimnasio cargue los suyos por separado.
 */
class ExerciseController extends Controller
{
    public const CATEGORIAS = [
        'fuerza'    => 'Fuerza',
        'cardio'    => 'Cardio',
        'movilidad' => 'Movilidad',
        'core'      => 'Core',
        'funcional' => 'Funcional',
    ];

    public const NIVELES = [
        'principiante' => 'Principiante',
        'intermedio'   => 'Intermedio',
        'avanzado'     => 'Avanzado',
    ];

    public function index(Request $request): View
    {
        $termino = $request->get('q');

        return view('admin.contenido.ejercicios.index', [
            'ejercicios' => Exercise::query()
                ->when($termino, fn ($q) => $q->where(function ($sub) use ($termino) {
                    $t = '%' . trim($termino) . '%';
                    $sub->where('name', 'like', $t)
                        ->orWhere('category', 'like', $t)
                        ->orWhere('equipment', 'like', $t);
                }))
                ->orderBy('category')
                ->orderBy('name')
                ->paginate(12)
                ->onEachSide(1)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.contenido.ejercicios.form', ['ejercicio' => new Exercise()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validarDatos($request);
        $datos['slug'] = $this->generarSlug($datos['name']);

        if ($ruta = $this->guardarImagen($request)) {
            $datos['image_path'] = $ruta;
        }

        Exercise::create($datos);

        return redirect()->route('admin.ejercicios.index')->with('exito', 'Ejercicio creado.');
    }

    public function edit(Exercise $ejercicio): View
    {
        return view('admin.contenido.ejercicios.form', ['ejercicio' => $ejercicio]);
    }

    public function update(Request $request, Exercise $ejercicio): RedirectResponse
    {
        $datos = $this->validarDatos($request);

        if ($ruta = $this->guardarImagen($request)) {
            if ($ejercicio->image_path) {
                Storage::disk('public')->delete($ejercicio->image_path);
            }
            $datos['image_path'] = $ruta;
        }

        $ejercicio->update($datos);

        return redirect()->route('admin.ejercicios.index')->with('exito', 'Ejercicio actualizado.');
    }

    /**
     * Borra el ejercicio definitivamente. Un ejercicio asignado a rutinas no
     * se puede borrar: `routine_exercises` tiene cascada y eliminaría de
     * golpe ejercicios de planes de clientes reales — despublícalo en su
     * lugar. Ocultar un ejercicio publicado pasó a ocultar().
     */
    public function destroy(Exercise $ejercicio): RedirectResponse
    {
        if (RoutineExercise::where('exercise_id', $ejercicio->id)->exists()) {
            return back()->with('error', 'No se puede eliminar un ejercicio que está en rutinas asignadas; desactívalo en su lugar.');
        }

        $ejercicio->delete();

        return back()->with('exito', 'Ejercicio eliminado.');
    }

    /** Oculta un ejercicio publicado — la biblioteca deja de mostrarlo, sin borrarlo. */
    public function ocultar(Exercise $ejercicio): RedirectResponse
    {
        $ejercicio->update(['is_active' => false]);

        return back()->with('exito', 'Ejercicio despublicado.');
    }

    /** Publica (o república) un ejercicio oculto — complemento de ocultar(). */
    public function publicar(Exercise $ejercicio): RedirectResponse
    {
        $ejercicio->update(['is_active' => true]);

        return back()->with('exito', 'Ejercicio publicado.');
    }

    private function validarDatos(Request $request): array
    {
        $datos = $request->validate([
            'name'            => ['required', 'string', 'max:120'],
            'category'        => ['required', 'in:' . implode(',', array_keys(self::CATEGORIAS))],
            'level'           => ['required', 'in:' . implode(',', array_keys(self::NIVELES))],
            'equipment'       => ['nullable', 'string', 'max:120'],
            'description'     => ['nullable', 'string', 'max:1000'],
            'muscle_groups'   => ['nullable', 'string'],
            'common_mistakes' => ['nullable', 'string', 'max:500'],
            'tips'            => ['nullable', 'string', 'max:500'],
            'video_url'       => ['nullable', 'url', 'max:255'],
            'imagen'          => ['nullable', 'image', 'max:3072'],
        ]);

        unset($datos['imagen']);

        // Igual que "features" en planes: una línea, un grupo muscular.
        $datos['muscle_groups'] = collect(explode("\n", $datos['muscle_groups'] ?? ''))
            ->map(fn ($l) => trim($l))
            ->filter()
            ->values()
            ->all();

        $datos['is_active'] = $request->boolean('is_active', true);

        return $datos;
    }

    private function guardarImagen(Request $request): ?string
    {
        if (! $request->hasFile('imagen')) {
            return null;
        }

        return $request->file('imagen')->store('ejercicios', 'public');
    }

    private function generarSlug(string $name): string
    {
        $slugBase = Str::slug($name);
        $slug     = $slugBase;
        $sufijo   = 1;

        while (Exercise::where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . (++$sufijo);
        }

        return $slug;
    }
}
