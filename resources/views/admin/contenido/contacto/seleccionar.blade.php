@extends('layouts.panel')

@section('titulo', 'Contenido web')
@section('subtitulo', 'Contacto de la página pública')

@section('contenido')
    @include('admin.contenido._pestanas')

    <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-4)">
        Elegí la sede cuyo contacto querés editar.
    </p>

    <div class="tarjetas" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:var(--e-4)">
        @foreach ($sedes as $sede)
            <a href="{{ route('admin.contenido.contacto', $sede->id) }}"
               class="tarjeta tarjeta--interactiva"
               style="display:grid;gap:var(--e-2);padding:var(--e-4);text-decoration:none;color:var(--hueso)">
                <h3 style="font-size:var(--t-lg);margin:0">{{ $sede->name }}</h3>
                @if ($sede->address)
                    <p style="color:var(--ceniza);font-size:var(--t-sm);margin:0">{{ $sede->address }}</p>
                @endif
                @if ($sede->phone)
                    <p style="color:var(--ceniza);font-size:var(--t-sm);margin:0;font-family:var(--f-mono)">{{ $sede->phone }}</p>
                @endif
                <span style="font-size:var(--t-xs);color:var(--brasa);margin-top:var(--e-2)">Editar contacto →</span>
            </a>
        @endforeach
    </div>
@endsection
