import axios from 'axios';
import Alpine from 'alpinejs';

/**
 * Mensajería interna. Dos piezas:
 *
 *  - `chat`: componente Alpine de la página /mensajes (lista + hilo + directorio).
 *  - `iniciarContadorMensajes`: badge de no leídos de la barra lateral, que
 *    corre en todas las páginas del panel con un polling ligero.
 *
 * Sin websockets: polling corto dentro del chat y otro más largo para el
 * contador. Es suficiente para la escala del gimnasio y no añade deps.
 */

const RUTA = (clave, id) => (window.spartaMensajes?.[clave] ?? '').replace('__ID__', id);

const PESO_MAX_ADJUNTO = 20 * 1024 * 1024; // mismo techo que el backend

document.addEventListener('alpine:init', () => {
    Alpine.data('chat', (conversaciones) => ({
        conversaciones: conversaciones ?? [],
        filtroLista: '',
        nuevoAbierto: false,
        filtroRol: '',
        busqueda: '',
        directorio: [],
        cargandoDirectorio: false,
        conversacion: null,
        contacto: null,
        mensajes: [],
        ultimoId: 0,
        texto: '',
        adjunto: null,
        aviso: '',
        enviando: false,
        cargandoHilo: false,
        temporizador: null,

        get conversacionesFiltradas() {
            const t = this.filtroLista.trim().toLowerCase();
            if (!t) return this.conversaciones;
            return this.conversaciones.filter((c) =>
                c.nombre.toLowerCase().includes(t)
                || (c.ultimo || '').toLowerCase().includes(t),
            );
        },

        init() {
            if (window.spartaAbrirConversacion) {
                this.abrir(window.spartaAbrirConversacion);
            }
            this.temporizador = setInterval(() => this.latido(), 4000);
        },

        destroy() {
            clearInterval(this.temporizador);
        },

        async latido() {
            if (this.conversacion) {
                await this.recibirNuevos();
            }
            await this.refrescarLista();
        },

        async abrir(id) {
            if (!id || id === this.conversacion) return;

            this.conversacion = id;
            this.cargandoHilo = true;
            this.quitarAdjunto();
            this.aviso = '';
            try {
                const { data } = await axios.get(RUTA('listaMensajes', id));
                this.contacto = data.contacto;
                this.mensajes = data.mensajes;
                this.ultimoId = data.ultimo_id;
                this.conversaciones = this.conversaciones.map((c) =>
                    c.id === id ? { ...c, no_leidas: 0 } : c,
                );
                this.$nextTick(() => this.irAlFinal());
            } finally {
                this.cargandoHilo = false;
            }
        },

        async recibirNuevos() {
            const { data } = await axios.get(RUTA('listaMensajes', this.conversacion) + '?desde=' + this.ultimoId);
            if (data.mensajes.length) {
                this.mensajes.push(...data.mensajes);
                this.ultimoId = data.ultimo_id;
                this.$nextTick(() => this.irAlFinal());
            }
        },

        async refrescarLista() {
            try {
                const { data } = await axios.get(window.spartaMensajes.lista);
                const activa = this.conversacion;
                this.conversaciones = activa
                    ? data.conversaciones.map((c) => (c.id === activa ? { ...c, no_leidas: 0 } : c))
                    : data.conversaciones;
            } catch (e) {
                /* red caída: se reintenta en el próximo latido */
            }
        },

        async enviarMensaje() {
            const cuerpo = this.texto.trim();
            if ((!cuerpo && !this.adjunto) || this.enviando || !this.conversacion) return;

            this.enviando = true;
            try {
                let data;
                if (this.adjunto) {
                    // Con adjunto va FormData: el archivo manda multipart y
                    // el texto viaja como campo opcional del mismo POST.
                    const fd = new FormData();
                    fd.append('body', cuerpo);
                    fd.append('adjunto', this.adjunto.file);
                    ({ data } = await axios.post(RUTA('enviar', this.conversacion), fd));
                } else {
                    ({ data } = await axios.post(RUTA('enviar', this.conversacion), { body: cuerpo }));
                }

                this.mensajes.push(data.mensaje);
                this.ultimoId = data.mensaje.id;
                this.texto = '';
                this.quitarAdjunto();
                this.$nextTick(() => this.irAlFinal());
            } finally {
                this.enviando = false;
            }
        },

        elegirAdjunto(e) {
            const file = e.target.files?.[0];
            e.target.value = '';
            if (!file) return;

            if (file.size > PESO_MAX_ADJUNTO) {
                this.aviso = 'El archivo supera el límite de 20 MB.';
                return;
            }

            this.aviso = '';
            this.adjunto = { file, nombre: file.name };
        },

        quitarAdjunto() {
            this.adjunto = null;
        },

        abrirNuevo() {
            this.nuevoAbierto = true;
            this.filtroRol = '';
            this.busqueda = '';
            this.cargarDirectorio();
            this.$nextTick(() => this.$refs.busqueda?.focus());
        },

        async cargarDirectorio() {
            this.cargandoDirectorio = true;
            try {
                const { data } = await axios.get(window.spartaMensajes.directorio, {
                    params: { rol: this.filtroRol, q: this.busqueda },
                });
                this.directorio = data.usuarios;
            } finally {
                this.cargandoDirectorio = false;
            }
        },

        async nuevaConversacion(usuarioId) {
            if (!usuarioId) return; // sin id no hay a quién escribirle — nada que intentar
            try {
                const { data } = await axios.post(window.spartaMensajes.conversar, { user_id: usuarioId });
                this.nuevoAbierto = false;
                await this.refrescarLista();
                await this.abrir(data.id);
            } catch (e) {
                console.error('[mensajes] no se pudo abrir la conversación:', e);
            }
        },

        irAlFinal() {
            const caja = this.$refs.mensajes;
            if (caja) caja.scrollTop = caja.scrollHeight;
        },
    }));
});

/** Polling global de badges de la barra lateral (corre en cada página del
 *  panel): el de mensajes y el de alertas de stock, cada uno con su propia
 *  URL en data-url y esperando el mismo shape `{ total }`. */
export function iniciarContadorMensajes() {
    const badges = [...document.querySelectorAll('[data-mensajes-no-leidas], [data-stock-alertas]')];
    if (!badges.length) return;

    const actualizar = async () => {
        await Promise.all(badges.map(async (b) => {
            const url = b.dataset.url;
            if (!url) return;
            try {
                const { data } = await axios.get(url);
                const total = Number(data.total) || 0;
                b.textContent = total;
                b.hidden = total === 0;
            } catch (e) {
                /* el badge se queda como está si falla el polling */
            }
        }));
    };

    actualizar();
    setInterval(actualizar, 15000);
}
