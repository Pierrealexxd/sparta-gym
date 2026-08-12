<?php

namespace App\Http\Controllers\Entrenador;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberMeasurement;
use App\Models\Trainer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ficha de un cliente, vista por su entrenador: sólo lo que necesita para
 * entrenarlo (medidas, objetivos, rutina), no el expediente administrativo
 * completo que sí ve recepción.
 */
class MemberController extends Controller
{
    public function show(Request $request, Member $member): View
    {
        $this->autorizar($request, $member);

        $member->load([
            'measurements' => fn ($q) => $q->orderBy('measured_at'),
            'goals' => fn ($q) => $q->activos(),
            'routines' => fn ($q) => $q->activas()->with('days.exercises.exercise'),
            // Fase 2 del plan de nutrición: el entrenador ve el diario de
            // hoy, no lo edita — eso sigue siendo cosa del propio socio.
            'mealLogs' => fn ($q) => $q->delDia()->with('items'),
        ]);

        return view('entrenador.clientes.show', [
            'cliente' => $member,
            'grafico' => [
                'labels' => $member->measurements->map(fn (MemberMeasurement $m) => $m->measured_at->format('d/m'))->all(),
                'data'   => $member->measurements->map(fn (MemberMeasurement $m) => (float) $m->weight_kg)->all(),
            ],
        ]);
    }

    public function guardarMedida(Request $request, Member $member): RedirectResponse
    {
        $this->autorizar($request, $member);

        $datos = $request->validate([
            'measured_at'  => ['required', 'date'],
            'weight_kg'    => ['required', 'numeric', 'min:20', 'max:400'],
            'body_fat_pct' => ['nullable', 'numeric', 'min:2', 'max:70'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ]);

        $member->measurements()->create($datos + ['recorded_by' => $request->user()->id]);

        return back()->with('exito', 'Medida registrada.');
    }

    /** Un entrenador sólo puede ver y anotar a los clientes que tiene a cargo. */
    private function autorizar(Request $request, Member $member): void
    {
        $tiene = Trainer::where('user_id', $request->user()->id)
            ->whereHas('assignments', fn ($q) => $q->where('member_id', $member->id)->whereNull('ended_at'))
            ->exists();

        abort_unless($tiene, 403, 'Este cliente no está a tu cargo.');
    }
}
