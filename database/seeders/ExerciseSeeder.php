<?php

namespace Database\Seeders;

use App\Models\Exercise;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Biblioteca de ejercicios compartida (gym_id nulo): la ven todos los
 * gimnasios de la plataforma. Cada gimnasio puede añadir los suyos encima.
 */
class ExerciseSeeder extends Seeder
{
    /** [nombre, categoría, nivel, equipo, músculos, error común, consejo, video] */
    private const EJERCICIOS = [
        ['Sentadilla trasera', 'fuerza', 'intermedio', 'Barra olímpica', ['cuadriceps', 'gluteos', 'core'],
         'Dejar caer las rodillas hacia dentro y levantar los talones.',
         'Pies a la anchura de los hombros, peso en el mediopié, rodillas siguiendo la punta del pie.',
         'https://www.youtube.com/watch?v=lONrEX7zAbY'],
        ['Peso muerto convencional', 'fuerza', 'avanzado', 'Barra olímpica', ['espalda', 'isquiotibiales', 'gluteos'],
         'Redondear la espalda baja al despegar la barra.',
         'La barra pegada a la pierna. Empuja el suelo, no tires con la espalda.',
         'https://www.youtube.com/watch?v=PMaiog6JKrM'],
        ['Press de banca', 'fuerza', 'intermedio', 'Barra olímpica', ['pecho', 'triceps', 'hombros'],
         'Rebotar la barra en el pecho y despegar los glúteos del banco.',
         'Escápulas retraídas, codos a 45°, control en la bajada.',
         'https://www.youtube.com/watch?v=WYbdpUw7Pa4'],
        ['Press militar', 'fuerza', 'intermedio', 'Barra olímpica', ['hombros', 'triceps', 'core'],
         'Arquear la espalda baja para compensar la falta de movilidad.',
         'Aprieta glúteos y abdomen. Si no sube limpio, baja el peso.',
         'https://www.youtube.com/watch?v=nNMR9fRGRjQ'],
        ['Dominadas', 'fuerza', 'intermedio', 'Barra fija', ['espalda', 'biceps'],
         'Balancearse y no completar el rango.',
         'Empieza desde brazos extendidos y lleva el pecho a la barra.',
         'https://www.youtube.com/watch?v=UwhqxIELoug'],
        ['Remo con barra', 'fuerza', 'intermedio', 'Barra olímpica', ['espalda', 'biceps'],
         'Incorporarse en cada repetición usando impulso de cadera.',
         'Tronco a 45°, tira hacia el ombligo, aprieta las escápulas arriba.'],
        ['Prensa de piernas', 'fuerza', 'principiante', 'Prensa', ['cuadriceps', 'gluteos'],
         'Bajar tanto que la cadera se despega del respaldo.',
         'Baja hasta 90° y no bloquees la rodilla del todo arriba.'],
        ['Curl de bíceps con barra', 'fuerza', 'principiante', 'Barra Z', ['biceps'],
         'Mover los codos hacia delante y usar la espalda.',
         'Codos pegados al costado. Si te balanceas, pesa demasiado.'],
        ['Fondos en paralelas', 'fuerza', 'intermedio', 'Paralelas', ['triceps', 'pecho'],
         'Bajar demasiado y forzar el hombro.',
         'Baja hasta que el brazo llegue a la paralela y no más.'],
        ['Elevaciones laterales', 'fuerza', 'principiante', 'Mancuernas', ['hombros'],
         'Subir por encima del hombro con impulso.',
         'Poco peso, control total, sube hasta la altura del hombro.'],
        ['Hip thrust', 'fuerza', 'principiante', 'Barra olímpica', ['gluteos', 'isquiotibiales'],
         'Hiperextender la zona lumbar arriba.',
         'Termina el movimiento con los glúteos, mentón hacia el pecho.'],
        ['Zancadas', 'funcional', 'principiante', 'Mancuernas', ['cuadriceps', 'gluteos', 'core'],
         'Que la rodilla adelantada pase mucho la punta del pie.',
         'Paso largo, tronco erguido, baja en vertical.'],
        ['Plancha abdominal', 'core', 'principiante', 'Sin equipo', ['core'],
         'Levantar la cadera o dejarla caer.',
         'Cuerpo en línea recta. Aprieta glúteos y abdomen.',
         'https://www.youtube.com/watch?v=06-85ORjfb0'],
        ['Rueda abdominal', 'core', 'avanzado', 'Rueda', ['core', 'espalda'],
         'Arquear la lumbar al extender.',
         'Empieza de rodillas y con recorrido corto hasta dominarlo.'],
        ['Remo en máquina', 'cardio', 'principiante', 'Remo', ['espalda', 'piernas', 'core'],
         'Tirar con los brazos antes de empujar con las piernas.',
         'Primero piernas, luego tronco, al final brazos.'],
        ['Bicicleta de aire', 'cardio', 'principiante', 'Bicicleta de aire', ['piernas', 'core'],
         'Empezar demasiado fuerte y no poder sostener el ritmo.',
         'Marca un ritmo que puedas mantener el intervalo completo.'],
        ['Movilidad de cadera', 'movilidad', 'principiante', 'Sin equipo', ['cadera'],
         'Rebotar en el estiramiento.',
         'Movimientos controlados. Respira y no fuerces.'],
        ['Face pull', 'fuerza', 'principiante', 'Polea', ['hombros', 'espalda'],
         'Usar demasiado peso y convertirlo en un remo.',
         'Tira hacia la cara separando las manos. Poco peso, mucho control.'],
    ];

    public function run(): void
    {
        foreach (self::EJERCICIOS as $ejercicio) {
            [$nombre, $categoria, $nivel, $equipo, $musculos, $error, $consejo] = $ejercicio;
            $video = $ejercicio[7] ?? null;

            Exercise::updateOrCreate(
                ['gym_id' => null, 'slug' => Str::slug($nombre)],
                [
                    'name'            => $nombre,
                    'category'        => $categoria,
                    'level'           => $nivel,
                    'equipment'       => $equipo,
                    'muscle_groups'   => $musculos,
                    'common_mistakes' => $error,
                    'tips'            => $consejo,
                    'video_url'       => $video,
                    'is_active'       => true,
                ]
            );
        }
    }
}
