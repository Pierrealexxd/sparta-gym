<?php

namespace Database\Seeders;

use App\Models\Recipe;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Biblioteca de recetas peruanas compartida (gym_id nulo): la ve todo
 * gimnasio de la plataforma. Fase 3 de PLAN_NUTRICION_PROGRESO.md — ventaja
 * frente a apps US-centric que no reconocen platos peruanos.
 */
class RecipeSeeder extends Seeder
{
    /** [nombre, descripción, ingredientes, pasos, minutos, comensales, etiquetas, porciones[palma,puno,cuenco,pulgar] con [count, alimento]] */
    private const RECETAS = [
        ['Lomo saltado', 'El clásico criollo: carne salteada a fuego alto con papas y arroz.',
         "Lomo de res en tiras\nCebolla roja\nTomate\nAjo\nSillao\nPapas fritas\nArroz blanco\nCulantro",
         "Sella el lomo a fuego muy alto, retira.\nSaltea cebolla y tomate en la misma sartén.\nRegresa la carne, agrega sillao y culantro.\nSirve con papas fritas y arroz.",
         25, 2, ['criollo', 'con papa frita'],
         ['palma' => [2, 'lomo de res'], 'puno' => [1, 'cebolla y tomate'], 'cuenco' => [2, 'arroz y papa'], 'pulgar' => [1, 'aceite de sartén']]],

        ['Ají de gallina', 'Pechuga deshilachada en crema de ají amarillo y pan.',
         "Pechuga de pollo\nAjí amarillo\nPan de molde\nLeche\nNueces\nQueso parmesano\nPapa\nArroz",
         "Hierve y deshilacha la pechuga.\nLicúa ají, pan remojado en leche y nueces.\nCocina la crema con el pollo deshilachado.\nSirve sobre papa sancochada con arroz.",
         40, 4, ['criollo', 'picante suave'],
         ['palma' => [2, 'pollo deshilachado'], 'puno' => [0, null], 'cuenco' => [2, 'papa y arroz'], 'pulgar' => [1, 'nueces y queso']]],

        ['Arroz con pollo', 'Arroz verde con culantro y presas de pollo al horno o sartén.',
         "Pollo\nCulantro\nArroz\nArvejas\nZanahoria\nCerveza o chicha de jora\nAjí amarillo",
         "Dora el pollo, retira.\nLicúa culantro con un poco de caldo.\nSofríe el arroz con el licuado y las verduras.\nAgrega el pollo y cocina a fuego lento hasta secar.",
         45, 4, ['criollo'],
         ['palma' => [2, 'pollo'], 'puno' => [1, 'arvejas y zanahoria'], 'cuenco' => [2, 'arroz'], 'pulgar' => [1, 'aceite de cocción']]],

        ['Causa limeña de pollo', 'Papa amarilla prensada con ají amarillo, rellena de pollo.',
         "Papa amarilla\nAjí amarillo\nLimón\nPollo deshilachado\nMayonesa\nPalta\nHuevo duro",
         "Sancocha y prensa la papa con ají, limón y aceite.\nMezcla el pollo con mayonesa.\nArma capas: papa, pollo, papa.\nDecora con palta y huevo.",
         35, 4, ['sin cocción de horno', 'frío'],
         ['palma' => [1, 'pollo'], 'puno' => [0, null], 'cuenco' => [2, 'papa amarilla'], 'pulgar' => [1, 'palta y mayonesa']]],

        ['Tallarines verdes con bistec', 'Pasta en salsa de albahaca acompañada de bistec apanado.',
         "Espagueti\nAlbahaca\nEspinaca\nQueso fresco\nLeche evaporada\nBistec de res\nPan rallado",
         "Licúa albahaca, espinaca, queso y leche.\nCocina la pasta y mézclala con la salsa.\nApana y fríe el bistec.\nSirve juntos.",
         30, 2, ['criollo'],
         ['palma' => [2, 'bistec'], 'puno' => [1, 'albahaca y espinaca'], 'cuenco' => [2, 'espagueti'], 'pulgar' => [1, 'queso y aceite']]],

        ['Pollo a la brasa con ensalada', 'Pollo horneado sazonado, sin la porción de papas fritas.',
         "Pollo entero\nAjí panca\nAjo\nComino\nCerveza negra\nLechuga\nTomate\nPepino",
         "Marina el pollo con ají panca, ajo, comino y cerveza toda la noche.\nHornea a fuego alto hasta dorar.\nSirve con ensalada fresca.",
         70, 4, ['al horno', 'sin frituras'],
         ['palma' => [2, 'pollo'], 'puno' => [2, 'ensalada fresca'], 'cuenco' => [0, null], 'pulgar' => [1, 'piel del pollo']]],

        ['Quinua con verduras y pollo', 'Guiso de quinua andina con vegetales y pollo en cubos.',
         "Quinua\nPollo en cubos\nZanahoria\nArvejas\nZapallo\nCebolla\nAjo",
         "Lava bien la quinua hasta que el agua salga clara.\nSofríe cebolla, ajo y pollo.\nAgrega la quinua y verduras con caldo.\nCocina hasta que la quinua esté suelta.",
         35, 4, ['andino', 'alto en fibra'],
         ['palma' => [1, 'pollo'], 'puno' => [1, 'zanahoria y arvejas'], 'cuenco' => [2, 'quinua'], 'pulgar' => [1, 'aceite de sofrito']]],

        ['Pescado a la plancha con camote', 'Filete de pescado blanco sellado con guarnición de camote.',
         "Filete de pescado (bonito o perico)\nLimón\nAjo\nCamote\nEnsalada de hoja verde",
         "Marina el pescado con limón y ajo.\nSella en sartén caliente sin exceso de aceite.\nSirve con camote sancochado y ensalada.",
         20, 1, ['bajo en grasa', 'alto en proteína'],
         ['palma' => [2, 'pescado'], 'puno' => [1, 'ensalada'], 'cuenco' => [1, 'camote'], 'pulgar' => [0, null]]],

        ['Menestra con arroz y bistec', 'Lentejas o frejoles guisados, arroz blanco y bistec a la plancha.',
         "Lentejas o frejoles\nCebolla\nTomate\nAjo\nArroz\nBistec de res",
         "Cocina las menestras con el sofrito hasta espesar.\nPrepara arroz blanco aparte.\nSella el bistec a la plancha.\nSirve los tres juntos.",
         40, 2, ['criollo', 'clásico de casa'],
         ['palma' => [2, 'bistec'], 'puno' => [0, null], 'cuenco' => [2, 'menestra y arroz'], 'pulgar' => [1, 'aceite del sofrito']]],

        ['Tortilla de verduras', 'Tortilla al horno o sartén con verduras y huevo, ligera y rápida.',
         "Huevos\nEspinaca\nZanahoria rallada\nCebolla\nQueso fresco",
         "Bate los huevos con sal.\nSaltea las verduras hasta que ablanden.\nMezcla todo y cocina en sartén a fuego bajo hasta cuajar.",
         15, 2, ['vegetariano', 'rápida'],
         ['palma' => [1, 'huevo'], 'puno' => [1, 'verduras salteadas'], 'cuenco' => [0, null], 'pulgar' => [1, 'queso fresco']]],

        ['Ceviche de pescado', 'Pescado fresco cocido en limón, con cebolla, ají y camote.',
         "Pescado blanco fresco\nLimón\nCebolla roja\nAjí limo\nCamote\nChoclo\nCulantro",
         "Corta el pescado en cubos.\nMacera en limón, sal y ají hasta que el pescado \"cocine\".\nAgrega cebolla en pluma y culantro.\nSirve con camote y choclo.",
         20, 2, ['sin cocción con calor', 'bajo en grasa'],
         ['palma' => [2, 'pescado'], 'puno' => [1, 'cebolla y ají'], 'cuenco' => [1, 'camote y choclo'], 'pulgar' => [0, null]]],

        ['Papa a la huancaína con pollo', 'Papa amarilla bañada en crema de ají amarillo y queso, con pollo.',
         "Papa amarilla\nAjí amarillo\nQueso fresco\nLeche evaporada\nGalleta de soda\nPechuga de pollo\nLechuga y huevo",
         "Licúa ají, queso, leche y galleta hasta obtener una crema.\nSancocha la papa y el pollo.\nBaña la papa en la crema, sirve con pollo, huevo y lechuga.",
         30, 3, ['criollo', 'picante suave'],
         ['palma' => [1, 'pollo'], 'puno' => [0, null], 'cuenco' => [2, 'papa'], 'pulgar' => [1, 'queso y crema']]],

        ['Salteado de verduras con tofu', 'Wok de verduras de estación con tofu, versión ligera sin carne.',
         "Tofu firme\nBrócoli\nZanahoria\nPimiento\nSillao\nJengibre\nArroz integral",
         "Sella el tofu en cubos hasta dorar.\nSaltea las verduras a fuego alto con jengibre.\nMezcla con el tofu y sillao.\nSirve sobre arroz integral.",
         25, 2, ['vegetariano', 'alto en fibra'],
         ['palma' => [2, 'tofu'], 'puno' => [2, 'verduras salteadas'], 'cuenco' => [1, 'arroz integral'], 'pulgar' => [1, 'aceite del wok']]],

        ['Sopa de quinua con pollo', 'Sopa espesa y reconfortante, ideal para días fríos.',
         "Quinua\nPollo\nZanahoria\nApio\nCulantro\nPapa",
         "Sofríe verduras, agrega caldo y quinua lavada.\nIncorpora el pollo en presas.\nCocina a fuego lento hasta que la quinua abra.\nAjusta sal y culantro al final.",
         40, 4, ['andino', 'sopa'],
         ['palma' => [1, 'pollo'], 'puno' => [1, 'zanahoria y apio'], 'cuenco' => [1, 'quinua y papa'], 'pulgar' => [0, null]]],

        ['Yogurt con frutas y frutos secos', 'Desayuno o merienda rápida, sin cocción.',
         "Yogurt griego natural\nFresas o plátano\nNueces o almendras\nMiel (opcional)",
         "Sirve el yogurt en un bowl.\nAgrega la fruta picada encima.\nCorona con los frutos secos.",
         5, 1, ['sin cocción', 'desayuno rápido'],
         ['palma' => [1, 'yogurt griego'], 'puno' => [0, null], 'cuenco' => [1, 'fruta'], 'pulgar' => [1, 'frutos secos']]],
    ];

    public function run(): void
    {
        foreach (self::RECETAS as [$nombre, $descripcion, $ingredientes, $pasos, $minutos, $comensales, $tags, $porciones]) {
            $receta = Recipe::updateOrCreate(
                ['slug' => Str::slug($nombre)],
                [
                    'name'         => $nombre,
                    'description'  => $descripcion,
                    'ingredients'  => $ingredientes,
                    'steps'        => $pasos,
                    'prep_minutes' => $minutos,
                    'servings'     => $comensales,
                    'tags'         => $tags,
                    'is_active'    => true,
                ],
            );

            foreach ($porciones as $tipo => [$cantidad, $alimento]) {
                $receta->portions()->updateOrCreate(
                    ['recipe_id' => $receta->id, 'portion_type' => $tipo],
                    ['count' => $cantidad, 'food_name' => $alimento],
                );
            }
        }
    }
}
