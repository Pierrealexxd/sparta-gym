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
import { iniciarParticulas } from './particulas';
import { iniciarCarruseles } from './carrusel';
import { iniciarCarruseles3d } from './carrusel-3d';

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
// GSAP (~116 KB) se carga bajo demanda: solo si la página tiene [data-revelar]
// o el hero. Las landing lo necesitan, login/registro no.
iniciar('animaciones', async () => {
    const { iniciarAnimaciones } = await import('./animations');
    iniciarAnimaciones();
});
iniciar('particulas', () => iniciarParticulas(document.querySelector('[data-particulas]')));
// interacciones.js es vanilla JS liviano (~0.8 KB): cursor-tracking glow + tilt.
iniciar('interacciones', async () => {
    const { iniciarInteracciones } = await import('./interacciones');
    iniciarInteracciones();
});
iniciar('carruseles', iniciarCarruseles);
// Después de 'carruseles': depende de que la pista y sus clones ya
// existan (carrusel.js los arma en construirCarrusel()→reconstruir(),
// síncrono dentro de iniciarCarruseles()).
iniciar('carrusel-3d', iniciarCarruseles3d);
