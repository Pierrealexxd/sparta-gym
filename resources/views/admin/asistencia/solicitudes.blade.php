@extends('layouts.panel')

@section('titulo', 'Solicitudes de corrección')
{{-- ->total() y no ->count(): con paginate(), count() solo cuenta las
     filas de la página actual, no el total de solicitudes pendientes. --}}
@section('subtitulo', $estado === 'pendientes'
    ? $solicitudes->total() . ' pendientes de aprobar'
    : 'Solicitudes ya revisadas')

@section('contenido')
    @include('admin.asistencia._pestanas')

    <nav class="pestanas__nav pestanas__nav--ancha" style="margin-bottom:var(--e-5)">
        <a class="pestanas__enlace" href="{{ route('admin.asistencia.solicitudes.index') }}"
           aria-current="{{ $estado === 'pendientes' ? 'true' : 'false' }}">Pendientes</a>
        <a class="pestanas__enlace" href="{{ route('admin.asistencia.solicitudes.index', ['estado' => 'historial']) }}"
           aria-current="{{ $estado === 'historial' ? 'true' : 'false' }}">Historial</a>
    </nav>

    <div class="tabla-envoltorio" data-revelar>
        <table class="tabla tabla--tarjetas">
            <thead>
                <tr>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th>Quién</th>
                    <th>Solicitante</th>
                    @if ($modoTodas)
                        <th>Sede</th>
                    @endif
                    <th>Entrada actual</th>
                    <th>Propuesta</th>
                    <th>Motivo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($solicitudes as $s)
                    @php
                        $objetivo = $s->objeto === 'cliente' ? $s->attendance : $s->staffAttendance;
                        $actual   = $objetivo?->checked_in_at ?? $objetivo?->clocked_in_at;
                    @endphp
                    <tr>
                        <td data-etiqueta="Estado">
                            @if ($s->status === 'pendiente')
                                <span class="estado estado--pendiente">Pendiente</span>
                            @elseif ($s->status === 'aprobada')
                                <span class="estado" style="color:var(--ok)">Aprobada</span>
                            @else
                                <span class="estado" style="color:var(--humo)">Rechazada</span>
                            @endif
                        </td>
                        <td style="color:var(--ceniza)" data-etiqueta="Fecha">{{ $s->created_at->format('d M H:i') }}</td>
                        <td class="es-fuerte" data-etiqueta="Quién">
                            @if ($s->objeto === 'cliente')
                                {{ $s->attendance?->member?->full_name ?? '—' }}
                                <span class="estado" style="color:var(--bronce)">Asistencia</span>
                            @else
                                {{ $s->staffAttendance?->user?->name ?? $actual?->format('d M H:i') ?? '—' }}
                                <span class="estado">Marcación</span>
                            @endif
                        </td>
                        <td class="es-fuerte" data-etiqueta="Solicitante">{{ $s->requestedBy?->name ?? '—' }}</td>
                        @if ($modoTodas)
                            <td style="color:var(--ceniza)" data-etiqueta="Sede">{{ $s->gym?->name ?? '—' }}</td>
                        @endif
                        <td style="color:var(--ceniza)" data-etiqueta="Entrada actual">
                            {{ $actual?->format('d M H:i') ?? '—' }}
                        </td>
                        <td data-etiqueta="Propuesta">
                            @if ($s->es_eliminacion)
                                <span style="color:var(--sangre-viva)">Eliminar registro</span>
                            @else
                                <span style="color:var(--bronce)">
                                    {{ $s->checked_in_at->format('d M H:i') }}
                                    @if ($s->checked_out_at) — {{ $s->checked_out_at->format('H:i') }} @endif
                                </span>
                            @endif
                        </td>
                        <td style="color:var(--ceniza)" data-etiqueta="Motivo">{{ $s->reason ?? '—' }}</td>
                        <td data-etiqueta="nada">
                            @if ($s->status === 'pendiente')
                                <form method="POST" action="{{ route('admin.asistencia.solicitudes.aprobar', $s) }}">
                                    @csrf
                                    <button class="btn btn--fuego" type="submit">Aprobar</button>
                                </form>
                                <form method="POST" action="{{ route('admin.asistencia.solicitudes.rechazar', $s) }}">
                                    @csrf
                                    <button class="btn btn--vidrio" type="submit">Rechazar</button>
                                </form>
                            @else
                                <span style="color:var(--humo);font-size:var(--t-xs)">{{ $s->reviewed_at?->format('d M H:i') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;color:var(--humo)">
                            {{ $estado === 'pendientes' ? 'No hay solicitudes pendientes.' : 'Todavía no hay solicitudes revisadas.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="paginacion">{{ $solicitudes->links() }}</div>
@endsection
