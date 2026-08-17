<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\Faq;
use App\Models\Plan;
use App\Models\Program;
use App\Models\Recipe;
use App\Models\Testimonial;
use App\Support\GymContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Previsualización inline de las secciones de la landing. Cada sección se
 * renderiza dentro de un iframe usando las mismas Blade views y CSS que la
 * página pública, garantizando fidelidad visual pixel-perfecta.
 */
class PreviewController extends Controller
{
    private const SECCIONES = [
        'preguntas',
        'testimonios',
        'ejercicios',
        'programas',
        'recetas',
        'planes',
        'contacto',
    ];

    public function show(string $section): View
    {
        abort_unless(in_array($section, self::SECCIONES, true), 404);

        $method = 'datos_' . $section;

        return view("admin.preview.{$section}", $this->$method());
    }

    private function datos_preguntas(): array
    {
        return ['faqs' => Faq::publicados()->get()];
    }

    private function datos_testimonios(): array
    {
        return ['testimonios' => Testimonial::publicados()->get()];
    }

    private function datos_ejercicios(): array
    {
        return [
            'ejercicios' => Exercise::disponibles()->orderBy('name')->get(),
            'categorias' => Exercise::disponibles()
                ->select('category')->distinct()->orderBy('category')->pluck('category'),
        ];
    }

    private function datos_programas(): array
    {
        return [
            'programs' => Program::publicos()->get(),
            'ejemplosPrograma' => [
                'ganar_masa'   => Exercise::disponibles()->where('category', 'fuerza')
                    ->orderBy('name')->take(4)->get(),
                'perder_grasa' => Exercise::disponibles()->whereIn('category', ['cardio', 'funcional'])
                    ->orderBy('name')->take(4)->get(),
            ],
        ];
    }

    private function datos_recetas(): array
    {
        return ['recetas' => Recipe::disponibles()->get()];
    }

    private function datos_planes(): array
    {
        return ['planes' => Plan::publicos()->get()];
    }

    private function datos_contacto(): array
    {
        return [
            'gym'    => GymContext::current(),
            'planes' => Plan::publicos()->get(),
        ];
    }
}
