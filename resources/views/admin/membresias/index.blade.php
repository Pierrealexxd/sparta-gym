@extends('layouts.panel')

@section('titulo', 'Membresías')
@section('subtitulo', $membresias->total() . ' registradas')

@section('contenido')
    <form class="panel__toolbar" method="GET">
        <div class="panel__busqueda">
            <x-icono nombre="lupa" />
            <input class="campo__control" type="search" name="q" value="{{ request('q') }}"
                   placeholder="Buscar por socio, código o plan…">
        </div>
        <select class="campo__control" name="estado" style="max-width:220px" onchange="this.form.submit()">
            <option value="">Todos los estados</option>
            @foreach (['activa' => 'Activa', 'vencida' => 'Vencida', 'cancelada' => 'Cancelada', 'congelada' => 'Congelada'] as $v => $l)
                <option value="{{ $v }}" @selected(request('estado') === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </form>

    <div class="tabla-envoltorio" data-revelar>
        <table class="tabla">
            <thead><tr><th>Socio</th><th>Plan</th><th>Periodo</th><th>Total</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @forelse ($membresias as $mem)
                    <tr>
                        <td class="es-fuerte">
                            @if ($mem->member)
                                <a href="{{ route('admin.clientes.show', $mem->member) }}">{{ $mem->member->full_name }}</a>
                            @else
                                <span style="color:var(--humo)">Socio no activo</span>
                            @endif
                        </td>
                        <td>{{ $mem->plan_name }}</td>
                        <td>{{ $mem->starts_at->format('d/m/y') }} – {{ $mem->ends_at->format('d/m/y') }}</td>
                        <td>S/ {{ number_format($mem->total, 2) }}</td>
                        <td><span class="estado estado--{{ $mem->status }}">{{ ucfirst($mem->status) }}</span></td>
                        <td>
                            @if ($mem->status === 'activa')
                                <button class="btn btn--desnudo" type="button"
                                        @click="$store.confirmar.abrir({
                                            accion: '{{ route('admin.membresias.cancelar', $mem) }}',
                                            metodo: 'POST',
                                            titulo: 'Cancelar membresía',
                                            mensaje: '¿Cancelar esta membresía? El socio deja de tener plan activo.',
                                            etiqueta: 'Cancelar'
                                        })">Cancelar</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="tabla__vacio"><x-estado-vacio icono="tarjetas" texto="Sin membresías registradas." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="paginacion">{{ $membresias->links() }}</div>

    <x-modal-confirmar />
@endsection
