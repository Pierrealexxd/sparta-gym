<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ProgramRoutine;
use App\Models\Routine;
use App\Models\RoutineDay;
use App\Models\RoutineExercise;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Asignación automática de rutina al elegir un programa desde la landing
 * (Parte 3 de PLAN-PROGRAMAS.md). El socio SIEMPRE sale de
 * $request->user()->member() — nunca de un member_id del request, para que
 * un cliente no pueda asignarle una rutina a otro (regla 8 de
 * PROMPT-EJECUCION-PROGRAMAS.md). La ruta ya vive bajo middleware('rol:cliente')
 * en routes/cliente.php.
 */
class ProgramController extends Controller
{
    public function asignar(Request $request): RedirectResponse
    {
        $request->validate([
            'program_slug' => ['required', 'string', 'exists:programs,slug'],
        ]);

        $socio = $request->user()->member()->firstOrFail();
        $programa = Program::where('slug', $request->program_slug)->firstOrFail();

        $yaTiene = Routine::where('member_id', $socio->id)
            ->where('program_id', $programa->id)
            ->where('status', 'activa')
            ->exists();

        if ($yaTiene) {
            return back()->with('info', 'Ya tienes una rutina activa de este programa.');
        }

        $plantilla = ProgramRoutine::where('program_id', $programa->id)
            ->with('days.exercises')
            ->orderBy('sort_order')
            ->first();

        if (! $plantilla) {
            return back()->with('error', 'Este programa aún no tiene una rutina disponible. Pásate por recepción.');
        }

        $rutina = Routine::create([
            'gym_id'     => $socio->gym_id,
            'member_id'  => $socio->id,
            'trainer_id' => null,
            'program_id' => $programa->id,
            'name'       => $plantilla->name,
            'objective'  => $programa->objective,
            'notes'      => $plantilla->notes,
            'starts_at'  => now()->toDateString(),
            'status'     => 'activa',
        ]);

        foreach ($plantilla->days as $diaPlantilla) {
            $dia = RoutineDay::create([
                'routine_id' => $rutina->id,
                'name'       => $diaPlantilla->name,
                'focus'      => $diaPlantilla->focus,
                'sort_order' => $diaPlantilla->sort_order,
            ]);

            foreach ($diaPlantilla->exercises as $ejPlantilla) {
                RoutineExercise::create([
                    'routine_day_id' => $dia->id,
                    'exercise_id'    => $ejPlantilla->exercise_id,
                    'sort_order'     => $ejPlantilla->sort_order,
                    'sets'           => $ejPlantilla->sets,
                    'reps'           => $ejPlantilla->reps,
                    'weight_kg'      => $ejPlantilla->weight_kg,
                    'time_seconds'   => $ejPlantilla->time_seconds,
                    'rest_seconds'   => $ejPlantilla->rest_seconds,
                    'notes'          => $ejPlantilla->notes,
                    // FASE 1 de PLAN-GUIAS-EJERCICIO.md: la guía personalizada
                    // vive en la plantilla (program_routine_exercises) — sin
                    // copiarla acá, "Mi Rutina" (que lee routine_exercises,
                    // no la plantilla) nunca la vería.
                    'guide_video_url'       => $ejPlantilla->guide_video_url,
                    'guide_video_source'    => $ejPlantilla->guide_video_source,
                    'guide_video_file_path' => $ejPlantilla->guide_video_file_path,
                    'guide_description'     => $ejPlantilla->guide_description,
                    'guide_tips'            => $ejPlantilla->guide_tips,
                    'guide_common_mistakes' => $ejPlantilla->guide_common_mistakes,
                ]);
            }
        }

        return redirect()->route('cliente.progreso')
            ->with('exito', 'Rutina asignada. Revisa tu progreso para ver los detalles.');
    }
}
