/**
 * carrusel.js
 * --------------------------------------------------------------
 * Convierte una rejilla ya existente (planes, testimonios, biblioteca de
 * ejercicios) en un carrusel con scroll-snap en móvil, sin tocar su markup
 * de escritorio: el propio contenedor (".planes", ".testimonios",
 * ".biblioteca") pasa a ser la "pista" que se desliza — solo con CSS bajo
 * el breakpoint— y este módulo añade la paginación (puntos o flechas) y la
 * sincroniza con el scroll.
 *
 * Dos modos, elegidos con data-carrusel-modo en el control:
 *  - "puntos":  un punto por tarjeta, pensado para pocos elementos (planes,
 *    testimonios). Se pinta una vez porque el número de tarjetas no cambia.
 *  - "flechas": contador "X / N" + botones prev/next, pensado para listas
 *    largas y filtrables (biblioteca de ejercicios): reconstruir un punto
 *    por cada uno de 18 ejercicios sería más ruido que ayuda.
 *
 * La biblioteca además filtra con Alpine (x-show, que alterna
 * style="display:none"): un MutationObserver sobre ese atributo, dentro de
 * la propia pista, detecta el cambio de filtro sin acoplarse a los botones
 * ni a los tiempos de Alpine ($nextTick no siempre llegaba a tiempo aquí),
 * y recalcula qué tarjetas son visibles.
 *
 * Auto-avance opcional (data-carrusel-auto="<ms>" en el control): en bucle,
 * pero cuidando no gastar ciclos de más ni marear a quien lo está usando:
 *  - un solo setInterval por carrusel (nada de requestAnimationFrame en
 *    bucle) y solo mientras la pista está realmente en pantalla
 *    (IntersectionObserver) y la pestaña está visible;
 *  - se detiene apenas la persona arrastra la pista de verdad (no con
 *    cualquier toque: un "touchstart" también dispara al simplemente pasar
 *    el dedo por encima para bajar la página, así que el disparador real es
 *    el evento "scroll" — y solo cuando NO lo generamos nosotros mismos con
 *    irA(), marcado con la bandera "programado" — y retoma tras un respiro;
 *  - respeta prefers-reduced-motion: quien lo pidió, no ve nada moviéndose
 *    solo.
 */
function tarjetasVisibles(pista) {
    return [...pista.children].filter((el) => el.offsetParent !== null);
}

