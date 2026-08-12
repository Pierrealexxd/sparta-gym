/**
 * escaneo-qr.js
 * --------------------------------------------------------------
 * Cámara del entrenador para marcar asistencia con el QR de la sede.
 *
 * Flujo del modal (ver entrenador/asistencia/_escaneo-qr.blade.php):
 *   pidiendo → preparando → (turno | leyendo) → procesando → listo | error
 *
 * La decisión entrada/salida siempre la toma el backend (AsistenciaService).
 * Este módulo solo consulta el estado (para pedir turno o no) y manda
 * { token, turno } al POST. El gym_id nunca sale de aquí: lo define el QR.
 *
 * El stream se detiene en cuanto hay resultado o se cierra el modal — la
 * luz de la cámara no puede quedar encendida en segundo plano.
 */
import Alpine from 'alpinejs';
import jsQR from 'jsqr';

document.addEventListener('alpine:init', () => {
    Alpine.data('escaneoQr', (config) => ({
        abierto: false,
        estado: 'pidiendo',   // pidiendo | preparando | turno | leyendo | procesando | listo | error
        mensaje: '',
        enTurno: false,
        horaEntrada: null,
        turno: 'manana',
        resultado: null,

        rutaEstado: config.rutaEstado ?? '',
        rutaQr: config.rutaQr ?? '',

        stream: null,
        arrancando: false,
        bucle: null,
        canvas: null,

        abrir() {
            this.resultado = null;
            this.mensaje = '';
            this.arrancando = false;
            this.abierto = true;
            this.estado = 'preparando';

            // La cámara se pide DENTRO del clic (gesto de usuario): en móvil,
            // getUserMedia tras un await (el fetch del estado) no muestra el
            // permiso y deja el modal clavado en "Preparando la cámara…". El
            // turno se decide cuando llegue el estado, no antes.
            this.arrancarCamara();
            this.consultarEstado();
        },

        cerrar() {
            this.detener();
            this.abierto = false;
        },

        async consultarEstado() {
            try {
                const res = await fetch(this.rutaEstado);
                const data = await res.json();
                if (!this.abierto) return; // cerraron el modal mientras se consultaba
                this.enTurno = !!data.abierta;
                this.horaEntrada = data.horaEntrada ?? null;
                if (this.stream) {
                    // cámara ya arriba: el estado decide si pedir turno o leer
                    this.estado = this.enTurno ? 'leyendo' : 'turno';
                } else {
                    // la cámara aún no arrancó (p. ej. el gesto no la inició)
                    this.arrancarCamara();
                }
            } catch (error) {
                if (!this.abierto) return;
                this.estado = 'error';
                this.mensaje = 'No se pudo consultar tu estado. Revisá tu conexión e intentá de nuevo.';
            }
        },

        async arrancarCamara() {
            if (this.arrancando || this.stream) return;
            if (!navigator.mediaDevices?.getUserMedia) {
                this.estado = 'error';
                this.mensaje = 'Tu navegador no permite usar la cámara. Usa la marcación manual.';
                return;
            }

            this.arrancando = true;
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' } },
                    audio: false,
                });
                this.arrancando = false;
                if (!this.abierto) { // cerraron el modal mientras se pedía el permiso
                    stream.getTracks().forEach((t) => t.stop());
                    return;
                }
                this.stream = stream;
                this.estado = this.enTurno ? 'leyendo' : 'turno';
                this.$nextTick(() => this.empezarLectura());
            } catch (error) {
                this.arrancando = false;
                if (!this.abierto) return;
                this.detener();
                this.estado = 'error';
                this.mensaje = this.mensajeCamara(error);
            }
        },

        /** Elegir turno arranca la lectura: sin esto el escáner quedaría clavado. */
        elegirTurno(turno) {
            this.turno = turno;
            this.estado = 'leyendo';
        },

        mensajeCamara(error) {
            if (error?.name === 'NotAllowedError' || error?.name === 'SecurityError') {
                return 'No se puede acceder a la cámara. Concede el permiso en tu navegador e inténtalo de nuevo.';
            }
            if (error?.name === 'NotReadableError') {
                return 'La cámara está siendo usada por otra aplicación. Cerrá la otra pestaña e intentá de nuevo.';
            }
            return 'No se pudo iniciar la cámara. Vuelve a intentarlo.';
        },

        empezarLectura() {
            const video = this.$refs.video;
            if (!video) return;

            video.srcObject = this.stream;
            video.setAttribute('playsinline', '');
            video.muted = true;
            video.play().catch(() => {});

            this.bucle = requestAnimationFrame(() => this.leer());
        },

        leer() {
            const video = this.$refs.video;
            // Solo decodifica en estado 'leyendo': el turno se elige con la
            // cámara ya encendida, sin arriesgar una lectura accidental antes.
            if (!video || this.estado !== 'leyendo' || video.readyState !== video.HAVE_ENOUGH_DATA || video.videoWidth < 1) {
                this.bucle = requestAnimationFrame(() => this.leer());
                return;
            }

            const ancho = video.videoWidth;
            const alto = video.videoHeight;
            if (!this.canvas) {
                this.canvas = document.createElement('canvas');
                this.canvas.style.display = 'none';
            }
            if (this.canvas.width !== ancho) this.canvas.width = ancho;
            if (this.canvas.height !== alto) this.canvas.height = alto;

            const ctx = this.canvas.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(video, 0, 0, ancho, alto);

            const imagen = ctx.getImageData(0, 0, ancho, alto);
            const code = jsQR(imagen.data, ancho, alto, { inversionAttempts: 'dontInvert' });

            if (code?.data) {
                this.enviar(code.data);
                return;
            }

            this.bucle = requestAnimationFrame(() => this.leer());
        },

        async enviar(token) {
            this.detener();
            this.estado = 'procesando';

            try {
                const res = await fetch(this.rutaQr, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    },
                    body: JSON.stringify({ token, turno: this.turno }),
                });

                if (!res.ok) {
                    let mensaje = 'Código QR no válido.';
                    try {
                        const data = await res.json();
                        mensaje = data.errors?.qr?.[0]
                            ?? (data.message === 'Too Many Attempts.' ? 'Demasiados intentos. Esperá un momento.' : data.message)
                            ?? mensaje;
                    } catch (e) {
                        // respuestas no-JSON (p. ej. 419) caen al mensaje genérico
                    }
                    this.estado = 'error';
                    this.mensaje = mensaje;
                    return;
                }

                this.resultado = await res.json();
                this.estado = 'listo';
            } catch (error) {
                this.estado = 'error';
                this.mensaje = 'No se pudo conectar con el servidor. Revisá tu conexión.';
            }
        },

        recargar() {
            this.cerrar();
            window.location.reload();
        },

        detener() {
            if (this.bucle) {
                cancelAnimationFrame(this.bucle);
                this.bucle = null;
            }
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
            }
        },
    }));
});
