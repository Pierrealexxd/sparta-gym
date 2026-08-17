<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Plan;
use App\Services\MatriculaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    public function __construct(private readonly MatriculaService $matricula) {}

    public function index(Request $request): View
    {
        $termino = $request->get('q');

        $membresias = Membership::query()
            ->with(['member', 'plan'])
            ->when($request->get('estado'), fn ($q, $e) => $q->where('status', $e))
            ->when($termino, fn ($q) => $q->where(function ($sub) use ($termino) {
                $t = '%' . trim($termino) . '%';
                $sub->where('plan_name', 'like', $t)
                    ->orWhereHas('member', fn ($m) => $m
                        ->where('first_name', 'like', $t)
                        ->orWhere('last_name', 'like', $t)
                        ->orWhere('code', 'like', $t));
            }))
            ->latest('starts_at')
            ->paginate(10)
            ->onEachSide(1)
            ->withQueryString();

        return view('admin.membresias.index', [
            'membresias' => $membresias,
            'planes'     => Plan::activos()->get(),
        ]);
    }

    /**
     * Renueva la membresía de un socio existente y, si corresponde,
     * registra el pago en el mismo trámite: en recepción esto ocurre en
     * una sola conversación con el socio, no en dos pantallas separadas.
     * Para socios nuevos, ver Admin\MatriculaController.
     */
    public function store(Request $request, Member $member): RedirectResponse
    {
        $datos = $request->validate([
            'plan_id'    => ['required', 'exists:plans,id'],
            'starts_at'  => ['required', 'date'],
            'ends_at'    => ['nullable', 'date', 'after_or_equal:starts_at'],
            'discount'   => ['nullable', 'numeric', 'min:0'],
            'method'     => ['required', 'in:efectivo,transferencia,yape,plin,tarjeta,otro'],
            'reference'  => ['nullable', 'string', 'max:120'],
            'registrar_pago' => ['nullable', 'boolean'],
        ]);

        $plan = Plan::findOrFail($datos['plan_id']);

        $this->matricula->renovarMembresia($member, $plan, $datos, $request->user());

        return back()->with('exito', 'Membresía registrada.');
    }

    public function cancelar(Membership $membership): RedirectResponse
    {
        $membership->update(['status' => 'cancelada']);

        return back()->with('exito', 'Membresía cancelada.');
    }
}