function activarAutoplay(control, pista, avanzar) {
    const intervaloMs = Number(control.dataset.carruselAuto);
    if (!intervaloMs || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return null;

    let temporizador = null;
    let enPantalla = false;
    let reanudar;

    function detener() {
        clearInterval(temporizador);
        temporizador = null;
    }
    function iniciar() {
        detener();
        if (!enPantalla || document.hidden) return;
        temporizador = setInterval(avanzar, intervaloMs);
    }
    function pausarYReanudar() {
        detener();
        clearTimeout(reanudar);
        reanudar = setTimeout(iniciar, intervaloMs);
    }

    new IntersectionObserver(([entrada]) => {
        enPantalla = entrada.isIntersecting;
        enPantalla ? iniciar() : detener();
    }, { threshold: .4 }).observe(pista);

    document.addEventListener('visibilitychange', () => (document.hidden ? detener() : iniciar()));

    // Un toque en los botones del control (flechas/puntos) sí cuenta como
    // interacción manual — eso no pasa "por accidente".
    control.addEventListener('click', pausarYReanudar);

    return { pausarYReanudar };
}

function construirCarrusel(control) {
    const pista = document.querySelector(control.dataset.carruselObjetivo);
    if (!pista) return;

    const modo = control.dataset.carruselModo || 'flechas';
    const contador = control.querySelector('[data-carrusel-contador]');
    const puntosCont = control.querySelector('[data-carrusel-puntos]');
    let items = [];
    let puntos = [];
    let activo = 0;
    // Distingue un scroll que provocamos nosotros (irA, incluido el propio
    // auto-avance) de uno que arrastró la persona a mano: solo el segundo
    // debe pausar el auto-avance (ver el listener de "scroll" más abajo).
    let scrollProgramado = false;

    function irA(indice) {
        const el = items[indice];
        if (!el) return;
        const centrado = el.offsetLeft - (pista.clientWidth - el.offsetWidth) / 2;
        scrollProgramado = true;
        pista.scrollTo({ left: Math.max(0, centrado), behavior: 'smooth' });
        // Un scroll suave tarda varios cuadros en completarse; se limpia la
        // bandera cuando ya debió terminar, no de inmediato.
        setTimeout(() => { scrollProgramado = false; }, 600);
        // No esperar al evento "scroll" para reflejar el cambio: en scroll
        // suave el navegador tarda varios cuadros en emitirlo (y en algún
        // entorno de prueba sin compositor activo no llega a emitirse
        // nunca), así que el punto/contador se actualiza ya, de una vez.
        activo = indice;
        pintar();
    }

    function pintar() {
        if (contador) contador.textContent = `${items.length ? activo + 1 : 0} / ${items.length}`;
        puntos.forEach((punto, i) => punto.classList.toggle('is-activo', i === activo));
        control.querySelectorAll('[data-carrusel-prev]').forEach((b) => { b.disabled = activo <= 0; });
        control.querySelectorAll('[data-carrusel-next]').forEach((b) => { b.disabled = activo >= items.length - 1; });
    }

    function actualizarActivo() {
        const centro = pista.scrollLeft + pista.clientWidth / 2;
        let mejor = 0;
        let mejorDistancia = Infinity;
        items.forEach((el, i) => {
            const distancia = Math.abs((el.offsetLeft + el.offsetWidth / 2) - centro);
            if (distancia < mejorDistancia) {
                mejorDistancia = distancia;
                mejor = i;
            }
        });
        activo = mejor;
        pintar();
    }

    function reconstruir() {
        items = tarjetasVisibles(pista);
        activo = 0;
        pista.scrollTo({ left: 0 });

        if (modo === 'puntos' && puntosCont) {
            puntosCont.innerHTML = '';
            puntos = items.map((_, i) => {
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.className = 'carrusel__punto';
                boton.setAttribute('aria-label', `Ir a la tarjeta ${i + 1}`);
                boton.addEventListener('click', () => irA(i));
                puntosCont.appendChild(boton);
                return boton;
            });
        }
        pintar();
    }

    control.querySelectorAll('[data-carrusel-prev]').forEach((b) => {
        b.addEventListener('click', () => irA(Math.max(0, activo - 1)));
    });
    control.querySelectorAll('[data-carrusel-next]').forEach((b) => {
        b.addEventListener('click', () => irA(Math.min(items.length - 1, activo + 1)));
    });

    let cuadro;
    let autoplay;
    pista.addEventListener('scroll', () => {
        cancelAnimationFrame(cuadro);
        cuadro = requestAnimationFrame(actualizarActivo);
        // Arrastre real del usuario (no nuestro propio irA): pausa el
        // auto-avance en vez de pelearse con la mano de quien lo mueve.
        if (!scrollProgramado) autoplay?.pausarYReanudar();
    }, { passive: true });

    window.addEventListener('resize', reconstruir);

    // En planes/testimonios el número de tarjetas nunca cambia, así que
    // esto no hace nada de más; en la biblioteca es lo que detecta el
    // filtro de Alpine sin depender de sus tiempos internos.
    let mutacion;
    new MutationObserver(() => {
        clearTimeout(mutacion);
        mutacion = setTimeout(reconstruir, 60);
    }).observe(pista, { attributes: true, attributeFilter: ['style'], subtree: true });

    reconstruir();

    // Bucle: de la última tarjeta vuelve a la primera en vez de detenerse.
    autoplay = activarAutoplay(control, pista, () => irA((activo + 1) % Math.max(items.length, 1)));
}

export function iniciarCarruseles() {
    document.querySelectorAll('[data-carrusel-control]').forEach(construirCarrusel);
}
