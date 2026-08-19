<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\Faq;
use App\Models\Gym;
use App\Models\Plan;
use App\Models\Testimonial;
use App\Support\GymContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * El gimnasio y todo el contenido que se publica en la web.
 *
 * Los textos son deliberadamente lacónicos: los espartanos eran célebres por
 * su brevedad —de ahí viene la palabra— y la marca lo hereda. Todo esto es
 * editable desde el panel; aquí sólo se siembra el punto de partida.
 */
class SpartaGymSeeder extends Seeder
{
    public function run(): void
    {
        $gym = Gym::updateOrCreate(
            ['slug' => 'sparta-gym'],
            [
                'name'        => 'Sparta Gym',
                'tagline'     => 'Hierro · Sudor · Sangre',
                'description' => 'Un gimnasio sin atajos. Hierro de verdad, entrenadores que corrigen, y una comunidad que no te deja abandonar.',
                'email'       => 'contacto@spartagym.pe',
                'phone'       => '+51 900 000 000',
                'whatsapp'    => '+51 900 000 000',
                'address'     => 'Av. Principal 000',
                'city'        => 'Piura',
                'country'     => 'PE',
                'latitude'    => -5.194490,
                'longitude'   => -80.632820,
                'currency'    => 'PEN',
                'timezone'    => 'America/Lima',
                'schedule'    => [
                    ['dia' => 'Lunes a sábado', 'abre' => '08:00', 'cierra' => '13:00'],
                    ['dia' => 'Lunes a sábado', 'abre' => '16:00', 'cierra' => '22:00'],
                    ['dia' => 'Domingo',        'abre' => '09:00', 'cierra' => '13:00'],
                ],
                'socials' => [
                    'instagram' => 'https://instagram.com/spartagym',
                    'facebook'  => 'https://facebook.com/spartagym',
                    'tiktok'    => 'https://tiktok.com/@spartagym',
                    'youtube'   => null,
                ],
                'is_active' => true,
            ]
        );

        // A partir de aquí todo se crea dentro de este gimnasio.
        GymContext::set($gym);

        $this->planes();
        $this->instalaciones();
        $this->maquinas();
        $this->preguntas();
        $this->testimonios();
    }

    private function planes(): void
    {
        $planes = [
            [
                'name'     => 'Rutina diaria',
                'tagline'  => 'Pruébalo antes de decidir.',
                'price'    => 5,
                'duration_days' => 1,
                'features' => ['Acceso completo por un día', 'Sin matrícula', 'Sin compromiso'],
                'is_featured' => false,
            ],
            [
                'name'     => 'Mensualidad',
                'tagline'  => 'El punto de partida.',
                'price'    => 70,
                'duration_days' => 30,
                'features' => [
                    'Acceso ilimitado en horario completo',
                    'Rutina inicial personalizada',
                    'Evaluación física de ingreso',
                    'Acceso a tu panel de progreso',
                ],
                'is_featured' => false,
            ],
            [
                'name'     => 'Trimestral',
                'tagline'  => 'Donde empiezan a verse los resultados.',
                'price'    => 240,
                'duration_days' => 90,
                'features' => [
                    'Todo lo del plan mensual',
                    'Rutina actualizada cada mes',
                    'Control de medidas mensual',
                    'Asesoría nutricional básica',
                    '10 % de descuento en suplementos',
                ],
                'is_featured' => true,
            ],
            [
                'name'     => 'Anual',
                'tagline'  => 'Para quien ya no negocia consigo mismo.',
                'price'    => 840,
                'duration_days' => 365,
                'features' => [
                    'Todo lo del plan trimestral',
                    'Dos meses de regalo',
                    'Entrenador asignado',
                    'Plan nutricional completo',
                    '20 % de descuento en suplementos',
                    'Invitado gratis una vez al mes',
                ],
                'is_featured' => false,
            ],
        ];

        foreach ($planes as $i => $plan) {
            Plan::updateOrCreate(
                ['gym_id' => GymContext::id(), 'slug' => Str::slug($plan['name'])],
                $plan + ['slug' => Str::slug($plan['name']), 'sort_order' => $i, 'is_public' => true, 'is_active' => true]
            );
        }
    }

    private function instalaciones(): void
    {
        $items = [
            ['name' => 'Zona de peso libre',   'tagline' => '400 m² de hierro.',            'description' => 'Barras olímpicas, mancuernas hasta 60 kg, racks de sentadilla y plataforma de peso muerto.', 'icon' => 'barbell'],
            ['name' => 'Sala de máquinas',     'tagline' => 'Aislamiento sin excusas.',     'description' => 'Línea completa de máquinas guiadas para trabajar cada grupo muscular con técnica segura.', 'icon' => 'machine'],
            ['name' => 'Zona funcional',       'tagline' => 'Fuerza que se usa fuera.',     'description' => 'Cuerdas, kettlebells, cajones, trineo y espacio abierto para trabajo metabólico.', 'icon' => 'rope'],
            ['name' => 'Cardio',               'tagline' => 'El motor.',                    'description' => 'Cintas, elípticas, bicicletas de aire y remos con monitor de frecuencia cardíaca.', 'icon' => 'heart'],
            ['name' => 'Vestuarios',           'tagline' => 'Agua caliente siempre.',       'description' => 'Duchas, casilleros con llave y zona de cambio amplia.', 'icon' => 'locker'],
            ['name' => 'Área de recuperación', 'tagline' => 'Entrenar es la mitad.',        'description' => 'Zona de estiramiento, rodillos de espuma y espacio de movilidad.', 'icon' => 'recovery'],
        ];

        foreach ($items as $i => $item) {
            Facility::updateOrCreate(
                ['gym_id' => GymContext::id(), 'type' => 'instalacion', 'name' => $item['name']],
                $item + ['type' => 'instalacion', 'sort_order' => $i, 'is_published' => true]
            );
        }
    }

