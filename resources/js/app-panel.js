/**
 * Entry point del panel (admin/entrenador/cliente). Ver app-public.js para
 * el porqué de la separación.
 *
 * Los módulos pesados (Chart.js, GSAP, jsQR, qrcode) se importan de forma
 * dinámica: solo se descargan y ejecutan cuando la página los necesita. Esto
 * reduce el JS base de ~565 KB a ~250 KB en la mayoría de páginas del panel.
 * Los módulos que registran componentes Alpine (escaneo-qr, mensajes) se
 * mantienen como import estático porque deben estar listos antes de Alpine.start().
 */
import './bootstrap';

import Alpine from 'alpinejs';
import { iniciarInterfazPanel } from './ui';
import { iniciarContadorMensajes } from './mensajes';
import './escaneo-qr';
import './notificaciones';

window.Alpine = Alpine;

// Confirmación compartida: cualquier botón destructivo del panel puede
// abrir el modal con $store.confirmar.abrir({ accion, metodo, titulo,
// mensaje, etiqueta, ids }) en vez de repetir un confirm() nativo.
document.addEventListener('alpine:init', () => {
    Alpine.store('confirmar', {
        abierto: false,
        titulo: 'Confirmar acción',
        mensaje: '',
        etiqueta: 'Confirmar',
        accion: '',
        metodo: 'DELETE',
        ids: [],
        abrir({ accion, metodo = 'DELETE', titulo = 'Confirmar acción', mensaje = '', etiqueta = 'Confirmar', ids = [] }) {
            this.accion = accion;
            this.metodo = metodo;
            this.titulo = titulo;
            this.mensaje = mensaje;
            this.etiqueta = etiqueta;
            this.ids = ids;
            this.abierto = true;
        },
        cerrar() {
            this.abierto = false;
        },
    });

    // Editor compartido de los modales "Nuevo / Editar": un solo componente
    // que las pantallas de listado configuran con { crearUrl, editarUrl
    // (con el comodín __ID__), base (vacíos), fila (valores iniciales) }.
    // El botón "editar" de cada fila despacha el registro por un evento de
    // ventana (abrir-<modulo>) y este componente lo recibe y rellena el
    // formulario. Tras un error de validación, la página recarga y el modal
    // se reabre con lo tecleado (config.abierta + config.fila con old()).
    Alpine.data('editorGenerico', (config) => ({
        base: config.base ?? {},
        abierta: config.abierta ?? false,
        editando: config.editando ?? false,
        crearUrl: config.crearUrl ?? '',
        editarUrl: config.editarUrl ?? '',
        fila: { ...(config.fila ?? {}) },
        accion: config.editando && config.fila?.id
            ? (config.editarUrl ?? '').replace('__ID__', config.fila.id)
            : (config.crearUrl ?? ''),
        // Rutas o datos adicionales que el modal necesite (p. ej. la URL del
        // formulario de movimiento de stock en Inventario) se reciben por
        // config.extras y se fusionan tal cual.
        ...(config.extras ?? {}),
        abrir(datos) {
            this.fila = { ...this.base, ...(datos ?? {}) };
            this.editando = !!datos?.id;
            this.accion = this.editando ? this.editarUrl.replace('__ID__', this.fila.id) : this.crearUrl;
            this.abierta = true;
        },
        cerrar() {
            this.abierta = false;
        },
    }));
});

Alpine.start();

// Cada módulo se inicia por separado y con su propio try/catch: un fallo en
// uno (p. ej. un gráfico con datos límite) no puede dejar sin arrancar a los
// que van después — como pasaba con el QR de la ficha del socio, que nunca
// llegaba a pintarse si iniciarGraficos() fallaba.
function iniciar(nombre, fn) {
    try {
        fn();
    } catch (error) {
        console.error(`[app-panel.js] fallo al iniciar "${nombre}":`, error);
    }
}

// --- Módulos siempre cargados (livianos, necesarios en toda página) ---
iniciar('interfaz', iniciarInterfazPanel);
iniciar('mensajes-contador', iniciarContadorMensajes);

// --- Módulos pesados cargados bajo demanda (solo si la página los necesita) ---
iniciar('animaciones', async () => {
    const { iniciarAnimaciones } = await import('./animations');
    iniciarAnimaciones();
});

// Gráficos: precargados para que el evento tema:refrescar funcione sin delay.
let _graficosModule = null;
iniciar('graficos', async () => {
    _graficosModule = await import('./graficos');
    _graficosModule.iniciarGraficos();
});

iniciar('interacciones', async () => {
    const { iniciarInteracciones } = await import('./interacciones');
    iniciarInteracciones();
});

iniciar('qr', async () => {
    const { iniciarQr } = await import('./qr');
    iniciarQr();
});

// Al cambiar el tema del panel, los gráficos se reconstruyen con la paleta
// nueva (los tokens se leen en cada construcción). El módulo se importó
// dináricamente arriba; si la página no tiene gráficos, simplemente no-op.
window.addEventListener('tema:refrescar', () => {
    _graficosModule?.refrescarGraficos();
});
