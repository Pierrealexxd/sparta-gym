/**
 * carrusel-3d.js
 * --------------------------------------------------------------
 * P1 + P2 del plan de carruseles 3D: coverflow suave (la tarjeta centrada
 * al frente, las vecinas replegadas) y atenuación (las vecinas se apagan
 * y pierden saturación, nunca la marca). Solo testimonios y biblioteca —
 * planes queda quieto a propósito (comparar precios pide quietud, ver
 * PLAN_CARRUSELES_3D_LANDING.md).
 *
 * Solo escribe: nunca decide cuándo avanza el carrusel (eso sigue siendo
 * 100% de carrusel.js, que no se toca) ni pausa/reanuda nada — se limita a
 * mirar el 'scroll' de la pista (el mismo que ya dispara carrusel.js con
 * su propio avance o un arrastre manual) y, por cada tarjeta, calcular su
 * distancia al centro y aplicar la transformación.
 *
 * El transform/opacity de cada tarjeta se escriben DIRECTO por elemento
 * (element.style, inline) y no por variable CSS consumida en landing.css:
 * el revelado GSAP (data-revelar) dice esas mismas dos propiedades inline
 * al terminar su animación de entrada, y un inline de hoja de estilos
 * nunca gana contra otro inline — solo otro inline escrito después (el
 * nuestro, que no arranca hasta el primer scroll de la pista, mucho
 * después de que el revelado ya terminó) puede reemplazarlo con certeza.
 * El filtro (grayscale de P2) sí va por variable + CSS: GSAP no lo toca.
 */

const PERSPECTIVA_PX = 900;   // mismo valor que ya usa .tarjeta (components.css)
// Escala y profundidad rebajadas (antes 1.04/40px): la tarjeta activa crece
// dentro de la pista, que recorta también en vertical (ver comentario junto
// al padding-top de .planes/.testimonios/.biblioteca en landing.css) — menos
// crecimiento es más margen de sobra contra ese recorte, sin perder el efecto.
const ESCALA_CENTRO = 1.03;
const ESCALA_BORDE = 0.92;
const PROFUNDIDAD_CENTRO_PX = 24;
const GIRO_BORDE_DEG = 8;
const OPACIDAD_BORDE = 0.55;
const ATENUAR_BORDE = 0.4;    // grayscale 0..1
const UMBRAL_CENTRO = 0.08;   // ver comentario junto a su uso, en aplicar()

function construir3d(pista) {
    const menosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)');
    const esMovil = window.matchMedia('(max-width: 740px)');
    const activo = () => esMovil.matches && !menosMovimiento.matches;

    let enPantalla = false;
    let cuadroProgramado = false;

    function neutralizar() {
        [...pista.children].forEach((tarjeta) => {
            tarjeta.style.transform = '';
            tarjeta.style.opacity = '';
            tarjeta.style.setProperty('--atenuar-3d', '0');
        });
    }

    function aplicar() {
        cuadroProgramado = false;
        if (!activo() || !enPantalla) return;

        const rectPista = pista.getBoundingClientRect();
        const centroPista = rectPista.left + rectPista.width / 2;
        const radio = rectPista.width / 2 || 1;
        const tarjetas = [...pista.children];

        // Primero se LEE todo (getBoundingClientRect de cada tarjeta),
        // después se ESCRIBE todo (style.transform/opacity). Intercalar
        // lectura y escritura tarjeta por tarjeta fuerza un reflow
        // síncrono en cada vuelta ("layout thrashing"): hasta 18 tarjetas
        // en la biblioteca, serían hasta 18 reflows por cuadro en vez de 1.
        const medidas = tarjetas.map((tarjeta) => tarjeta.getBoundingClientRect());

        tarjetas.forEach((tarjeta, i) => {
            const rect = medidas[i];
            if (rect.width === 0) return; // oculta por el filtro Alpine de la biblioteca

            const centro = rect.left + rect.width / 2;
            let desfase = Math.max(-1, Math.min(1, (centro - centroPista) / radio));
            // Zona muerta: el scroll-snap no cae siempre en el píxel exacto
            // del centro (unos pocos px de resto según el ancho del
            // viewport), algo invisible en una tarjeta sin transformar. Acá
            // ese resto se traduce en un giro/escala asimétrico bien
            // perceptible justo en la tarjeta que se supone al frente — se
            // redondea a 0 para que "centrada" se vea realmente centrada.
            if (Math.abs(desfase) < UMBRAL_CENTRO) desfase = 0;
            const distancia = Math.abs(desfase);

            const escala = ESCALA_CENTRO - distancia * (ESCALA_CENTRO - ESCALA_BORDE);
            const profundidad = (1 - distancia) * PROFUNDIDAD_CENTRO_PX;
            const giro = desfase * GIRO_BORDE_DEG;
            const opacidad = 1 - distancia * (1 - OPACIDAD_BORDE);
            const atenuar = distancia * ATENUAR_BORDE;

            tarjeta.style.transform =
                `perspective(${PERSPECTIVA_PX}px) translateZ(${profundidad.toFixed(1)}px) `
                + `scale(${escala.toFixed(3)}) rotateY(${giro.toFixed(2)}deg)`;
            tarjeta.style.opacity = opacidad.toFixed(2);
            tarjeta.style.setProperty('--atenuar-3d', atenuar.toFixed(2));
        });
    }

    function pedirCuadro() {
        if (cuadroProgramado) return;
        cuadroProgramado = true;
        requestAnimationFrame(aplicar);
    }

    pista.addEventListener('scroll', pedirCuadro, { passive: true });

    new IntersectionObserver(([entrada]) => {
        enPantalla = entrada.isIntersecting;
        if (enPantalla) pedirCuadro(); else neutralizar();
    }, { threshold: .1 }).observe(pista);

    // A propósito SIN MutationObserver sobre la pista: este módulo mismo
    // escribe `style` en cada tarjeta (transform/opacity) en cada cuadro,
    // así que observar cambios de `style` se dispararía a sí mismo sin fin
    // — el filtro Alpine de la biblioteca (que si cambia `style` de verdad,
    // vía x-show) se refleja solo, en el próximo scroll natural de la
    // pista; no vale la pena el riesgo de un bucle por un recálculo que de
    // todos modos llega enseguida.
    let redimension;
    window.addEventListener('resize', () => {
        clearTimeout(redimension);
        redimension = setTimeout(() => (activo() ? pedirCuadro() : neutralizar()), 200);
    });

    esMovil.addEventListener('change', () => (activo() ? pedirCuadro() : neutralizar()));
    menosMovimiento.addEventListener('change', () => (activo() ? pedirCuadro() : neutralizar()));

    if (activo()) pedirCuadro();
}

export function iniciarCarruseles3d() {
    document.querySelectorAll('[data-carrusel-3d]').forEach(construir3d);
}
