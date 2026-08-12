@extends('layouts.panel')

@section('titulo', 'QR de asistencia')
@section('subtitulo', $sede->name)

@section('acciones')
    <a class="btn btn--vidrio" href="{{ route('admin.sedes.index') }}"><x-icono nombre="flecha-der" style="transform:rotate(180deg)" /> Volver</a>
@endsection

@section('contenido')
    <article class="tarjeta" style="padding:var(--e-6);max-width:420px" data-revelar>
        @if ($qr)
            <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-4)">
                Lo escanean los entrenadores para marcar su asistencia en esta sede.
                El QR solo contiene un token de acceso; no lleva datos de la sede.
            </p>

            {{-- El canvas lo pinta iniciarQr() de app.js (qrcode + tokens). --}}
            <canvas data-qr="{{ $qr->token }}" data-qr-tamano="260"
                    style="max-width:100%;height:auto;background:#fff;border-radius:var(--r-2);padding:var(--e-2)"></canvas>
            <p style="font-family:var(--f-mono);font-size:var(--t-xs);color:var(--ceniza);word-break:break-all;margin-top:var(--e-4)">{{ $qr->token }}</p>

            <div class="formulario-panel__acciones" style="margin-top:var(--e-5)">
                <a class="btn btn--fuego" href="{{ route('admin.sedes.qr.imprimir', $sede) }}">
                    <x-icono nombre="descargar" /> Imprimir / Guardar como PDF
                </a>
                <button class="btn btn--vidrio" type="button"
                        @click="$store.confirmar.abrir({
                            accion: '{{ route('admin.sedes.qr.regenerar', $sede) }}',
                            metodo: 'POST',
                            titulo: 'Regenerar QR',
                            mensaje: 'El QR impreso actual dejará de funcionar al instante. ¿Regenerar?',
                            etiqueta: 'Regenerar'
                        })">
                    Regenerar
                </button>
            </div>
        @else
            <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-4)">
                Esta sede aún no tiene QR de asistencia. Al generarlo podés imprimirlo
                y colocarlo en la recepción para que los entrenadores marquen con la cámara.
            </p>
            <form method="POST" action="{{ route('admin.sedes.qr.regenerar', $sede) }}">
                @csrf
                <button class="btn btn--fuego" type="submit"><x-icono nombre="qr" /> Generar QR de asistencia</button>
            </form>
        @endif
    </article>

    <x-modal-confirmar />
@endsection