    private function maquinas(): void
    {
        $items = [
            ['name' => 'Rack de sentadilla',   'specs' => ['unidades' => 4,  'zona' => 'Peso libre']],
            ['name' => 'Prensa de piernas',    'specs' => ['unidades' => 2,  'zona' => 'Máquinas']],
            ['name' => 'Jaula de dominadas',   'specs' => ['unidades' => 3,  'zona' => 'Funcional']],
            ['name' => 'Press banca olímpico', 'specs' => ['unidades' => 4,  'zona' => 'Peso libre']],
            ['name' => 'Polea alta y baja',    'specs' => ['unidades' => 6,  'zona' => 'Máquinas']],
            ['name' => 'Plataforma de peso muerto', 'specs' => ['unidades' => 2, 'zona' => 'Peso libre']],
            ['name' => 'Cinta de correr',      'specs' => ['unidades' => 8,  'zona' => 'Cardio']],
            ['name' => 'Bicicleta de aire',    'specs' => ['unidades' => 4,  'zona' => 'Cardio']],
        ];

        foreach ($items as $i => $item) {
            Facility::updateOrCreate(
                ['gym_id' => GymContext::id(), 'type' => 'maquina', 'name' => $item['name']],
                $item + ['type' => 'maquina', 'sort_order' => $i, 'is_published' => true]
            );
        }
    }

    private function preguntas(): void
    {
        $faqs = [
            ['question' => '¿Necesito experiencia para empezar?',
             'answer'   => 'No. El primer día hacemos una evaluación y sales con una rutina hecha para ti. Nadie entrena solo la primera semana.'],
            ['question' => '¿Hay matrícula?',
             'answer'   => 'No cobramos matrícula. Pagas tu plan y entrenas.'],
            ['question' => '¿Puedo congelar mi membresía?',
             'answer'   => 'Sí, hasta 15 días al año por lesión o viaje. Avísanos en recepción antes de ausentarte.'],
            ['question' => '¿Qué métodos de pago aceptan?',
             'answer'   => 'Efectivo, Yape, Plin, transferencia y tarjeta. Todos los pagos quedan registrados en tu panel.'],
            ['question' => '¿Cómo entro al gimnasio?',
             'answer'   => 'Con el código QR de tu panel o dictando tu código en recepción. Tu asistencia queda registrada automáticamente.'],
            ['question' => '¿Los entrenadores están incluidos?',
             'answer'   => 'La rutina y las correcciones de técnica sí, en todos los planes. El seguimiento uno a uno viene con el plan anual.'],
            ['question' => '¿Hay horario de menos gente?',
             'answer'   => 'De 10:00 a 16:00 el gimnasio está tranquilo. La hora punta es de 18:00 a 21:00.'],
        ];

        foreach ($faqs as $i => $faq) {
            Faq::updateOrCreate(
                ['gym_id' => GymContext::id(), 'question' => $faq['question']],
                $faq + ['sort_order' => $i, 'is_published' => true]
            );
        }
    }

    private function testimonios(): void
    {
        $items = [
            ['author' => 'Diego Ramírez', 'role' => 'Cliente desde 2022', 'rating' => 5,
             'content' => 'Llevaba años entrando y saliendo de gimnasios. Aquí llevo dos años porque alguien nota cuando falto.'],
            ['author' => 'Lucía Vargas', 'role' => 'Socia desde 2023', 'rating' => 5,
             'content' => 'Bajé 14 kilos en ocho meses. No por magia: por una rutina que cambiaba cuando dejaba de servirme.'],
            ['author' => 'Marco Silva', 'role' => 'Cliente desde 2021', 'rating' => 5,
             'content' => 'Vine por el peso libre. Me quedé porque me corrigieron la sentadilla el primer día.'],
            ['author' => 'Andrea Chávez', 'role' => 'Socia desde 2024', 'rating' => 5,
             'content' => 'Entré sin saber nada. Nadie me miró raro. Eso, en un gimnasio, no es poca cosa.'],
        ];

        foreach ($items as $i => $item) {
            Testimonial::updateOrCreate(
                ['gym_id' => GymContext::id(), 'author' => $item['author']],
                $item + ['sort_order' => $i, 'is_published' => true]
            );
        }
    }
}
