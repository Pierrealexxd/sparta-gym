{{-- Modal de detalle de programa (Fase 2). Mismo componente modal-info que
     guias.blade.php/beneficios.blade.php, con una caja más ancha
     (modal-info__caja--ancho) porque acá el contenido es más largo:
     descripción, características, rutina tipo y CTA. --}}
<div class="modal-info" x-cloak x-show="programaActivo !== null" @keydown.escape.window="programaActivo = null"
     role="dialog" aria-modal="true" :aria-label="programaActivo?.nombre">
    <div class="modal-info__fondo" x-show="programaActivo !== null" x-transition.opacity @click="programaActivo = null"></div>

    <div class="modal-info__caja modal-info__caja--ancho" x-show="programaActivo !== null"
         x-transition:enter="modal-info__entra" x-transition:enter-start="modal-info__entra-desde" x-transition:enter-end="modal-info__entra-hasta"
         x-transition:leave="modal-info__entra" x-transition:leave-start="modal-info__entra-hasta" x-transition:leave-end="modal-info__entra-desde">
        <button type="button" class="modal-info__cerrar" @click="programaActivo = null" aria-label="Cerrar">
            <x-icono nombre="cerrar" />
        </button>

        <template x-if="programaActivo">
            <div class="programa-detalle">
                <div class="programa-detalle__cabecera">
                    <div class="programa-detalle__icono">
                        <template x-if="programaActivo.icono === 'llama'"><x-icono nombre="llama" /></template>
                        <template x-if="programaActivo.icono === 'rayo'"><x-icono nombre="rayo" /></template>
                    </div>
                    <div>
                        <h3 class="programa-detalle__titulo" x-text="programaActivo.nombre"></h3>
                        <p class="programa-detalle__tagline" x-text="programaActivo.tagline"></p>
                    </div>
                </div>

                <div class="programa-detalle__descripcion" x-html="programaActivo.descripcion"></div>

                <ul class="programa-detalle__lista">
                    <template x-for="item in programaActivo.highlights" :key="item">
                        <li x-text="item"></li>
                    </template>
                </ul>

                <div class="programa-detalle__ejemplo" x-show="programaActivo.ejemplos.length">
                    <h4>Rutina tipo — ejemplo de un día</h4>
                    <div class="programa-detalle__ejercicios">
                        <template x-for="ej in programaActivo.ejemplos" :key="ej.nombre">
                            <div class="programa-detalle__ejercicio">
                                <span class="etiqueta" x-text="ej.categoria"></span>
                                <b x-text="ej.nombre"></b>
                                <small x-text="ej.equipo"></small>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="programa-detalle__acciones">
                    @auth
                        {{-- El plan original comparaba auth()->user()->rol->name === 'cliente',
                             que no existe (la relación es role(), por slug) — corregido a
                             esCliente() (User::esCliente, ver PROMPT-EJECUCION-PROGRAMAS.md). --}}
                        @if (auth()->user()->esCliente())
                            <form method="POST" action="{{ route('cliente.programa.asignar') }}" style="display:inline">
                                @csrf
                                <input type="hidden" name="program_slug" :value="programaActivo?.slug">
                                <button type="submit" class="btn btn--fuego">Agendar mi evaluación</button>
                            </form>
                        @else
                            {{-- Admin/recepción/entrenador autenticados no son clientes: no
                                 tiene sentido ofrecerles "agendar evaluación", los mandamos
                                 a su propio panel (User::rutaDeInicio, no cliente.dashboard fijo). --}}
                            <a href="{{ route(auth()->user()->rutaDeInicio()) }}" class="btn btn--fuego">Ver panel</a>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="btn btn--fuego">Empezar hoy</a>
                        <a href="#planes" class="btn btn--vidrio" @click="programaActivo = null">Ver planes</a>
                    @endauth
                </div>
            </div>
        </template>
    </div>
</div>
