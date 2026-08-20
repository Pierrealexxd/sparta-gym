{{-- Vista Lista de marcaciones laborales, alterna al Calendario vía
     <x-alterna-vista> (ver calendario.blade.php). Cada fila abre el modal
     de detalle (_detalle-marcacion.blade.php) vía el evento "abrir-detalle";
     el modal hace su propio fetch a admin.asistencia.detalle. --}}
<div class="tabla-envoltorio" data-revelar>
    <table class="tabla tabla--tarjetas">
        <thead>
            <tr>
                <th>Entrenador</th>
                <th>Fecha</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Turno</th>
                <th>Método</th>
                @if ($modoTodas) <th>Sede</th> @endif
                <th>GPS</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($marcaciones as $m)
                <tr class="tarjeta--interactiva" style="cursor:pointer"
                    @click="$dispatch('abrir-detalle', { url: '{{ route('admin.asistencia.detalle', $m) }}' })">
                    <td class="es-fuerte" data-etiqueta="Entrenador">{{ $m->user?->name ?? '—' }}</td>
                    <td data-etiqueta="Fecha">{{ $m->clocked_in_at->format('d/m/Y') }}</td>
                    <td data-etiqueta="Entrada">{{ $m->clocked_in_at->format('H:i') }}</td>
                    <td data-etiqueta="Salida">{{ $m->clocked_out_at?->format('H:i') ?? 'En curso' }}</td>
                    <td data-etiqueta="Turno">{{ $m->turno_legible }}</td>
                    <td data-etiqueta="Método"><span class="estado">{{ $m->method_legible }}</span></td>
                    @if ($modoTodas)
                        <td data-etiqueta="Sede"><span class="etiqueta">{{ $m->gym?->name ?? '—' }}</span></td>
                    @endif
                    <td data-etiqueta="GPS">
                        @if ($m->location_lat)
                            <span class="estado" style="color:var(--ok)" title="Marcado con ubicación">📍</span>
                        @else
                            <span style="color:var(--humo)">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $modoTodas ? 8 : 7 }}" class="tabla__vacio" data-etiqueta="">
                        <x-estado-vacio icono="entrada" texto="Sin marcaciones para este mes." />
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="paginacion">{{ $marcaciones->links() }}</div>
