/**
 * carrusel.js
 * --------------------------------------------------------------
 * Convierte una rejilla ya existente (planes, testimonios, biblioteca de
 * ejercicios) en un carrusel que avanza solo en móvil, sin tocar su markup
 * de escritorio: el propio contenedor (".planes", ".testimonios",
 * ".biblioteca") pasa a ser la "pista" que se desliza — eso lo hace el CSS
 * bajo el breakpoint— y este módulo solo se encarga del avance automático.
 *
 * Sin botones ni puntos: el gesto de deslizar ya es obvio en un móvil y una
 * fila de controles debajo de cada sección era ruido. Se activa poniendo
 * data-carrusel="<ms>" en la pista.
 *
 * Bucle infinito, por dos caminos según lo que haya dentro:
 *
 *  - Pistas sin Alpine (planes, testimonios): se clonan las tarjetas una vez
 *    al final. Al cruzar el final del juego original se devuelve el scroll
 *    hacia atrás exactamente el ancho de un juego, de forma instantánea:
 *    como lo que se ve en pantalla es idéntico, el salto es invisible y el
 *    carrusel parece no terminarse nunca.
 *
 *  - Pistas con Alpine (la biblioteca filtra con x-show y abre un modal con
 *    @click): NO se clona. Duplicar nodos que Alpine gobierna significa
 *    duplicar su estado y sus manejadores, y el filtro ya reordena la pista
 *    en cada toque. Ahí el bucle vuelve al principio de una sola vez,
 *    instantáneo en vez de rebobinando a la vista.
 *
 * En ambos casos el avance se pausa mientras la persona desliza a mano y se
 * reanuda sola después, no corre si la pista no está en pantalla o la
 * pestaña está oculta, y respeta prefers-reduced-motion.
 */

/** Las tarjetas reales de la pista: ni clones nuestros ni filtradas por Alpine. */
function tarjetasVisibles(pista) {
    return [...pista.children].filter(
        (el) => el.offsetParent !== null && !el.dataset.carruselClon
    );
}

/** Una pista gobernada por Alpine no se puede clonar sin duplicar su estado. */
function tieneAlpine(pista) {
    return [...pista.children].some((el) =>
        [...el.attributes].some((a) => a.name.startsWith('x-') || a.name.startsWith('@'))
    );
}

