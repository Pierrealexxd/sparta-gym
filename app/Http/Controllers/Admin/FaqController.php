<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(Request $request): View
    {
        $termino = $request->get('q');

        return view('admin.contenido.faqs.index', [
            'faqs' => Faq::query()
                ->when($termino, fn ($q) => $q->where(function ($sub) use ($termino) {
                    $t = '%' . trim($termino) . '%';
                    $sub->where('question', 'like', $t)
                        ->orWhere('answer', 'like', $t)
                        ->orWhere('category', 'like', $t);
                }))
                ->orderBy('sort_order')
                ->paginate(10)
                ->onEachSide(1)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.contenido.faqs.form', ['faq' => new Faq()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Faq::create($this->validarDatos($request));

        return redirect()->route('admin.faqs.index')->with('exito', 'Pregunta creada.');
    }

    public function edit(Faq $faq): View
    {
        return view('admin.contenido.faqs.form', ['faq' => $faq]);
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $faq->update($this->validarDatos($request));

        return redirect()->route('admin.faqs.index')->with('exito', 'Pregunta actualizada.');
    }

    /** Borra la pregunta definitivamente. Ocultar pasó a ocultar(). */
    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with('exito', 'Pregunta eliminada.');
    }

    /** Oculta una pregunta publicada — la web deja de mostrarla, sin borrarla. */
    public function ocultar(Faq $faq): RedirectResponse
    {
        $faq->update(['is_published' => false]);

        return back()->with('exito', 'Pregunta despublicada.');
    }

    /** Publica (o república) una pregunta oculta — complemento de ocultar(). */
    public function publicar(Faq $faq): RedirectResponse
    {
        $faq->update(['is_published' => true]);

        return back()->with('exito', 'Pregunta publicada.');
    }

    private function validarDatos(Request $request): array
    {
        $datos = $request->validate([
            'question'   => ['required', 'string', 'max:200'],
            'answer'     => ['required', 'string', 'max:2000'],
            'category'   => ['nullable', 'string', 'max:40'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $datos['is_published'] = $request->boolean('is_published', true);
        $datos['sort_order'] = $datos['sort_order'] ?? 0;

        return $datos;
    }
}
