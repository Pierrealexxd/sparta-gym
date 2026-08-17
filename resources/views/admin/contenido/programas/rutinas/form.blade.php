@extends('layouts.panel')

@section('titulo', $rutina->exists ? 'Editar rutina base' : 'Nueva rutina base')
@section('subtitulo', $programa->name)

@section('acciones')
    <a class="btn btn--vidrio" href="{{ route('admin.programas.rutinas.index', $programa) }}">Volver</a>
@endsection

@section('contenido')
    <form class="tarjeta formulario-panel" method="POST"
          action="{{ $rutina->exists ? route('admin.programas.rutinas.update', [$programa, $rutina]) : route('admin.programas.rutinas.store', $programa) }}">
        @csrf
        @if ($rutina->exists) @method('PUT') @endif

        <label class="campo"><span class="campo__etiqueta">Nombre de la rutina</span>
            <input class="campo__control" type="text" name="name" required value="{{ old('name', $rutina->name) }}"></label>

        <label class="campo"><span class="campo__etiqueta">Notas (opcional)</span>
            <textarea class="campo__control" name="notes">{{ old('notes', $rutina->notes) }}</textarea></label>

        <div class="formulario-panel__acciones">
            <a class="btn btn--vidrio" href="{{ route('admin.programas.rutinas.index', $programa) }}">Cancelar</a>
            <button class="btn btn--fuego" type="submit">Guardar</button>
        </div>
    </form>

    @if ($rutina->exists)
        {{-- Días y ejercicios se administran una vez creada la rutina, con
             el mismo patrón de formularios simples de entrenador/rutinas/show
             (ver nota en routes/admin.php: el plan pedía un único formulario
             Alpine con arrays anidados, pero el código real no funciona así). --}}
        <div style="display:grid;gap:var(--e-5);margin-top:var(--e-5)">
            @foreach ($rutina->days as $dia)
                <article class="tarjeta" x-data="{ editandoGuia: null }">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--e-4)">
                        <div>
                            <h3 style="font-size:var(--t-lg)">{{ $dia->name }}</h3>
                            @if ($dia->focus)<p style="color:var(--humo);font-size:var(--t-sm)">{{ $dia->focus }}</p>@endif
                        </div>
                        <button type="button" class="btn btn--desnudo"
                                @click="$store.confirmar.abrir({
                                    accion: '{{ route('admin.programas.rutinas.dias.destroy', $dia) }}',
                                    titulo: 'Eliminar día',
                                    mensaje: '¿Eliminar el día {{ $dia->name }} y sus ejercicios?',
                                    etiqueta: 'Eliminar'
                                })">
                            <x-icono nombre="papelera" />
                        </button>
                    </div>

                    <div class="tabla-envoltorio">
                        <table class="tabla tabla--tarjetas">
                            <thead><tr><th>Ejercicio</th><th>Prescripción</th><th>Guía</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($dia->exercises as $pe)
                                    <tr>
                                        <td class="es-fuerte" data-etiqueta="Ejercicio">{{ $pe->exercise->name }}</td>
                                        <td data-etiqueta="Prescripción">{{ $pe->prescripcion }}</td>
                                        {{-- FASE 1.6 de PLAN-GUIAS-EJERCICIO.md: indicador visual de override. --}}
                                        <td data-etiqueta="Guía">
                                            @if ($pe->tiene_guia_personalizada)
                                                <span class="estado estado--activo">Personalizada</span>
                                            @else
                                                <span style="color:var(--humo);font-size:var(--t-sm)">Hereda del ejercicio</span>
                                            @endif
                                        </td>
                                        <td data-etiqueta="nada">
                                            <div style="display:flex;gap:var(--e-2)">
                                                <button type="button" class="btn btn--desnudo" title="Editar guía"
                                                        @click="editandoGuia = (editandoGuia === {{ $pe->id }} ? null : {{ $pe->id }})">
                                                    <x-icono nombre="lapiz" />
                                                </button>
                                                <form method="POST" action="{{ route('admin.programas.rutinas.ejercicios.destroy', $pe) }}">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn--desnudo" type="submit"><x-icono nombre="cerrar" /></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr x-show="editandoGuia === {{ $pe->id }}" x-cloak>
                                        <td colspan="4" data-etiqueta="">
                                            {{-- Guía personalizada solo para esta rutina: en blanco, hereda del
                                                 Exercise (ver trait HasExerciseGuideOverrides). --}}
                                            <form method="POST" action="{{ route('admin.programas.rutinas.ejercicios.guia', $pe) }}" class="formulario-panel" style="padding:var(--e-3) 0">
                                                @csrf @method('PUT')
                                                <div class="formulario-panel__fila">
                                                    <label class="campo"><span class="campo__etiqueta">Fuente de video</span>
                                                        <select class="campo__control" name="guide_video_source">
                                                            @foreach (['youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'gdrive' => 'Google Drive', 'url' => 'URL de incrustación'] as $valor => $etiqueta)
                                                                <option value="{{ $valor }}" @selected($pe->guide_video_source === $valor)>{{ $etiqueta }}</option>
                                                            @endforeach
                                                        </select></label>
                                                    <label class="campo"><span class="campo__etiqueta">Enlace del video (opcional)</span>
                                                        <input class="campo__control" type="url" name="guide_video_url" placeholder="Vacío = usa el video del ejercicio" value="{{ $pe->guide_video_url }}"></label>
                                                </div>
                                                <label class="campo"><span class="campo__etiqueta">Descripción para este programa (opcional)</span>
                                                    <textarea class="campo__control" name="guide_description" placeholder="Vacío = usa la descripción del ejercicio">{{ $pe->guide_description }}</textarea></label>
                                                <div class="formulario-panel__fila">
                                                    <label class="campo"><span class="campo__etiqueta">Hazlo así (opcional)</span>
                                                        <textarea class="campo__control" name="guide_tips" placeholder="Vacío = hereda del ejercicio">{{ $pe->guide_tips }}</textarea></label>
                                                    <label class="campo"><span class="campo__etiqueta">Evita (opcional)</span>
                                                        <textarea class="campo__control" name="guide_common_mistakes" placeholder="Vacío = hereda del ejercicio">{{ $pe->guide_common_mistakes }}</textarea></label>
                                                </div>
                                                <div class="formulario-panel__acciones">
                                                    <button class="btn btn--vidrio" type="button" @click="editandoGuia = null">Cerrar</button>
                                                    <button class="btn btn--fuego" type="submit">Guardar guía</button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="tabla__vacio" data-etiqueta="">Sin ejercicios en este día.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <details style="margin-top:var(--e-4)">
                        <summary class="btn btn--desnudo" style="cursor:pointer">+ Agregar ejercicio</summary>
                        <form method="POST" action="{{ route('admin.programas.rutinas.dias.ejercicios.store', $dia) }}" class="formulario-panel" style="margin-top:var(--e-4)">
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
                <form method="POST" action="{{ route('admin.programas.rutinas.dias.store', [$programa, $rutina]) }}" class="formulario-panel" style="margin-top:var(--e-4)">
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
    @endif
@endsection
