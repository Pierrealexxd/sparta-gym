<?php

namespace Tests\Unit;

use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * El "día de negocio" es el de Lima, venga como venga el reloj del
 * servidor. Este es el contrato que rompió Render: con la zona horaria
 * dejada al entorno (UTC por defecto en despliegues sin APP_TIMEZONE),
 * todo lo registrado entre las 19:00 y las 24:00 de Lima quedaba sellado
 * con la fecha del día siguiente UTC y desaparecía de los filtros que
 * comparan contra today() ("Asistieron hoy", caja del día).
 *
 * El instante trampa: 02:00 UTC == 21:00 del día anterior en Lima.
 */
class ZonaHorariaDeNegocioTest extends TestCase
{
    public function test_el_dia_de_negocio_sigue_a_lima_aunque_el_reloj_utc_haya_cambiado_de_dia(): void
    {
        $zonaDeLaApp = config('app.timezone');

        $instanteTrampa = Carbon::parse('2026-08-23 02:00:00', 'UTC');

        // now()/today() resuelven contra config('app.timezone'): si esa
        // zona volviera a ser UTC, esto devolvería 2026-08-23 y toda
        // venta de la noche limeña saldría del "hoy" de la caja.
        $diaDeNegocio = $instanteTrampa->copy()->timezone($zonaDeLaApp)->toDateString();

        $this->assertSame(
            '2026-08-22',
            $diaDeNegocio,
            'El día de negocio debe ser el de Lima aunque el reloj UTC ya haya cambiado de fecha.'
        );
    }

    public function test_la_zona_horaria_esta_fijada_en_codigo_sin_escape_por_entorno(): void
    {
        // Si alguien reintroduce env('APP_TIMEZONE', ...) y el entorno del
        // despliegue dice otra cosa, este test avisa antes que el cliente.
        $config = require config_path('app.php');

        $this->assertSame('America/Lima', $config['timezone']);
    }
}
