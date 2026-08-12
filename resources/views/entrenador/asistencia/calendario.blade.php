@extends('layouts.panel')

@section('titulo', 'Asistencia')
@section('subtitulo', 'Las asistencias de clientes que registraste, mes a mes')

@section('contenido')
    @include('entrenador.asistencia._pestanas')

    <div x-data="{ registrarAbierto: false }">
        <div class="panel__toolbar" style="justify-content:flex-end;margin-bottom:var(--e-4)">
            <button class="btn btn--fuego" type="button" @click="registrarAbierto = true">
                <x-icono nombre="entrada" /> Registrar asistencia
            </button>
        </div>

        <x-calendario ruta="entrenador.asistencia.calendario" :anterior="$anterior" :siguiente="$siguiente"
                      :celdas="$celdas" contador-texto="asistencia">
            @foreach ($porDia as $fecha => $lista)
                <div x-show="diaAbierto === '{{ $fecha }}'" x-cloak class="calendario__lista">
                    @foreach ($lista->sortByDesc('checked_in_at') as $a)
                        <article class="calendario__rutina" x-data="{ corregir: false }">
                            <div>
                                <b class="es-fuerte" style="color:var(--hueso)">{{ $a->member?->full_name ?? '—' }}</b>
                                <span class="calendario__meta">
                                    {{ $a->checked_out_at ? 'Salió ' . $a->checked_out_at->format('H:i') : 'Sigue dentro' }}
                                    · {{ ucfirst($a->method) }}
                                </span>
                            </div>
                            <time class="calendario__hora">{{ $a->checked_in_at->format('H:i') }}</time>

                            <div style="margin-top:var(--e-2);display:flex;flex-direction:column;gap:var(--e-2);align-items:flex-start">
                                @unless ($a->checked_out_at)
                                    <form method="POST" action="{{ route('entrenador.asistencia.salida', $a) }}">
                                        @csrf
                                        <button class="btn btn--desnudo" type="submit">Marcar salida</button>
                                    </form>
                                @endunless
                                <button class="btn btn--desnudo" type="button" @click="corregir = !corregir"
                                        x-text="corregir ? 'Cancelar corrección' : 'Solicitar corrección'"></button>

                                {{-- Solicitar corrección: los horarios propuestos reemplazan a los
                                     reales cuando el admin aprueba (ver AsistenciaService). --}}
                                <form method="POST" action="{{ route('entrenador.asistencia.solicitar-correccion', $a) }}"
                                      x-show="corregir" x-cloak class="formulario-panel"
                                      style="border-top:1px solid var(--acero);padding-top:var(--e-3);width:100%">
                                    @csrf
                                    <label class="campo">
                                        <span class="campo__etiqueta">Entrada propuesta</span>
                                        <input class="campo__control" type="datetime-local" name="checked_in_at"
                                               value="{{ $a->checked_in_at->format('Y-m-d\TH:i') }}" required>
                                    </label>
                                    <label class="campo">
                                        <span class="campo__etiqueta">Salida propuesta</span>
                                        <input class="campo__control" type="datetime-local" name="checked_out_at"
                                               value="{{ $a->checked_out_at?->format('Y-m-d\TH:i') }}">
                                    </label>
                                    <label class="campo">
                                        <span class="campo__etiqueta">Motivo</span>
                                        <input class="campo__control" type="text" name="reason" maxlength="255" placeholder="Opcional">
                                    </label>
                                    <button class="btn btn--fuego" type="submit">Enviar solicitud</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endforeach
        </x-calendario>

        {{-- Modal de registro: buscador de mis clientes asignados + alta. --}}
        <div class="modal__fondo" x-show="registrarAbierto" x-cloak @keydown.escape.window="registrarAbierto = false">
            <form class="tarjeta modal__caja" method="POST" action="{{ route('entrenador.asistencia.registrar') }}"
                  @click.outside="registrarAbierto = false"
                  x-data="{
                      buscarQ: '', resultados: [], seleccionado: null,
                      buscarCliente() {
                          if (this.buscarQ.trim().length < 2) { this.resultados = []; return; }
                          fetch('{{ route('entrenador.asistencia.clientes') }}?q=' + encodeURIComponent(this.buscarQ))
                              .then(r => r.json())
                              .then(d => this.resultados = d)
                              .catch(() => this.resultados = []);
                      },
                      elegirCliente(m) {
                          this.seleccionado = m;
                          this.buscarQ = m.full_name + (m.code ? ' (' + m.code + ')' : '');
                          this.resultados = [];
                      }
                  }"
                  style="display:grid;gap:var(--e-4)">
                @csrf
                <input type="hidden" name="member_id" :value="seleccionado?.id ?? ''">

                <div class="modal__cabecera">
                    <h3>Registrar asistencia de cliente</h3>
                    <button class="modal__cerrar" type="button" @click="registrarAbierto = false"><x-icono nombre="cerrar" /></button>
                </div>

                @error('member_id')<span class="campo__error">{{ $message }}</span>@enderror

                <x-buscador-cliente etiqueta="Cliente" placeholder="Buscar por nombre, código o documento…" />

                <div class="formulario-panel__acciones">
                    <button class="btn btn--fuego" type="submit" :disabled="!seleccionado">Registrar entrada</button>
                </div>
            </form>
        </div>
    </div>
@endsection
