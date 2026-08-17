<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * El admin carga testimonios a mano aquí, y además aprueba los que un
 * socio escribe desde su propio panel (Cliente\TestimonialController):
 * esos llegan con is_published = false y no salen en la landing hasta
 * que alguien con web.editar los publica.
 */
class TestimonialController extends Controller
{
    public function index(Request $request): View
    {
        $termino = $request->get('q');

        return view('admin.contenido.testimonios.index', [
            'pendientes'  => Testimonial::pendientes()->whereNotNull('member_id')->with('member')->get(),
            'testimonios' => Testimonial::where(fn ($q) => $q->where('is_published', true)->orWhereNull('member_id'))
                ->when($termino, fn ($q) => $q->where(function ($sub) use ($termino) {
                    $t = '%' . trim($termino) . '%';
                    $sub->where('author', 'like', $t)
                        ->orWhere('content', 'like', $t);
                }))
                ->orderBy('sort_order')
                ->paginate(10)
                ->onEachSide(1)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.contenido.testimonios.form', ['testimonio' => new Testimonial()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validarDatos($request);

        if ($ruta = $this->guardarFoto($request)) {
            $datos['photo_path'] = $ruta;
        }

        Testimonial::create($datos);

        return redirect()->route('admin.testimonios.index')->with('exito', 'Testimonio creado.');
    }

    public function edit(Testimonial $testimonio): View
    {
        return view('admin.contenido.testimonios.form', ['testimonio' => $testimonio]);
    }

    public function update(Request $request, Testimonial $testimonio): RedirectResponse
    {
        $datos = $this->validarDatos($request);

        if ($ruta = $this->guardarFoto($request)) {
            if ($testimonio->photo_path) {
                Storage::disk('public')->delete($testimonio->photo_path);
            }
            $datos['photo_path'] = $ruta;
        }

        $testimonio->update($datos);

        return redirect()->route('admin.testimonios.index')->with('exito', 'Testimonio actualizado.');
    }

    /** Borra el testimonio definitivamente. Ocultar pasó a ocultar(). */
    public function destroy(Testimonial $testimonio): RedirectResponse
    {
        $testimonio->delete();

        return back()->with('exito', 'Testimonio eliminado.');
    }

    /** Oculta un testimonio publicado — la web deja de mostrarlo, sin borrarlo. */
    public function ocultar(Testimonial $testimonio): RedirectResponse
    {
        $testimonio->update(['is_published' => false]);

        return back()->with('exito', 'Testimonio despublicado.');
    }

    /** Aprueba un testimonio enviado por un socio (o republica uno oculto). */
    public function publicar(Testimonial $testimonio): RedirectResponse
    {
        $testimonio->update(['is_published' => true]);

        // El autor (si es un socio con cuenta) se entera de que su reseña
        // ya salió en la web pública.
        $autor = $testimonio->member?->user;
        if ($autor && $autor->is_active) {
            app(NotificationService::class)->disparar(
                $autor,
                'resena.aprobada',
                'Tu reseña fue publicada',
                'Ya puedes verla en la página pública del gimnasio.',
                'estrella',
                'baja',
                $testimonio->id,
                route('landing') . '#testimonios',
            );
        }

        return back()->with('exito', 'Testimonio publicado.');
    }

    private function validarDatos(Request $request): array
    {
        $datos = $request->validate([
            'author'     => ['required', 'string', 'max:120'],
            'role'       => ['nullable', 'string', 'max:120'],
            'content'    => ['required', 'string', 'max:1000'],
            'rating'     => ['required', 'integer', 'min:1', 'max:5'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'foto'       => ['nullable', 'image', 'max:3072'],
        ]);

        unset($datos['foto']);

        $datos['is_published'] = $request->boolean('is_published', true);
        $datos['sort_order'] = $datos['sort_order'] ?? 0;

        return $datos;
    }

    private function guardarFoto(Request $request): ?string
    {
        if (! $request->hasFile('foto')) {
            return null;
        }

        return $request->file('foto')->store('testimonios', 'public');
    }
}
