<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\Program;
use App\Models\ProgramRoutine;
use Illuminate\Database\Seeder;

/**
 * Rutinas base (plantillas) para los 2 programas de ProgramSeeder — Parte 2
 * de PLAN-PROGRAMAS.md. Usa solo ejercicios de ExerciseSeeder por nombre;
 * si falta alguno (biblioteca editada a mano), el día simplemente lo omite
 * en vez de romper el seeder.
 */
class ProgramRoutineSeeder extends Seeder
{
    /**
     * FASE 4 de PLAN-GUIAS-EJERCICIO.md: guía personalizada por programa en
     * algunos ejercicios de demo — el resto hereda del Exercise (comportamiento
     * normal, ver HasExerciseGuideOverrides). [video, descripción, tips, errores]
     */
    private const GUIAS = [
        'Sentadilla trasera' => [
            'https://www.youtube.com/watch?v=lONrEX7zAbY',
            'La sentadilla trasera es el ejercicio base de este programa: si solo tienes tiempo para uno, que sea este.',
            'Respira, llena de aire el abdomen y mantenlo firme durante todo el descenso.',
            'No dejar que las rodillas colapsen hacia adentro en la subida.',
        ],
        'Press de banca' => [
            'https://www.youtube.com/watch?v=WYbdpUw7Pa4',
            'Movimiento principal de empuje horizontal. Prioriza la técnica antes que el peso en la barra.',
            'Retrae las escápulas y mantenlas fijas durante todo el recorrido.',
            null,
        ],
        'Peso muerto convencional' => [
            null,
            'El más técnico de los tres básicos: si tienes dudas, pide que el entrenador te revise la forma antes de subir peso.',
            null,
            'Redondear la espalda baja para "ganar" unos centímetros de recorrido.',
        ],
        'Bicicleta de aire' => [
            'https://www.youtube.com/watch?v=OU8QSSSGyGc',
            'Aquí es donde se rompe el corazón del día — literal, es el bloque de intervalos.',
            'Marca un ritmo sostenible: mejor constante que explosivo al inicio y muerto al final.',
            null,
        ],
    ];

    public function run(): void
    {
        $ejercicios = Exercise::pluck('id', 'name');

        $this->crear('ganar-masa', 'Rutina base · Ganar masa muscular', [
            'Día 1 · Empuje' => [
                'focus' => 'Pecho, hombro y tríceps',
                'ejercicios' => [
                    ['Press de banca', 4, '6-8', 120],
                    ['Press militar', 4, '8-10', 90],
                    ['Fondos en paralelas', 3, '8-12', 90],
                ],
            ],
            'Día 2 · Tirón' => [
                'focus' => 'Espalda y bíceps',
                'ejercicios' => [
                    ['Dominadas', 4, 'al fallo', 120],
                    ['Remo con barra', 4, '8-10', 90],
                    ['Face pull', 3, '12-15', 60],
                ],
            ],
            'Día 3 · Piernas' => [
                'focus' => 'Cuádriceps, glúteo e isquiotibiales',
                'ejercicios' => [
                    ['Sentadilla trasera', 4, '6-8', 150],
                    ['Peso muerto convencional', 4, '5-6', 150],
                    ['Prensa de piernas', 3, '10-12', 90],
                    ['Hip thrust', 3, '10-12', 90],
                ],
            ],
            'Día 4 · Full body' => [
                'focus' => 'Cuerpo completo',
                'ejercicios' => [
                    ['Zancadas', 3, '10-12', 90],
                    ['Curl de bíceps con barra', 3, '10-12', 60],
                    ['Elevaciones laterales', 3, '12-15', 60],
                    ['Plancha abdominal', 3, '45 s', 45],
                ],
            ],
        ], $ejercicios);

        $this->crear('perder-grasa', 'Rutina base · Perder grasa corporal', [
            'Día 1 · Full body + HIIT' => [
                'focus' => 'Fuerza general + intervalos',
                'ejercicios' => [
                    ['Sentadilla trasera', 3, '10-12', 60],
                    ['Press de banca', 3, '10-12', 60],
                    ['Remo con barra', 3, '10-12', 60],
                    ['Bicicleta de aire', 4, '30 s', 30],
                ],
            ],
            'Día 2 · Fuerza + Cardio' => [
                'focus' => 'Piernas, hombro y remo',
                'ejercicios' => [
                    ['Peso muerto convencional', 3, '8-10', 90],
                    ['Press militar', 3, '10-12', 60],
                    ['Remo en máquina', 3, '500 m', 60],
                ],
            ],
            'Día 3 · Activo' => [
                'focus' => 'Movilidad y core',
                'ejercicios' => [
                    ['Zancadas', 3, '12-15', 60],
                    ['Plancha abdominal', 3, '45 s', 45],
                    ['Movilidad de cadera', 2, '10 por lado', 30],
                ],
            ],
        ], $ejercicios);
    }

    private function crear(string $slugPrograma, string $nombreRutina, array $dias, $ejercicios): void
    {
        $programa = Program::where('slug', $slugPrograma)->first();

        if (! $programa) {
            return;
        }

        $rutina = ProgramRoutine::updateOrCreate(
            ['program_id' => $programa->id, 'name' => $nombreRutina],
            ['sort_order' => 0],
        );

        // Recrear días/ejercicios en cada corrida del seeder: más simple que
        // sincronizar campo a campo y este seeder no corre en producción con
        // datos ya asignados a clientes (esos son Routine, no ProgramRoutine).
        $rutina->days()->each(function ($dia) {
            $dia->exercises()->delete();
            $dia->delete();
        });

        $i = 0;
        foreach ($dias as $nombreDia => $datosDia) {
            $dia = $rutina->days()->create([
                'name'       => $nombreDia,
                'focus'      => $datosDia['focus'] ?? null,
                'sort_order' => $i++,
            ]);

            $j = 0;
            foreach ($datosDia['ejercicios'] as [$nombreEjercicio, $sets, $reps, $descanso]) {
                $exerciseId = $ejercicios[$nombreEjercicio] ?? null;

                if (! $exerciseId) {
                    continue;
                }

                [$guideVideo, $guideDesc, $guideTips, $guideErrores] = self::GUIAS[$nombreEjercicio] ?? [null, null, null, null];

                $dia->exercises()->create([
                    'exercise_id'            => $exerciseId,
                    'sort_order'             => $j++,
                    'sets'                   => $sets,
                    'reps'                   => $reps,
                    'rest_seconds'           => $descanso,
                    'guide_video_url'        => $guideVideo,
                    'guide_video_source'     => 'youtube',
                    'guide_description'      => $guideDesc,
                    'guide_tips'             => $guideTips,
                    'guide_common_mistakes'  => $guideErrores,
                ]);
            }
        }
    }
}
