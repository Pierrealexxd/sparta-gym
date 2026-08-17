<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Exercise;
use App\Models\Faq;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Program;
use App\Models\Testimonial;
use App\Models\Trainer;
use App\Support\GymContext;
use App\Support\Marca;
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
            'logoUrl' => Marca::logoPublico(),
            'gym'           => $gym,
            'planes'        => Plan::publicos()->get(),
            'testimonios'   => Testimonial::publicados()->get(),
            'faqs'          => Faq::publicados()->get(),
            'cifras'        => $this->cifras(),
            'heroVideo'     => $this->heroVideo(),
            'heroPoster'    => $this->heroPoster(),
            'ejercicios'    => Exercise::disponibles()->orderBy('name')->get(),
            'categorias'    => Exercise::disponibles()->select('category')->distinct()->orderBy('category')->pluck('category'),
            'programs'      => Program::publicos()->get(),
            // Rutina tipo de cada programa (Fase 2): 4 ejercicios representativos
            // de sus categorías, no toda la biblioteca — la sección de más
            // arriba ya cubre el catálogo completo.
            'ejemplosPrograma' => [
                'ganar_masa'   => Exercise::disponibles()->where('category', 'fuerza')->orderBy('name')->take(4)->get(),
                'perder_grasa' => Exercise::disponibles()->whereIn('category', ['cardio', 'funcional'])->orderBy('name')->take(4)->get(),
            ],
        ]);
    }

    /**
     * El video de fondo del hero. Devuelve null si todavía no se ha
     * subido, y el hero cae de vuelta a la variante sin video.
     *
     * Vive en storage/, no en public/videos, y se sirve por una ruta propia
     * (ver heroVideoStream) — no como archivo estático directo. Motivo: el
     * servidor de producción (php artisan serve) sirve los archivos que
     * encuentra físicamente bajo public/ con su propio mini servidor
     * estático, que no entiende peticiones "Range". Sin eso, Safari/iOS no
     * reproduce el video en absoluto (ni con autoplay ni a mano): se queda
     * con el ícono nativo de "play" pausado. Al mover el archivo fuera de
     * public/, esa ruta pasa sí o sí por Laravel, cuyo response()->file()
     * (BinaryFileResponse de Symfony) sí responde 206 con Content-Range.
     */
    private function heroVideo(): ?string
    {
        $archivo = storage_path('app/media/hero.mp4');

        return is_file($archivo) ? route('landing.hero-video') . '?v=' . filemtime($archivo) : null;
    }

    /**
     * Primer fotograma del video, como poster: mientras el navegador todavía
     * está pidiendo el video (sobre todo en una red lenta o el servidor de un
     * solo worker de Render), la pantalla no queda en blanco — se ve esto de
     * entrada. Sí vive en public/ (a diferencia del video): es una imagen
     * estática de siempre, no necesita pasar por Laravel para nada.
     */
    private function heroPoster(): ?string
    {
        $archivo = public_path('images/hero-poster.jpg');

        return is_file($archivo) ? asset('images/hero-poster.jpg') . '?v=' . filemtime($archivo) : null;
    }

    /** Sirve el video del hero con soporte real de "Range requests" (ver heroVideo). */
    public function heroVideoStream()
    {
        $archivo = storage_path('app/media/hero.mp4');

        abort_unless(is_file($archivo), 404);

        return response()->file($archivo, [
            'Content-Type'  => 'video/mp4',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
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
