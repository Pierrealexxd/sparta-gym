<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Exercise;
use App\Models\Faq;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Testimonial;
use App\Models\Trainer;
use App\Support\GymContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LandingController extends Controller
{
    public function index(): View
    {
        $gym = GymContext::current();

        abort_if($gym === null, 503, 'No hay ningún gimnasio configurado.');

        return view('landing.index', [
            'logoUrl' => $this->logoPublico(),
            'gym'           => $gym,
            'planes'        => Plan::publicos()->get(),
            'testimonios'   => Testimonial::publicados()->get(),
            'faqs'          => Faq::publicados()->get(),
            'cifras'        => $this->cifras(),
            'heroVideo'     => $this->heroVideo(),
            'ejercicios'    => Exercise::disponibles()->orderBy('name')->get(),
            'categorias'    => Exercise::disponibles()->select('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    /**
     * URL pública del logotipo, si existe. Se acepta en dos ubicaciones para
     * no depender de que quien lo suba recuerde la convención de Laravel:
     * si aparece en resources/images (más natural para un archivo de marca
     * que no pasa por Vite) se copia una sola vez a public/images, que es
     * donde el navegador puede pedirlo.
     */
    private function logoPublico(): ?string
    {
        $destino = public_path('images/logo.png');

        if (! is_file($destino)) {
            $origen = resource_path('images/logo.png');
            if (is_file($origen)) {
                @mkdir(dirname($destino), 0777, true);
                copy($origen, $destino);
            }
        }

        return is_file($destino) ? asset('images/logo.png') . '?v=' . filemtime($destino) : null;
    }

    /**
     * El video de fondo del hero. Devuelve null si todavía no se ha
     * subido, y el hero cae de vuelta a la variante sin video.
     */
    private function heroVideo(): ?string
    {
        $archivo = public_path('videos/hero.mp4');

        return is_file($archivo) ? asset('videos/hero.mp4') . '?v=' . filemtime($archivo) : null;
    }

    /**
     * Las cifras del hero salen de datos reales, no de un array a mano: si el
     * gimnasio crece, la web lo refleja sola. Se cachean 10 minutos —no una
     * hora como antes— porque contar 36 000 asistencias en cada visita no
     * tiene sentido, pero una hora de TTL dejaba "Clientes activos: 0+"
     * visible en producción demasiado tiempo tras el primer socio real.
     */
    private function cifras(): array
    {
        return Cache::remember('landing.cifras', now()->addMinutes(10), fn () => [
            'clientes'     => Member::activos()->count(),
            'entrenadores' => Trainer::activos()->count(),
            'sesiones'     => Attendance::count(),
            'anios'        => max(1, now()->year - 2019),
        ]);
    }

    /** Recibe el formulario de contacto de la landing. */
    public function contactar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'email'         => ['nullable', 'email', 'max:180'],
            'phone'         => ['nullable', 'string', 'max:40'],
            'interested_in' => ['nullable', 'string', 'max:120'],
            'message'       => ['required', 'string', 'min:10', 'max:2000'],
            // Trampa para robots: es un campo oculto que una persona nunca
            // rellena. Más discreto que un captcha y sin dependencias.
            'website'       => ['nullable', 'size:0'],
        ], [
            'name.required'    => 'Necesitamos tu nombre.',
            'message.required' => 'Cuéntanos en qué podemos ayudarte.',
            'message.min'      => 'Escribe un poco más para poder responderte bien.',
            'email.email'      => 'Ese correo no parece válido.',
        ]);

        unset($datos['website']);

        \App\Models\ContactMessage::create($datos + ['ip' => $request->ip()]);

        return back()
            ->with('exito', 'Mensaje recibido. Te respondemos hoy mismo.')
            ->withFragment('contacto');
    }
}
