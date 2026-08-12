<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Un socio puede escribir su propia reseña desde su panel. Se guarda sin
 * publicar: sólo aparece en la landing cuando el admin la aprueba desde
 * admin.testimonios.index (ver Admin\TestimonialController::publicar).
 */
class TestimonialController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $socio = $request->user()->member()->firstOrFail();

        if ($socio->testimonial()->exists()) {
            return back()->with('error', 'Ya enviaste tu reseña. Está a la espera de aprobación o ya fue publicada.');
        }

        $datos = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $socio->testimonial()->create($datos + [
            'author'       => $socio->full_name,
            'role'         => 'Socio desde ' . $socio->joined_at->format('Y'),
            'is_published' => false,
        ]);

        return back()->with('exito', '¡Gracias! Tu reseña quedó enviada y a la espera de aprobación.');
    }
}
