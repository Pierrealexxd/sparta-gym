<?php

namespace App\Http\Controllers\Entrenador;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\RoutineDay;
use App\Models\RoutineExercise;
use App\Models\Trainer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * El constructor de rutinas: rutina → día → ejercicio, en tres niveles
 * porque así es como un entrenador piensa un plan real ("lunes empuje,
 * miércoles tirón"), no como una lista plana de movimientos.
 */
class RoutineController extends Controller
{
    public function index(Request $request): View
    {
        $entrenador = $this->entrenador($request);

        return view('entrenador.rutinas.index', [
            'rutinas' => Routine::where('trainer_id', $entrenador->id)
                ->with('member')
                ->withCount(['days as ejercicios_count' => fn ($q) => $q->withCount('exercises')])
                ->latest()
                ->paginate(10)
                ->onEachSide(1),
        ]);
    }

    public function create(Request $request): View
    {
        $entrenador = $this->entrenador($request);

        return view('entrenador.rutinas.form', [
            'rutina' => new Routine(),
            'clientes' => $entrenador->activeMembers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $entrenador = $this->entrenador($request);

        $datos = $request->validate([
            'member_id'  => ['required', 'exists:members,id'],
            'name'       => ['required', 'string', 'max:120'],
            'objective'  => ['nullable', 'string', 'max:160'],
            'starts_at'  => ['nullable', 'date'],
            'ends_at'    => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless(
            $entrenador->activeMembers->contains('id', $datos['member_id']),
            403,
            'Ese socio no está a tu cargo.'
        );

        $rutina = Routine::create($datos + ['trainer_id' => $entrenador->id, 'status' => 'activa']);

        return redirect()->route('entrenador.entrenamientos.show', $rutina)->with('exito', 'Rutina creada. Agrega los días de entrenamiento.');
    }

    public function show(Request $request, Routine $routine): View
    {
        $this->autorizar($request, $routine);

        $routine->load('days.exercises.exercise', 'member');

        return view('entrenador.rutinas.show', [
            'rutina'     => $routine,
            'ejercicios' => Exercise::disponibles()->orderBy('name')->get(),
        ]);
    }

    public function edit(Request $request, Routine $routine): View
    {
        $this->autorizar($request, $routine);

        return view('entrenador.rutinas.form', [
            'rutina' => $routine,
            'clientes' => $this->entrenador($request)->activeMembers,
        ]);
    }

    public function update(Request $request, Routine $routine): RedirectResponse
    {
        $this->autorizar($request, $routine);

        $datos = $request->validate([
            'name'      => ['required', 'string', 'max:120'],
            'objective' => ['nullable', 'string', 'max:160'],
            'starts_at' => ['nullable', 'date'],
            'ends_at'   => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status'    => ['required', 'in:activa,finalizada,borrador'],
            'notes'     => ['nullable', 'string', 'max:1000'],
        ]);

        $routine->update($datos);

        return redirect()->route('entrenador.entrenamientos.show', $routine)->with('exito', 'Rutina actualizada.');
    }

    public function destroy(Request $request, Routine $routine): RedirectResponse
    {
        $this->autorizar($request, $routine);
        $routine->delete();

        return redirect()->route('entrenador.entrenamientos.index')->with('exito', 'Rutina eliminada.');
    }

    /* ---------------------------------------------------------- */

    public function agregarDia(Request $request, Routine $routine): RedirectResponse
    {
        $this->autorizar($request, $routine);

        $datos = $request->validate([
            'name'  => ['required', 'string', 'max:80'],
            'focus' => ['nullable', 'string', 'max:120'],
        ]);

        $routine->days()->create($datos + ['sort_order' => $routine->days()->count()]);

        return back()->with('exito', 'Día agregado.');
    }

    public function eliminarDia(Request $request, RoutineDay $routineDay): RedirectResponse
    {
        $this->autorizar($request, $routineDay->routine);
        $routineDay->delete();

        return back()->with('exito', 'Día eliminado.');
    }

    public function agregarEjercicio(Request $request, RoutineDay $routineDay): RedirectResponse
    {
        $this->autorizar($request, $routineDay->routine);

        $datos = $request->validate([
            'exercise_id'  => ['required', 'exists:exercises,id'],
            'sets'         => ['required', 'integer', 'min:1', 'max:20'],
            'reps'         => ['nullable', 'string', 'max:20'],
            'weight_kg'    => ['nullable', 'numeric', 'min:0'],
            'time_seconds' => ['nullable', 'integer', 'min:0'],
            'rest_seconds' => ['nullable', 'integer', 'min:0'],
            'notes'        => ['nullable', 'string', 'max:300'],
        ]);

        $routineDay->exercises()->create($datos + ['sort_order' => $routineDay->exercises()->count()]);

        return back()->with('exito', 'Ejercicio agregado.');
    }

    public function eliminarEjercicio(Request $request, RoutineExercise $routineExercise): RedirectResponse
    {
        $this->autorizar($request, $routineExercise->day->routine);
        $routineExercise->delete();

        return back()->with('exito', 'Ejercicio eliminado.');
    }

    /* ---------------------------------------------------------- */

    private function entrenador(Request $request): Trainer
    {
        return Trainer::where('user_id', $request->user()->id)->firstOrFail();
    }

    private function autorizar(Request $request, Routine $routine): void
    {
        abort_unless(
            $routine->trainer_id === $this->entrenador($request)->id,
            403,
            'Esta rutina no es tuya.'
        );
    }
}
