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
                // FASE 3 de PLAN-GUIAS-EJERCICIO.md: recomendaciones de demo.
                'nutrition_tips' => [
                    'Al menos 1.8 g de proteína por kg de peso al día.',
                    'No entrenes en ayunas si tu objetivo es ganar masa.',
                    'Añade un batido post-entreno si te cuesta llegar a las calorías del día.',
                ],
                'recovery_tips' => [
                    'Duerme 7-8 horas: es cuando más músculo se repara.',
                    'Deja 48h entre sesiones del mismo grupo muscular.',
                    'Un día de descanso total por semana no es opcional.',
                ],
                'hydration_tips' => [
                    'Mínimo 3 litros de agua al día, más si hace calor.',
                    'Un vaso de agua al levantarte, antes del café.',
                ],
                'supplements_tips' => [
                    'Creatina monohidratada: 5 g al día, cualquier hora.',
                    'Proteína en polvo solo si no llegas con comida real.',
                ],
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
                'nutrition_tips' => [
                    'Déficit calórico moderado: no más de 500 kcal por debajo de tu mantenimiento.',
                    'Prioriza proteína en cada comida para no perder músculo.',
                    'Evita azúcares líquidos (gaseosas, jugos envasados).',
                ],
                'recovery_tips' => [
                    'Recuperación activa el día libre: caminata suave o movilidad.',
                    'El cardio de intervalos también necesita descanso — no lo hagas todos los días.',
                ],
                'hydration_tips' => [
                    'Un vaso de agua antes de cada comida ayuda a controlar la porción.',
                    'Evita bebidas azucaradas incluso las "light" con frecuencia.',
                ],
                'supplements_tips' => [
                    'BCAA es opcional, no imprescindible con proteína suficiente en la dieta.',
                    'Cafeína pre-entreno (100-200 mg) si te ayuda a rendir más en el HIIT.',
                ],
            ],
        );
    }
}
