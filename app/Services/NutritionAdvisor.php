<?php

namespace App\Services;

use App\Models\Member;

/**
 * Recomendaciones nutricionales para el perfil del socio (Fase 3 de
 * PLAN-RUTINAS-PERSONALIZADAS.md). Puro PHP, sin API externa ni costo: un
 * sistema de reglas con fórmulas nutricionales estándar (Harris-Benedict
 * revisada) sobre los datos que ya existen en el expediente — peso, altura,
 * edad, género y meta activa. No es IA, es aritmética con buen criterio.
 */
class NutritionAdvisor
{
    public function __construct(private Member $member) {}

    public function recomendar(): array
    {
        $medida = $this->member->latestMeasurement;
        $meta   = $this->member->goals()->activos()->first();

        $peso     = $medida?->weight_kg ? (float) $medida->weight_kg : 70.0;
        $altura   = $this->member->height_cm ?? 170;
        $edad     = $this->member->age ?? 25;
        $genero   = $this->member->gender ?? 'M';
        // Enum real de member_goals.type (ver 2026_08_03_000103_create_members_tables.php):
        // perder_peso, ganar_musculo, fuerza, resistencia, salud, otro.
        $objetivo = $meta?->type ?? 'salud';

        // TMB (Harris-Benedict revisada). 'O' o sin género cae al lado
        // conservador (fórmula femenina), mismo criterio que Member::porcionesPara.
        $tmb = $genero === 'M'
            ? (88.362 + (13.397 * $peso) + (4.799 * $altura) - (5.677 * $edad))
            : (447.593 + (9.247 * $peso) + (3.098 * $altura) - (4.330 * $edad));

        $factorActividad = match ($objetivo) {
            'ganar_musculo' => 1.55,
            'perder_peso'   => 1.45,
            'fuerza'        => 1.6,
            'resistencia'   => 1.65,
            default         => 1.375,
        };

        $calorias = (int) round($tmb * $factorActividad);

        // Déficit/superávit moderado — no agresivo, para no perder músculo
        // al bajar de peso ni engordar de más al subir.
        $caloriasFinales = match ($objetivo) {
            'perder_peso'   => $calorias - 300,
            'ganar_musculo' => $calorias + 300,
            default         => $calorias,
        };

        $proteinaG = (int) round($peso * match ($objetivo) {
            'ganar_musculo' => 2.0,
            'perder_peso'   => 1.8,
            default         => 1.6,
        });

        $creatinaG = 5; // Dosis estándar de mantenimiento, sin fase de carga.

        $aguaMl = (int) round($peso * match ($objetivo) {
            'ganar_musculo' => 40,
            'perder_peso'   => 38,
            default         => 35,
        });

        // Macros: proteína y carbos a 4 kcal/g, grasas a 9 kcal/g. Grasas
        // primero (25% del total) y carbos absorben el resto — igual que
        // cualquier calculadora de macros estándar.
        $grasasG = (int) round(($caloriasFinales * 0.25) / 9);
        $carbsG  = max(0, (int) round(($caloriasFinales - ($proteinaG * 4) - ($grasasG * 9)) / 4));

        return [
            'calorias'         => $caloriasFinales,
            'proteina_g'       => $proteinaG,
            'proteina_scoops'  => (int) ceil($proteinaG / 25), // ~25 g de proteína por scoop
            'creatina_g'       => $creatinaG,
            'agua_ml'          => $aguaMl,
            'agua_litros'      => round($aguaMl / 1000, 1),
            'grasas_g'         => $grasasG,
            'carbs_g'          => $carbsG,
            'objetivo'         => $objetivo,
        ];
    }

    /** Etiqueta legible del objetivo, para no repetir el match en cada vista. */
    public static function objetivoLegible(string $objetivo): string
    {
        return match ($objetivo) {
            'perder_peso'   => 'Perder peso',
            'ganar_musculo' => 'Ganar músculo',
            'fuerza'        => 'Fuerza',
            'resistencia'   => 'Resistencia',
            'salud'         => 'Salud general',
            default         => 'General',
        };
    }
}
