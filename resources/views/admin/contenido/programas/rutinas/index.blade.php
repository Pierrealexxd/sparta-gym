@extends('layouts.panel')

@section('titulo', 'Rutinas base · ' . $programa->name)
@section('subtitulo', $rutinas->count() . ' plantillas')

@section('acciones')
    <div style="display:flex;gap:var(--e-3)">
        <a class="btn btn--vidrio" href="{{ route('admin.programas.index') }}">
            <x-icono nombre="flecha-der" style="transform:rotate(180deg)" /> Volver a programas
        </a>
        <a class="btn btn--fuego" href="{{ route('admin.programas.rutinas.create', $programa) }}">
            <x-icono nombre="agregar" /> Nueva rutina base
        </a>
    </div>
@endsection

@section('contenido')
    <div class="tabla-envoltorio" data-revelar>
        <table class="tabla tabla--tarjetas">
            <thead><tr><th>Rutina</th><th>Días</th><th></th></tr></thead>
            <tbody>
                @forelse ($rutinas as $rutina)
                    <tr>
                        <td class="es-fuerte" data-etiqueta="Rutina">{{ $rutina->name }}</td>
                        <td data-etiqueta="Días">{{ $rutina->days_count }}</td>
                        <td data-etiqueta="nada">
                            <div style="display:flex;gap:var(--e-2)">
                                <a class="btn btn--desnudo" title="Editar" href="{{ route('admin.programas.rutinas.edit', [$programa, $rutina]) }}">
                                    <x-icono nombre="lapiz" />
                                </a>
                                <button class="btn btn--desnudo" type="button" title="Eliminar"
                                        @click="$store.confirmar.abrir({
                                            accion: '{{ route('admin.programas.rutinas.destroy', [$programa, $rutina]) }}',
                                            titulo: 'Eliminar rutina base',
                                            mensaje: '¿Eliminar esta rutina base y todos sus días y ejercicios?',
                                            etiqueta: 'Eliminar'
                                        })">
                                    <x-icono nombre="papelera" />
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="tabla__vacio"><x-estado-vacio icono="lista" texto="Sin rutinas base todavía. El botón 'Agendar mi evaluación' de la landing no podrá asignar nada hasta que crees al menos una." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-modal-confirmar />
@endsection
