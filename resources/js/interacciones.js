/**
 * interacciones.js
 * --------------------------------------------------------------
 * La microinteracción que hace que una tarjeta se sienta "de Google Labs":
 * un brillo que sigue al cursor y una inclinación de pocos grados hacia
 * donde apunta. Aquí el brillo es ámbar/sangre —la paleta de la marca—,
 * no el arcoíris pastel de Labs; lo que se toma prestado es el mecanismo
 * (la posición del puntero convertida en variables CSS), no el color.
 *
 * Sólo actúa sobre `.tarjeta--interactiva` (opt-in, ver components.css):
 * las tarjetas-formulario del panel usan `.tarjeta` sin ese modificador y
 * quedan totalmente al margen.
 */

const PUEDE_INCLINAR = window.matchMedia('(hover: hover) and (pointer: fine)').matches
    && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const INCLINACION_MAX = 6; // grados; más que esto ya se siente como un mareo, no como un lujo

export function iniciarInteracciones() {
    document.querySelectorAll('.tarjeta--interactiva').forEach((tarjeta) => {
        tarjeta.addEventListener('pointermove', (e) => {
            if (e.pointerType !== 'mouse') return;   // el dedo no tiene "posición" que seguir

            const r = tarjeta.getBoundingClientRect();
            const px = (e.clientX - r.left) / r.width;
            const py = (e.clientY - r.top) / r.height;

            tarjeta.style.setProperty('--brillo-x', `${(px * 100).toFixed(1)}%`);
            tarjeta.style.setProperty('--brillo-y', `${(py * 100).toFixed(1)}%`);

            if (!PUEDE_INCLINAR) return;

            // El eje se invierte a propósito: la esquina donde está el
            // cursor tiene que "levantarse" hacia él, no hundirse.
            const inclinaY = (py - .5) * -INCLINACION_MAX * 2;
            const inclinaX = (px - .5) * INCLINACION_MAX * 2;
            tarjeta.style.setProperty('--inclina-x', `${inclinaX.toFixed(2)}deg`);
            tarjeta.style.setProperty('--inclina-y', `${inclinaY.toFixed(2)}deg`);
        });

        tarjeta.addEventListener('pointerleave', () => {
            tarjeta.style.setProperty('--inclina-x', '0deg');
            tarjeta.style.setProperty('--inclina-y', '0deg');
        });
    });
}
