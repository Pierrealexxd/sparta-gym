/**
 * escaneo-qr.js
 * --------------------------------------------------------------
 * Cámara del entrenador para marcar asistencia con el QR de la sede.
 *
 * Flujo del modal (ver entrenador/asistencia/_escaneo-qr.blade.php):
 *   pidiendo → verificando-ubicacion → ubicacion-bloqueada | preparando
 *   → (turno | leyendo) → procesando → listo | error
 *
 * La ubicación se pide ANTES de encender la cámara. Si el navegador no la
 * tiene o el permiso fue denegado, se muestra instrucciones para activarla.
 * La decisión entrada/salida siempre la toma el backend (AsistenciaService).
 *
 * El stream se detiene en cuanto hay resultado o se cierra el modal — la
 * luz de la cámara no puede quedar encendida en segundo plano.
 */
import Alpine from 'alpinejs';

// jsQR se carga bajo demanda: solo cuando el entrenador abre el escáner.
// Pesa ~10 KB y no tiene sentido en páginas que nunca usan la cámara.
let jsQRCargado = null;

document.addEventListener('alpine:init', () => {
    Alpine.data('escaneoQr', (config) => ({
        abierto: false,
        estado: 'pidiendo',   // pidiendo | verificando-ubicacion | ubicacion-bloqueada | preparando | turno | leyendo | procesando | listo | error
        mensaje: '',
        enTurno: false,
        horaEntrada: null,
        turno: 'manana',
        resultado: null,
        coords: null,

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
            this.coords = null;
            this.abierto = true;
            this.estado = 'verificando-ubicacion';

            // Primero verificar/solicitar GPS. La cámara se pide después,
            // solo si la ubicación está disponible. Esto evita que en iOS,
            // donde el permiso denegado no se puede re-pedir, el entrenador
            // escanee el QR y recién ahí descubra que no puede marcar.
            this.verificarUbicacion();
        },

        cerrar() {
            this.detener();
            this.abierto = false;
        },

        /**
         * Verifica el estado del permiso de ubicación ANTES de pedir_coords.
         * Si el permiso ya fue denegado en Safari/iOS, getCurrentPosition
         * falla silenciosamente — por eso usamos navigator.permissions.query
         * cuando está disponible para detectar el caso y mostrar instrucciones.
         */
        async verificarUbicacion() {
            if (!navigator.geolocation) {
                this.estado = 'ubicacion-bloqueada';
                this.mensaje = 'Tu dispositivo no tiene GPS. Necesitás ubicación para marcar asistencia.';
                return;
            }

            // navigator.permissions no existe en todos los navegadores
            // (p. ej. older Safari). Si no está, pedimos directamente.
            if (navigator.permissions?.query) {
                try {
                    const resultado = await navigator.permissions.query({ name: 'geolocation' });
                    if (resultado.state === 'denied') {
                        this.estado = 'ubicacion-bloqueada';
                        this.mensaje = '';
                        return;
                    }
                    // 'granted' o 'prompt': pedir coords (mostrará diálogo si es prompt)
                } catch {
                    // query falló: continuar y pedir directamente
                }
            }

            this.pedirUbicacion();
        },

        /**
         * Pide la ubicación al navegador. Si el usuario la concede, se
         * guarda en this.coords y se procede a la cámara. Si la niega,
         * se muestra instrucciones para activarla en Ajustes.
         */
        pedirUbicacion() {
            navigator.geolocation.getCurrentPosition(
                (posicion) => {
                    if (!this.abierto) return;
                    this.coords = { lat: posicion.coords.latitude, lng: posicion.coords.longitude };
                    this.estado = 'preparando';
                    this.arrancarCamara();
                    this.consultarEstado();
                },
                (error) => {
                    if (!this.abierto) return;
                    this.estado = 'ubicacion-bloqueada';
                    if (error.code === error.PERMISSION_DENIED) {
                        this.mensaje = '';
                    } else if (error.code === error.POSITION_UNAVAILABLE) {
                        this.mensaje = 'Ubicación no disponible. Verificá que el GPS esté activado.';
                    } else {
                        this.mensaje = 'No se pudo obtener tu ubicación. Intentá de nuevo.';
                    }
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
            );
        },

        /** Reintentar después de que el usuario fue a Ajustes a activar ubicación. */
        reintentarUbicacion() {
            this.estado = 'verificando-ubicacion';
            this.mensaje = '';
            this.verificarUbicacion();
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

        async empezarLectura() {
            const video = this.$refs.video;
            if (!video) return;

            // Precargar jsQR una sola vez: se resuelve rápido (~10 KB) pero
            // si la red falla, el escáner lo detecta en leer() y muestra error.
            if (!jsQRCargado) {
                try {
                    jsQRCargado = (await import('jsqr')).default;
                } catch {
                    this.detener();
                    this.estado = 'error';
                    this.mensaje = 'No se pudo cargar el lector QR. Revisá tu conexión.';
                    return;
                }
            }

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
            const code = jsQRCargado(imagen.data, ancho, alto, { inversionAttempts: 'dontInvert' });

            if (code?.data) {
                this.enviar(code.data);
                return;
            }

            this.bucle = requestAnimationFrame(() => this.leer());
        },

        /**
         * Tras leer el QR, se envía la marcación con las coordenadas que ya
         * se capturaron al abrir el modal (this.coords). La ubicación es
         * OBLIGATORIA: si no se pudo obtener, este método no se llama.
         */
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
                    body: JSON.stringify({ token, turno: this.turno, lat: this.coords.lat, lng: this.coords.lng }),
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
