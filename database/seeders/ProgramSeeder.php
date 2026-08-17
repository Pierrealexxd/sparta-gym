<?php

namespace Database\Seeders;

use App\Models\Program;
use Illuminate\Database\Seeder;

/**
 * Los dos programas públicos de la landing (Fase 0 de
 * PLAN-RUTINAS-PERSONALIZADAS.md). gym_id nulo: biblioteca compartida, igual
 * que ExerciseSeeder y RecipeSeeder.
 */
class ProgramSeeder extends Seeder
{
    public function run(): void
    {
        Program::updateOrCreate(
            ['slug' => 'ganar-masa'],
            [
                'name'        => 'Ganar masa muscular',
                'tagline'     => 'Volumen · Fuerza · Hipertrofia',
                'objective'   => 'ganar_masa',
                'icon'        => 'llama',
                'description' => '<p>Un programa de fuerza progresiva pensado para sumar kilos de músculo de forma sostenida, no para "inflar" en dos semanas y perderlo al mes siguiente.</p>'
                    . '<p>Empieza con una evaluación de ingreso donde medimos tu punto de partida real: fuerza en los movimientos base, técnica y disponibilidad de tiempo. Sobre eso se arma tu rutina — nunca al revés.</p>'
                    . '<p>Cada semana subes algo: una repetición, medio kilo, una serie más. El plan nutricional que acompaña la rutina está calculado con tu peso y tu objetivo, no es una tabla genérica.</p>',
                'highlights' => [
                    '4-5 días por semana',
                    'Progresión de carga semanal',
                    'Plan nutricional personalizado',
                    'Seguimiento mensual de medidas',
                ],
                'duration_weeks' => 12,
                'difficulty'     => 'intermedio',
                'sort_order'     => 1,
            ],
        );

        Program::updateOrCreate(
            ['slug' => 'perder-grasa'],
            [
                'name'        => 'Perder grasa corporal',
                'tagline'     => 'Definición · Cardio · Recomposición',
                'objective'   => 'perder_grasa',
                'icon'        => 'rayo',
                'description' => '<p>Combina fuerza y cardio de intervalos para que el peso que bajes sea grasa, no músculo — la diferencia entre "pesar menos" y "verte mejor".</p>'
                    . '<p>El control se hace con la mano, no con la balanza de cocina: cuatro porciones por comida (palma, puño, cuenco, pulgar) que se ajustan a tu objetivo sin contar calorías todo el día.</p>'
                    . '<p>Las gráficas de progreso muestran peso y grasa corporal juntos, porque solo el peso miente: puedes bajar de peso y no de grasa, o al revés mientras ganas músculo.</p>',
                'highlights' => [
                    '3-4 días por semana',
                    'HIIT + fuerza combinados',
                    'Control de porciones con la mano',
                    'Gráficas de progreso semanales',
                ],
                'duration_weeks' => 8,
                'difficulty'     => 'principiante',
                'sort_order'     => 2,
            ],
        );
    }
}
