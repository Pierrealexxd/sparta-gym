@extends('layouts.panel')

@section('titulo', $rutina->name)
@section('subtitulo', $rutina->member?->full_name . ' · ' . ($rutina->objective ?? 'Sin objetivo definido'))

@section('acciones')
    <a class="btn btn--vidrio" href="{{ route('entrenador.rutinas.edit', $rutina) }}"><x-icono nombre="lapiz" /> Editar</a>
@endsection

@section('contenido')
    <div x-data="{ diaAbrir: null }" style="display:grid;gap:var(--e-5)">
        @foreach ($rutina->days as $dia)
            <article class="tarjeta">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--e-4)">
                    <div>
                        <h3 style="font-size:var(--t-lg)">{{ $dia->name }}</h3>
                        @if ($dia->focus)<p style="color:var(--humo);font-size:var(--t-sm)">{{ $dia->focus }}</p>@endif
                    </div>
                    <button type="button" class="btn btn--desnudo"
                            @click="$store.confirmar.abrir({
                                accion: '{{ route('entrenador.dias.destroy', $dia) }}',
                                titulo: 'Eliminar día',
                                mensaje: '¿Eliminar el día {{ $dia->name }} y sus ejercicios?',
                                etiqueta: 'Eliminar'
                            })">
                        <x-icono nombre="papelera" />
                    </button>
                </div>

                <div class="tabla-envoltorio">
                    <table class="tabla tabla--tarjetas">
                        <thead><tr><th>Ejercicio</th><th>Prescripción</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($dia->exercises as $re)
                                <tr>
                                    <td class="es-fuerte" data-etiqueta="Ejercicio">{{ $re->exercise->name }}</td>
                                    <td data-etiqueta="Prescripción">{{ $re->prescripcion }}</td>
                                    <td data-etiqueta="nada">
                                        <form method="POST" action="{{ route('entrenador.ejercicios.destroy', $re) }}">
                                            @csrf @method('DELETE')
                                            <button class="btn btn--desnudo" type="submit"><x-icono nombre="cerrar" /></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="tabla__vacio" data-etiqueta="">Sin ejercicios en este día.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <details style="margin-top:var(--e-4)">
                    <summary class="btn btn--desnudo" style="cursor:pointer">+ Agregar ejercicio</summary>
                    <form method="POST" action="{{ route('entrenador.dias.ejercicios.store', $dia) }}" class="formulario-panel" style="margin-top:var(--e-4)">
                        @csrf
                        <div class="formulario-panel__fila">
                            <label class="campo"><span class="campo__etiqueta">Ejercicio</span>
                                <select class="campo__control" name="exercise_id" required>
                                    @foreach ($ejercicios as $ej)<option value="{{ $ej->id }}">{{ $ej->name }}</option>@endforeach
                                </select></label>
                            <label class="campo"><span class="campo__etiqueta">Series</span>
                                <input class="campo__control" type="number" name="sets" value="4" required></label>
                            <label class="campo"><span class="campo__etiqueta">Repeticiones</span>
                                <input class="campo__control" type="text" name="reps" placeholder="8-10"></label>
                            <label class="campo"><span class="campo__etiqueta">Peso (kg)</span>
                                <input class="campo__control" type="number" step="0.5" name="weight_kg"></label>
                            <label class="campo"><span class="campo__etiqueta">Descanso (s)</span>
                                <input class="campo__control" type="number" name="rest_seconds" value="60"></label>
                        </div>
                        <button class="btn btn--fuego" type="submit">Agregar</button>
                    </form>
                </details>
            </article>
        @endforeach

        <details class="tarjeta" style="padding:var(--e-5)">
            <summary class="btn btn--desnudo" style="cursor:pointer">+ Agregar día de entrenamiento</summary>
            <form method="POST" action="{{ route('entrenador.rutinas.dias.store', $rutina) }}" class="formulario-panel" style="margin-top:var(--e-4)">
                @csrf
                <div class="formulario-panel__fila">
                    <label class="campo"><span class="campo__etiqueta">Nombre</span>
                        <input class="campo__control" type="text" name="name" placeholder="Día 1 · Empuje" required></label>
                    <label class="campo"><span class="campo__etiqueta">Enfoque</span>
                        <input class="campo__control" type="text" name="focus" placeholder="Pecho y tríceps"></label>
                </div>
                <button class="btn btn--fuego" type="submit">Agregar día</button>
            </form>
        </details>
    </div>

    <x-modal-confirmar />
@endsection
