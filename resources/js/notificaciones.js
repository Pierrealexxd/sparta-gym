import axios from 'axios';
import Alpine from 'alpinejs';

/**
 * Notificaciones unificadas (ver docs/plan-notificaciones-toast.md).
 *
 * Tres piezas:
 *  - `$store.toasts`: pila de toasts en tiempo real (duración por
 *    prioridad, pausa al pasar el cursor, máximo visible).
 *  - `Alpine.data('campanita')`: badge + cajón lateral, un solo origen
 *    (los endpoints de /notificaciones) en vez de sumar tres fuentes.
 *  - Polling ligero (sparta.notificaciones.polling_segundos) que actualiza
 *    el contador y trae los toasts nuevos sin recargar.
 *
 * Sin websockets: mismo mecanismo que la mensajería, suficiente para la
 * escala del gimnasio y sin dependencias nuevas.
 */

const RUTA = (clave) => (window.spartaNotificaciones?.[clave] ?? '');
const POLLING_MS = () => (window.spartaNotificaciones?.pollingMs ?? 15000);

const duracionDe = (prioridad) => {
    const segundos = window.spartaNotificaciones?.toastDuracion?.[prioridad] ?? 5;
    return segundos * 1000;
};

document.addEventListener('alpine:init', () => {
    Alpine.store('toasts', {
        items: [],
        _siguienteId: 0,

        // El backend serializa cada notificación con nombres en inglés
        // (title/body/icon/priority — mismos nombres que las columnas de la
        // tabla `notifications`, ver NotificationService::serializar()), y
        // recibirNuevas() pasa esos objetos tal cual. Sin este mapeo,
        // "titulo"/"cuerpo"/"icono"/"prioridad" llegaban siempre undefined
        // y todo toast salía en blanco con el ícono y prioridad por
        // defecto, sin importar la notificación real.
        mostrar({ title: titulo, body: cuerpo = '', icon: icono = 'campana', priority: prioridad = 'media', url = '' }) {
            const max = window.spartaNotificaciones?.toastMax ?? 4;

            const id = ++this._siguienteId;
            const item = {
                id, titulo, cuerpo, icono, prioridad, url,
                duracion: duracionDe(prioridad),
                inicio: Date.now(),
                restante: null,
                temporizador: null,
            };

            // Máximo visible: la más antigua se retira para que entre la nueva
            // (nunca se acumulan toasts tapando la pantalla).
            if (this.items.length >= max) {
                this._limpiarTemporizador(this.items[0]);
                this.items.shift();
            }

            this.items.push(item);
            item.temporizador = setTimeout(() => this.cerrar(id), item.duracion);
        },

        cerrar(id) {
            const item = this.items.find((t) => t.id === id);
            if (!item) return;
            this._limpiarTemporizador(item);
            this.items = this.items.filter((t) => t.id !== id);
        },

        // Pasar el cursor pausa la cuenta; al salir se reanuda con lo que
        // quedaba (no reinicia la duración completa).
        pausar(item) {
            if (!item) return;
            this._limpiarTemporizador(item);
            item.restante = Math.max(0, item.duracion - (Date.now() - item.inicio));
        },

        reanudar(item) {
            if (!item) return;
            const restante = item.restante ?? item.duracion;
            item.inicio = Date.now();
            item.restante = null;
            item.temporizador = setTimeout(() => this.cerrar(item.id), restante);
        },

        _limpiarTemporizador(item) {
            if (item.temporizador) clearTimeout(item.temporizador);
            item.temporizador = null;
        },
    });

    Alpine.data('campanita', () => ({
        abierto: false,
        items: [],
        total: 0,
        cargando: false,
        // Cursor de toasts, por timestamp (updated_at) — no por id: el
        // dedupe del backend reutiliza la misma fila mientras la conversación
        // siga sin leer, así que un cursor por id dejaba de re-toastear a
        // partir del segundo mensaje sin leer (ver NotificationService::nuevas).
        // La primera pasada solo lo sincroniza (toastear lo que ya existía
        // al cargar la página sería ruido — eso va al cajón).
        ultimoCursor: '',
        _primeraPeticion: true,
        temporizador: null,

        init() {
            this.actualizarContador();
            this.temporizador = setInterval(() => this.latido(), POLLING_MS());
        },

        destroy() {
            clearInterval(this.temporizador);
        },

        async latido() {
            await this.actualizarContador();
            await this.recibirNuevas();
        },

        async actualizarContador() {
            try {
                const { data } = await axios.get(RUTA('total'));
                this.total = Number(data.total) || 0;
            } catch (e) {
                /* red caída: se reintenta en el próximo ciclo */
            }
        },

        async recibirNuevas() {
            try {
                const { data } = await axios.get(RUTA('nuevas') + '?desde=' + encodeURIComponent(this.ultimoCursor));
                this.ultimoCursor = data.ultimo_cursor || this.ultimoCursor;

                if (this._primeraPeticion) {
                    this._primeraPeticion = false;
                    return;
                }

                const toasts = data.toasts ?? [];
                toasts.forEach((t) => this.$store.toasts.mostrar(t));
                if (toasts.length) await this.actualizarContador();
            } catch (e) {
                /* se reintenta en el próximo ciclo */
            }
        },

        async alternar() {
            this.abierto = !this.abierto;
            if (this.abierto) await this.cargar();
        },

        async cargar() {
            this.cargando = true;
            try {
                const { data } = await axios.get(RUTA('lista'));
                this.items = data.items ?? [];
            } finally {
                this.cargando = false;
            }
        },

        async marcarLeida(n) {
            if (n.leida) return;
            n.leida = true;
            this.total = Math.max(0, this.total - 1);
            // fetch con keepalive: la fila se marca justo cuando el clic
            // navega a otra página — un POST normal se abortaría al cambiar
            // de ruta y el contador volvería a subir en el próximo ciclo.
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                await fetch(RUTA('leida').replace('__ID__', n.id), {
                    method: 'POST',
                    keepalive: true,
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                });
            } catch (e) {
                /* si falla, el servidor lo re-marca en la próxima apertura */
            }
        },

        async marcarTodas() {
            if (this.total === 0) return;
            this.items = this.items.map((n) => ({ ...n, leida: true }));
            this.total = 0;
            try {
                await axios.post(RUTA('leidas'));
            } catch (e) {
                /* el badge se re-sincroniza en el próximo ciclo */
            }
        },

        irA(n) {
            if (!n.leida) this.marcarLeida(n);
            if (n.url) window.location.href = n.url;
        },
    }));
});
