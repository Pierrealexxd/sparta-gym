{{-- Modal de escaneo QR de asistencia laboral. El componente Alpine
     escaneoQr (resources/js/escaneo-qr.js) orquesta estados, cámara y POST.
     El backend decide entrada/salida; este modal solo pregunta el estado para
     saber si pedir turno o no, y muestra el resultado del POST. --}}
<div class="modal__fondo"
     x-data="escaneoQr({
         rutaEstado: '{{ route('entrenador.asistencia.estado') }}',
         rutaQr: '{{ route('entrenador.asistencia.qr') }}',
     })"
     x-show="abierto" x-cloak
     @keydown.escape.window="cerrar()"
     @abrir-escaneo-qr.window="abrir()">
    <div class="tarjeta modal__caja" style="max-width:24rem" @click.outside="cerrar()">
        <div class="modal__cabecera">
            <h3 style="font-size:var(--t-lg)">Escanear QR</h3>
            <button class="modal__cerrar" type="button" @click="cerrar()" aria-label="Cerrar">
                <x-icono nombre="cerrar" />
            </button>
        </div>

        {{-- Verificando permiso de ubicación --}}
        <template x-if="estado === 'verificando-ubicacion'">
            <p style="color:var(--ceniza)">Verificando ubicación…</p>
        </template>

        {{-- Ubicación bloqueada: permiso denegado o GPS no disponible --}}
        <template x-if="estado === 'ubicacion-bloqueada'">
            <div>
                <p style="color:var(--hueso);font-size:var(--t-sm);margin-bottom:var(--e-3)">
                    La ubicación es obligatoria para marcar asistencia.
                </p>
                <template x-if="!mensaje">
                    <div style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-3)">
                        <p style="margin-bottom:var(--e-2)"><b style="color:var(--hueso)">En iPhone:</b></p>
                        <ol style="padding-left:1.2em;margin:0">
                            <li>Abrí <b>Ajustes</b></li>
                            <li>Id a <b>Privacidad y seguridad</b></li>
                            <li>Id a <b>Servicios de ubicación</b></li>
                            <li>Buscá <b>Safari</b> (o tu navegador)</li>
                            <li>Seleccioná <b>"Al usar la app"</b> o <b>"Siempre"</b></li>
                        </ol>
                    </div>
                </template>
                <template x-if="mensaje">
                    <p style="color:var(--sangre-viva);font-size:var(--t-sm);margin-bottom:var(--e-3)" x-text="mensaje"></p>
                </template>
                <div style="display:flex;gap:var(--e-2)">
                    <button class="btn btn--fuego" type="button" @click="reintentarUbicacion()" style="flex:1">Reintentar</button>
                    <button class="btn btn--vidrio" type="button" @click="cerrar()" style="flex:1">Cerrar</button>
                </div>
            </div>
        </template>

        {{-- Estado inicial: consultando el fichaje de hoy --}}
        <template x-if="estado === 'pidiendo' || estado === 'preparando'">
            <p style="color:var(--ceniza)">Preparando la cámara…</p>
        </template>

        {{-- Cámara encendida. Si no está en turno, primero elige turno. --}}
        <template x-if="estado === 'turno' || estado === 'leyendo'">
            <div>
                <template x-if="enTurno">
                    <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-3)">
                        Escaneá para marcar tu <b style="color:var(--hueso)">salida</b>
                        <span x-show="horaEntrada" x-text="' (entraste a las ' + horaEntrada + ')'"></span>.
                    </p>
                </template>
                <template x-if="!enTurno">
                    <div style="margin-bottom:var(--e-3)">
                        <p style="color:var(--ceniza);font-size:var(--t-sm);margin-bottom:var(--e-2)">
                            Escaneá para marcar tu <b style="color:var(--hueso)">entrada</b>. Elegí el turno:
                        </p>
                        {{-- flex-wrap + flex:1 en los botones (mismo patrón que
                             mi-marcacion.blade.php): sin esto, los 3 botones
                             (~360px) no caben en los ~208px de contenido que
                             deja el modal en un viewport de 320px. --}}
                        <div style="display:flex;flex-wrap:wrap;gap:var(--e-2)">
                            <button type="button" class="btn" style="flex:1;min-width:5.5rem" :class="turno === 'manana' ? 'btn--fuego' : 'btn--vidrio'"
                                    @click="elegirTurno('manana')">Mañana</button>
                            <button type="button" class="btn" style="flex:1;min-width:5.5rem" :class="turno === 'tarde' ? 'btn--fuego' : 'btn--vidrio'"
                                    @click="elegirTurno('tarde')">Tarde</button>
                            <button type="button" class="btn" style="flex:1;min-width:5.5rem" :class="turno === 'doble' ? 'btn--fuego' : 'btn--vidrio'"
                                    @click="elegirTurno('doble')">Doble</button>
                        </div>
                    </div>
                </template>

                <div style="position:relative;border-radius:var(--r-2);overflow:hidden;background:#000;aspect-ratio:1/1">
                    <video x-ref="video" style="width:100%;height:100%;object-fit:cover"></video>
                    {{-- Marquita estática: el QR debe entrar en el recuadro --}}
                    <span style="position:absolute;inset:12%;border:2px solid var(--brasa);border-radius:var(--r-2);opacity:.8;pointer-events:none"></span>
                </div>
                <p style="color:var(--ceniza);font-size:var(--t-xs);margin-top:var(--e-2)">
                    Apuntá al QR impreso en la sede. La marcación manual sigue disponible si la cámara falla.
                </p>
            </div>
        </template>

        {{-- Registrando la marcación --}}
        <template x-if="estado === 'procesando'">
            <p style="color:var(--ceniza)">Registrando la marcación…</p>
        </template>

        {{-- Resultado del backend --}}
        <template x-if="estado === 'listo' && resultado">
            <div>
                <p style="font-size:var(--t-lg)">
                    <b style="color:var(--hueso)" x-text="resultado.tipo === 'salida' ? 'Salida' : 'Entrada'"></b>
                    marcada a las <b style="color:var(--hueso)" x-text="resultado.hora"></b>
                </p>
                <p style="color:var(--ceniza);font-size:var(--t-sm)">
                    <span x-text="resultado.nombre"></span>
                    <span x-show="resultado.dni"> · DNI <span x-text="resultado.dni"></span></span>
                </p>
                <p style="color:var(--ceniza);font-size:var(--t-sm)">Sede: <span x-text="resultado.sede"></span></p>
                <div class="formulario-panel__acciones">
                    <button class="btn btn--fuego" type="button" @click="recargar()">Listo</button>
                </div>
            </div>
        </template>

        {{-- Error legible; la marcación manual sigue disponible --}}
        <template x-if="estado === 'error'">
            <div>
                <p style="color:var(--sangre-viva)" x-text="mensaje"></p>
                <div class="formulario-panel__acciones">
                    <button class="btn btn--vidrio" type="button" @click="cerrar()">Cerrar</button>
                </div>
            </div>
        </template>
    </div>
</div>
