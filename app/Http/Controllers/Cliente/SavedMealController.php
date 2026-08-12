<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\SavedMeal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Mis platos habituales" (Fase 4 de PLAN_NUTRICION_PROGRESO.md): guardar
 * una comida como plantilla y volver a registrarla con un tap. No toca
 * meal_logs directamente al guardar — usar() es la que aplica el plato al
 * diario de hoy, con la misma regla updateOrCreate que ProgressController.
 */
class SavedMealController extends Controller
{
    private const TIPOS_COMIDA = ['desayuno', 'almuerzo', 'cena', 'merienda'];

    /** Se guarda desde el mismo formulario de "Hoy comiste" (botón con formaction). */
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'meal_type' => ['required', Rule::in(self::TIPOS_COMIDA)],
            'nombre_plato' => ['required', 'string', 'max:60'],
            'palma'     => ['nullable', 'integer', 'min:0', 'max:20'],
            'puno'      => ['nullable', 'integer', 'min:0', 'max:20'],
            'cuenco'    => ['nullable', 'integer', 'min:0', 'max:20'],
            'pulgar'    => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);

        $socio = $request->user()->member()->firstOrFail();

        $plato = $socio->savedMeals()->create([
            'meal_type' => $datos['meal_type'],
            'name'      => $datos['nombre_plato'],
        ]);

        foreach (['palma', 'puno', 'cuenco', 'pulgar'] as $tipo) {
            $plato->items()->create(['portion_type' => $tipo, 'count' => $datos[$tipo] ?? 0]);
        }

        return back()->with('exito', 'Plato "' . $plato->name . '" guardado.');
    }

    /**
     * Registra el plato guardado como la comida de hoy — mismo criterio de
     * "un registro por comida por día" que guardarComida(): si ya había
     * algo anotado para esa comida hoy, lo reemplaza por el plato.
     */
    public function usar(Request $request, SavedMeal $plato): RedirectResponse
    {
        $socio = $request->user()->member()->firstOrFail();

        abort_unless($plato->member_id === $socio->id, 403);

        $comida = $socio->mealLogs()->updateOrCreate(
            ['member_id' => $socio->id, 'meal_type' => $plato->meal_type, 'logged_on' => now()->toDateString()],
            [],
        );

        foreach ($plato->conteo as $tipo => $cantidad) {
            $comida->items()->updateOrCreate(
                ['meal_log_id' => $comida->id, 'portion_type' => $tipo],
                ['count' => $cantidad],
            );
        }

        return back()->with('exito', '"' . $plato->name . '" registrado como tu ' . $plato->meal_type_legible . ' de hoy.');
    }

    public function destroy(Request $request, SavedMeal $plato): RedirectResponse
    {
        $socio = $request->user()->member()->firstOrFail();

        abort_unless($plato->member_id === $socio->id, 403);

        $plato->delete();

        return back()->with('exito', 'Plato eliminado.');
    }
}
