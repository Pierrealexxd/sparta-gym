/**
 * Entry point de las páginas públicas (landing, login, registro).
 * Antes había un solo app.js para todo el sitio: cada visitante de la
 * landing bajaba también gráficos (Chart.js), QR y mensajería del panel sin
 * usarlos nunca (y viceversa, el panel bajaba GSAP/partículas de la
 * landing). Los módulos ya tenían guards de elemento (`if (!el) return`) así
 * que nada se rompía, pero el peso de más viajaba igual. Separar en dos
 * entradas (esta y app-panel.js) evita ese peso de más sin duplicar lógica:
 * cada módulo sigue viviendo en su propio archivo, solo cambia quién lo
 * importa.
 */
import './bootstrap';

import Alpine from 'alpinejs';
import { iniciarInterfazPublica } from './ui';
import { iniciarAnimaciones } from './animations';
import { iniciarParticulas } from './particulas';
import { iniciarInteracciones } from './interacciones';
import { iniciarCarruseles } from './carrusel';

window.Alpine = Alpine;
Alpine.start();

// Mismo patrón que app-panel.js: cada módulo con su try/catch, para que un
// fallo en uno no deje sin arrancar a los que van después.
function iniciar(nombre, fn) {
    try {
        fn();
    } catch (error) {
        console.error(`[app-public.js] fallo al iniciar "${nombre}":`, error);
    }
}

iniciar('interfaz', iniciarInterfazPublica);
iniciar('animaciones', iniciarAnimaciones);
iniciar('particulas', () => iniciarParticulas(document.querySelector('[data-particulas]')));
iniciar('interacciones', iniciarInteracciones);
iniciar('carruseles', iniciarCarruseles);