function construirCarrusel(pista) {
    const intervaloMs = Number(pista.dataset.carrusel);
    if (!intervaloMs) return;

    const menosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)');
    // El CSS solo convierte la rejilla en pista deslizable bajo este ancho;
    // por encima no hay nada que avanzar (la rejilla se ve entera).
    const esMovil = window.matchMedia('(max-width: 740px)');
    const clonable = !tieneAlpine(pista);

    let items = [];
    let clones = [];
    let anchoJuego = 0;
    let temporizador = null;
    let enPantalla = false;
    let reanudar;
    let scrollProgramado = false;

    function detener() {
        clearInterval(temporizador);
        temporizador = null;
    }

    function activo() {
        const esCarruselTotal = pista.classList.contains('ubicaciones__pista--carrusel');
        return (esMovil.matches || esCarruselTotal) && !menosMovimiento.matches;
    }

    function iniciar() {
        detener();
        if (!enPantalla || document.hidden || !activo() || items.length < 2) return;
        temporizador = setInterval(avanzar, intervaloMs);
    }

    /** Deslizar a mano gana: se pausa y se retoma tras un respiro. */
    function pausarYReanudar() {
        detener();
        clearTimeout(reanudar);
        reanudar = setTimeout(iniciar, intervaloMs * 2);
    }

    function limpiarClones() {
        clones.forEach((c) => c.remove());
        clones = [];
    }

    /**
     * El "periodo" del bucle: la distancia entre una tarjeta y su clon. Se
     * mide así, y no como (fin del último − inicio del primero), porque esa
     * cuenta se deja fuera el hueco entre la última original y el primer
     * clon: restar de menos deja el carrusel corrido unos píxeles en cada
     * vuelta, y el desfase se va acumulando hasta hacerse visible.
     */
    function medirJuego() {
        if (!items.length || !clones.length) return 0;
        return clones[0].offsetLeft - items[0].offsetLeft;
    }

    function reconstruir() {
        limpiarClones();
        items = tarjetasVisibles(pista);

        if (!activo() || items.length < 2) {
            detener();
            return;
        }

        if (clonable) {
            items.forEach((el) => {
                const clon = el.cloneNode(true);
                clon.dataset.carruselClon = '1';
                // Duplicado visual, no contenido nuevo: los lectores de
                // pantalla no deben anunciarlo dos veces.
                clon.setAttribute('aria-hidden', 'true');
                pista.appendChild(clon);
                clones.push(clon);
            });
        }

        anchoJuego = medirJuego();
        iniciar();
    }

    /**
     * El truco del bucle sin costuras: pasado un periodo completo, se resta
     * ese periodo de golpe. Lo que se ve antes y después del salto es
     * idéntico (una tarjeta y su clon), así que no se percibe.
     *
     * Se asigna scrollLeft directamente en vez de scrollTo({behavior}): es
     * instantáneo por definición y no depende de que el navegador acepte el
     * valor 'instant'.
     */
    function recolocarSiHaceFalta() {
        if (!clonable || !anchoJuego) return;
        if (pista.scrollLeft >= anchoJuego) pista.scrollLeft -= anchoJuego;
    }

    function avanzar() {
        if (!items.length) return;

        // El reajuste va ANTES de animar, nunca durante: un scroll suave
        // apunta a una posición absoluta, así que mover scrollLeft a mitad
        // de la animación haría que el navegador siguiera hacia el destino
        // viejo y el salto se vería.
        recolocarSiHaceFalta();

        const anchoTarjeta = items[0].offsetWidth + 16;   // ~ tarjeta + hueco
        let destino = pista.scrollLeft + anchoTarjeta;

        // Sin clones (biblioteca) no hay periodo que restar: al llegar al
        // final se vuelve al principio de una sola vez, no rebobinando a la
        // vista.
        if (!clonable && destino >= pista.scrollWidth - pista.clientWidth - 8) {
            pista.scrollLeft = 0;
            return;
        }

        scrollProgramado = true;
        pista.scrollTo({ left: destino, behavior: 'smooth' });
        setTimeout(() => { scrollProgramado = false; }, 700);
    }

    let quieto;
    pista.addEventListener('scroll', () => {
        // Solo un arrastre real de la persona pausa: los scroll que
        // provocamos nosotros al avanzar no cuentan.
        if (scrollProgramado) return;

        pausarYReanudar();

        // Tras un arrastre manual también puede hacer falta reajustar, pero
        // recién cuando el desplazamiento se detiene: hacerlo con el dedo
        // todavía en movimiento se siente como un tirón.
        clearTimeout(quieto);
        quieto = setTimeout(recolocarSiHaceFalta, 140);
    }, { passive: true });

    new IntersectionObserver(([entrada]) => {
        enPantalla = entrada.isIntersecting;
        enPantalla ? iniciar() : detener();
    }, { threshold: .3 }).observe(pista);

    document.addEventListener('visibilitychange', () => (document.hidden ? detener() : iniciar()));

    let redimension;
    window.addEventListener('resize', () => {
        clearTimeout(redimension);
        redimension = setTimeout(reconstruir, 200);
    });

    // La biblioteca filtra con x-show (alterna style="display:none"): al
    // cambiar el filtro cambian las tarjetas visibles y hay que recontar.
    // Solo se observan las tarjetas reales — un clon cambiando de estilo no
    // debe disparar otra reconstrucción (sería un bucle sin fin).
    if (!clonable) {
        let mutacion;
        new MutationObserver(() => {
            clearTimeout(mutacion);
            mutacion = setTimeout(reconstruir, 60);
        }).observe(pista, { attributes: true, attributeFilter: ['style'], subtree: true });
    }

    esMovil.addEventListener('change', reconstruir);
    reconstruir();
}

export function iniciarCarruseles() {
    document.querySelectorAll('[data-carrusel]').forEach(construirCarrusel);
}
