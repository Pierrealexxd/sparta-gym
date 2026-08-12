import './bootstrap';

import Alpine from 'alpinejs';
import { iniciarInterfaz } from './ui';
import { iniciarAnimaciones } from './animations';
import { iniciarParticulas } from './particulas';
import { iniciarGraficos, refrescarGraficos } from './graficos';
import { iniciarInteracciones } from './interacciones';
import { iniciarQr } from './qr';
import { iniciarContadorMensajes } from './mensajes';
import { iniciarCarruseles } from './carrusel';
import './escaneo-qr';

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
// que van después en la misma pasada síncrona — como pasaba con el QR de la
// ficha del socio, que nunca llegaba a pintarse si iniciarGraficos() fallaba.
function iniciar(nombre, fn) {
    try {
        fn();
    } catch (error) {
        console.error(`[app.js] fallo al iniciar "${nombre}":`, error);
    }
}

// La interfaz (menú, acordeón, cabecera) tiene que responder desde el primer
// instante y no depende de GSAP; las animaciones se montan justo después.
iniciar('interfaz', iniciarInterfaz);
iniciar('animaciones', iniciarAnimaciones);
iniciar('particulas', () => iniciarParticulas(document.querySelector('[data-particulas]')));
iniciar('graficos', iniciarGraficos);
iniciar('interacciones', iniciarInteracciones);
iniciar('qr', iniciarQr);
iniciar('mensajes-contador', iniciarContadorMensajes);
iniciar('carruseles', iniciarCarruseles);

// Al cambiar el tema del panel, los gráficos se reconstruyen con la paleta
// nueva (los tokens se leen en cada construcción).
window.addEventListener('tema:refrescar', refrescarGraficos);
