@extends('layouts.panel')

@section('titulo', 'Rutinas')

@section('acciones')
    <a class="btn btn--fuego" href="{{ route('entrenador.rutinas.create') }}"><x-icono nombre="agregar" /> Nueva rutina</a>
@endsection

@section('contenido')
    <div class="tabla-envoltorio" data-revelar>
        <table class="tabla tabla--tarjetas">
            <thead><tr><th>Socio</th><th>Rutina</th><th>Objetivo</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse ($rutinas as $r)
                    <tr>
                        <td class="es-fuerte" data-etiqueta="Socio">{{ $r->member?->full_name }}</td>
                        <td data-etiqueta="Rutina">{{ $r->name }}</td>
                        <td class="tabla__oculta-movil" data-etiqueta="Objetivo">{{ $r->objective ?? '—' }}</td>
                        <td data-etiqueta="Estado"><span class="estado estado--{{ $r->status === 'activa' ? 'activo' : 'inactivo' }}">{{ ucfirst($r->status) }}</span></td>
                        <td data-etiqueta="nada"><a class="btn btn--desnudo" href="{{ route('entrenador.rutinas.show', $r) }}">Abrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="tabla__vacio" data-etiqueta="">Sin rutinas todavía.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="paginacion">{{ $rutinas->links() }}</div>
@endsection
