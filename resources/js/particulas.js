/**
 * particulas.js
 * --------------------------------------------------------------
 * Ascuas y polvo metálico flotando en el hero. Mismo principio que la
 * capa de partículas del proyecto "El Castillo": un único <canvas>
 * en vez de decenas de nodos DOM animados, para que el hilo de
 * composición no se sature en móvil.
 *
 * A diferencia del castillo (10 escenas con mezclas distintas), aquí sólo
 * hay una atmósfera: el fuego de la marca. Se limita al hero porque es
 * donde aporta —un fondo ambiental corriendo tras el FAQ sólo distrae.
 */

const MENOS_MOVIMIENTO = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Sospecha de dispositivo modesto: menos partículas, no cero. */
const DENSIDAD = (() => {
    const nucleos = navigator.hardwareConcurrency ?? 8;
    const memoria = navigator.deviceMemory ?? 8;
    return (nucleos <= 4 || memoria <= 4) ? 0.55 : 1;
})();

const aleatorio = (a, b) => a + Math.random() * (b - a);

/** Receta única: ascua (cálida, sube) o mota de polvo (fría, deriva). */
class Particula {
    constructor(ancho, alto) {
        this.esAscua = Math.random() < 0.62;
        this.reiniciar(ancho, alto, true);
    }

    reiniciar(ancho, alto, dentro) {
        this.x = Math.random() * ancho;
        this.y = dentro ? Math.random() * alto : alto + 20 + Math.random() * 40;

        if (this.esAscua) {
            this.vy = -aleatorio(10, 34);
            this.vx = aleatorio(-8, 8);
            this.tam = aleatorio(1.2, 3.4);
            this.alfaBase = aleatorio(.35, .9);
            // Rango cálido: entre la brasa y la sangre de la marca.
            this.tono = aleatorio(6, 22);
        } else {
            this.vy = -aleatorio(2, 8);
            this.vx = aleatorio(-6, 6);
            this.tam = aleatorio(.6, 1.6);
            this.alfaBase = aleatorio(.12, .3);
            this.tono = aleatorio(30, 42);   // bronce apagado
        }

        this.fase = Math.random() * Math.PI * 2;
        this.velFase = .5 + Math.random() * 1.5;
        this.vida = 0;
    }

    actualizar(dt, ancho, alto) {
        this.vida += dt;
        this.fase += dt * this.velFase;
        const vaiven = Math.sin(this.vida * .7 + this.fase) * (this.esAscua ? 10 : 4);

        this.x += (this.vx + vaiven) * dt;
        this.y += this.vy * dt;

        const m = 40;
        if (this.y < -m || this.x < -m || this.x > ancho + m) {
            this.reiniciar(ancho, alto, false);
        }
    }

    dibujar(ctx) {
        // Parpadeo propio: sin él, cien ascuas idénticas se leen como un patrón.
        const parpadeo = .5 + .5 * Math.sin(this.fase * 2.2);
        const a = this.alfaBase * (this.esAscua ? .5 + parpadeo * .5 : 1);
        if (a <= .01) return;

        const color = `hsla(${this.tono}, 92%, ${this.esAscua ? 58 : 62}%, `;

        if (this.esAscua) {
            // Halo + núcleo: dos círculos salen más baratos que un gradiente
            // radial creado por partícula y por cuadro.
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.tam * 2.6, 0, Math.PI * 2);
            ctx.fillStyle = color + (a * .18).toFixed(3) + ')';
            ctx.fill();
        }

        ctx.beginPath();
        ctx.arc(this.x, this.y, this.tam, 0, Math.PI * 2);
        ctx.fillStyle = color + a.toFixed(3) + ')';
        ctx.fill();
    }
}

export function iniciarParticulas(canvas) {
    if (!canvas || MENOS_MOVIMIENTO) return;

    const ctx = canvas.getContext('2d', { alpha: true });
    let ancho = 0;
    let alto = 0;
    let particulas = [];
    let activo = true;

    function dimensionar() {
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const rect = canvas.parentElement.getBoundingClientRect();
        ancho = rect.width;
        alto = rect.height;
        canvas.width = Math.floor(ancho * dpr);
        canvas.height = Math.floor(alto * dpr);
        canvas.style.width = ancho + 'px';
        canvas.style.height = alto + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        const total = Math.round(Math.min(110, Math.max(30, (ancho * alto) / 15000)) * DENSIDAD);
        particulas = Array.from({ length: total }, () => new Particula(ancho, alto));
    }

    dimensionar();
    window.addEventListener('resize', dimensionar, { passive: true });

    document.addEventListener('visibilitychange', () => {
        activo = !document.hidden;
        if (activo) { ultimo = performance.now(); requestAnimationFrame(paso); }
    });

    let ultimo = performance.now();

    function paso(ahora) {
        if (!activo) return;
        const dt = Math.min((ahora - ultimo) / 1000, .05);
        ultimo = ahora;

        ctx.clearRect(0, 0, ancho, alto);
        for (const p of particulas) {
            p.actualizar(dt, ancho, alto);
            p.dibujar(ctx);
        }

        requestAnimationFrame(paso);
    }

    requestAnimationFrame(paso);
}
