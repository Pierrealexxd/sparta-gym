<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\GymQrCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * QR de asistencia laboral de una sede. El token es una capability (UUID):
 * identifica la sucursal al escanear y nada más; no lleva datos de contacto
 * ni el gym_id. Rotar el QR revoca el anterior (is_active + revoked_at) para
 * que un papel perdido deje de valer al instante, sin borrar el histórico.
 */
class GymQrCodeController extends Controller
{
    public function mostrar(Gym $sede): View
    {
        return view('admin.sedes.qr', [
            'sede' => $sede,
            'qr'   => $sede->qrCodes()->vigente()->latest('id')->first(),
        ]);
    }

    /** Generar el primero o rotar el vigente: mismo POST, mismo efecto. */
    public function regenerar(Request $request, Gym $sede): RedirectResponse
    {
        $sede->qrCodes()->vigente()->update([
            'is_active'  => false,
            'revoked_at' => now(),
        ]);

        GymQrCode::create([
            'gym_id'     => $sede->id,
            'label'      => 'QR de asistencia',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('exito', 'QR generado. El anterior ya no funciona.');
    }

    /** Vista de impresión, autónoma como el carnet (patrón admin/clientes/carnet). */
    public function imprimir(Gym $sede): View
    {
        $qr = $sede->qrCodes()->vigente()->latest('id')->first();

        abort_if(! $qr, 404, 'Esta sede no tiene un QR de asistencia vigente.');

        return view('admin.sedes.qr-imprimir', ['sede' => $sede, 'qr' => $qr]);
    }
}
