<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\ProgramRoutine;
use App\Models\ProgramRoutineDay;
use App\Models\ProgramRoutineExercise;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * CRUD de rutinas base (plantillas) de un programa — Parte 2 de
 * PLAN-PROGRAMAS.md. Días y ejercicios se administran una vez creada la
 * rutina, con sus propias rutas (agregarDia/eliminarDia/agregarEjercicio/
 * eliminarEjercicio), calcado de Entrenador\RoutineController — ver la nota
 * en routes/admin.php sobre por qué no es un único formulario Alpine.
 */
class ProgramRoutineController extends Controller
{
    public function index(Program $programa): View
    {
        return view('admin.contenido.programas.rutinas.index', [
            'programa' => $programa,
            'rutinas'  => $programa->routineTemplates()->withCount('days')->get(),
        ]);
    }

    public function create(Program $programa): View
    {
        return view('admin.contenido.programas.rutinas.form', [
            'programa' => $programa,
            'rutina'   => new ProgramRoutine(),
        ]);
    }

    public function store(Request $request, Program $programa): RedirectResponse
    {
        $datos = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $rutina = $programa->routineTemplates()->create($datos + ['sort_order' => $programa->routineTemplates()->count()]);

        return redirect()->route('admin.programas.rutinas.edit', [$programa, $rutina])
            ->with('exito', 'Rutina base creada. Agrega los días de entrenamiento.');
    }

    public function edit(Program $programa, ProgramRoutine $rutina): View
    {
        $this->autorizar($programa, $rutina);

        $rutina->load('days.exercises.exercise');

        return view('admin.contenido.programas.rutinas.form', [
            'programa'   => $programa,
            'rutina'     => $rutina,
            'ejercicios' => Exercise::disponibles()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Program $programa, ProgramRoutine $rutina): RedirectResponse
    {
        $this->autorizar($programa, $rutina);

        $datos = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $rutina->update($datos);

        return redirect()->route('admin.programas.rutinas.edit', [$programa, $rutina])->with('exito', 'Rutina base actualizada.');
    }

    public function destroy(Program $programa, ProgramRoutine $rutina): RedirectResponse
    {
        $this->autorizar($programa, $rutina);
        $rutina->delete();

        return redirect()->route('admin.programas.rutinas.index', $programa)->with('exito', 'Rutina base eliminada.');
    }

    /* ---------------------------------------------------------- */

    public function agregarDia(Request $request, Program $programa, ProgramRoutine $rutina): RedirectResponse
    {
        $this->autorizar($programa, $rutina);

        $datos = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'focus' => ['nullable', 'string', 'max:100'],
        ]);

        $rutina->days()->create($datos + ['sort_order' => $rutina->days()->count()]);

        return back()->with('exito', 'Día agregado.');
    }

    public function eliminarDia(ProgramRoutineDay $dia): RedirectResponse
    {
        $dia->delete();

        return back()->with('exito', 'Día eliminado.');
    }

    public function agregarEjercicio(Request $request, ProgramRoutineDay $dia): RedirectResponse
    {
        $datos = $request->validate([
            'exercise_id'  => ['required', 'exists:exercises,id'],
            'sets'         => ['required', 'integer', 'min:1', 'max:20'],
            'reps'         => ['nullable', 'string', 'max:20'],
            'rest_seconds' => ['nullable', 'integer', 'min:0'],
            'notes'        => ['nullable', 'string', 'max:300'],
        ]);

        $dia->exercises()->create($datos + ['sort_order' => $dia->exercises()->count()]);

        return back()->with('exito', 'Ejercicio agregado.');
    }

    public function eliminarEjercicio(ProgramRoutineExercise $ejercicio): RedirectResponse
    {
        $ejercicio->delete();

        return back()->with('exito', 'Ejercicio eliminado.');
    }

    /** El día/rutina pedido tiene que pertenecer al programa de la URL. */
    private function autorizar(Program $programa, ProgramRoutine $rutina): void
    {
        abort_unless($rutina->program_id === $programa->id, 404);
    }
}
