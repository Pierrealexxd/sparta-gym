/**
 * ui.js
 * --------------------------------------------------------------
 * Comportamientos de interfaz que no son animación: navegación, menú
 * móvil y acordeón. Todo funciona sin GSAP, de modo que si las
 * animaciones fallan la web sigue siendo usable.
 */

/** Cabecera: se condensa y se vuelve cristal al despegarse de arriba. */
function cabecera() {
    const nav = document.querySelector('[data-nav]');
    if (!nav) return;

    // IntersectionObserver sobre un centinela en el tope de la página: es más
    // barato que escuchar scroll y recalcular en cada cuadro.
    const centinela = document.createElement('div');
    centinela.style.cssText = 'position:absolute;top:0;height:80px;width:1px;pointer-events:none';
    document.body.prepend(centinela);

    new IntersectionObserver(
        ([entrada]) => nav.classList.toggle('is-pegada', !entrada.isIntersecting),
        { threshold: 0 }
    ).observe(centinela);
}

/** Menú móvil: cajón lateral que entra desde la izquierda. */
function menuMovil() {
    const boton = document.querySelector('[data-menu]');
    const panel = document.querySelector('[data-menu-panel]');
    const velo = document.querySelector('[data-menu-velo]');
    // Aspa propia dentro del cajón (ver nav.blade.php): mismo cierre que el
    // velo o Escape, distinto del botón que lo abre desde la barra.
    const botonesCerrar = document.querySelectorAll('[data-menu-cerrar]');
    if (!boton || !panel) return;

    const alternar = (abrir) => {
        boton.setAttribute('aria-expanded', String(abrir));
        panel.classList.toggle('is-abierto', abrir);
        velo?.classList.toggle('is-abierto', abrir);
        // Bloquear el scroll del fondo mientras el menú está abierto.
        document.body.style.overflow = abrir ? 'hidden' : '';
    };

    boton.addEventListener('click', () => {
        alternar(boton.getAttribute('aria-expanded') !== 'true');
    });

    botonesCerrar.forEach((b) => b.addEventListener('click', () => alternar(false)));

    // Tocar el velo (el resto de la pantalla) cierra el cajón.
    velo?.addEventListener('click', () => alternar(false));

    // Al elegir destino, o al tocar el velo, el menú se retira solo.
    panel.addEventListener('click', (e) => {
        if (e.target.closest('a')) alternar(false);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') alternar(false);
    });
}

/**
 * Acordeón de preguntas frecuentes.
 * La altura se anima con CSS sobre un valor calculado, no con GSAP: son
 * cuatro líneas y no merece cargar una dependencia para esto.
 */
function acordeon() {
    document.querySelectorAll('[data-faq]').forEach((item) => {
        const boton = item.querySelector('.faq__pregunta');
        const panel = item.querySelector('.faq__respuesta');
        if (!boton || !panel) return;

        panel.style.transition = 'height .42s cubic-bezier(.22,.61,.36,1)';

        boton.addEventListener('click', () => {
            const abierto = item.classList.contains('is-abierto');

            // Sólo una abierta a la vez: leer una respuesta no debería
            // obligar a buscar de nuevo la siguiente pregunta.
            document.querySelectorAll('[data-faq].is-abierto').forEach((otro) => {
                if (otro === item) return;
                otro.classList.remove('is-abierto');
                otro.querySelector('.faq__respuesta').style.height = '0px';
                otro.querySelector('.faq__pregunta').setAttribute('aria-expanded', 'false');
            });

            item.classList.toggle('is-abierto', !abierto);
            boton.setAttribute('aria-expanded', String(!abierto));
            panel.style.height = abierto ? '0px' : `${panel.scrollHeight}px`;
        });
    });
}

/** Marca en la navegación la sección que se está viendo. */
function seccionActiva() {
    const enlaces = new Map();

    document.querySelectorAll('.nav__enlace[href^="#"]').forEach((a) => {
        const destino = document.querySelector(a.getAttribute('href'));
        if (destino) enlaces.set(destino, a);
    });

    if (!enlaces.size) return;

    const observador = new IntersectionObserver(
        (entradas) => {
            entradas.forEach((e) => {
                if (e.isIntersecting) {
                    enlaces.forEach((a) => a.removeAttribute('aria-current'));
                    enlaces.get(e.target)?.setAttribute('aria-current', 'true');
                }
            });
        },
        // Franja estrecha en el tercio superior: la sección "activa" es la que
        // está entrando, no la que ocupa más pantalla.
        { rootMargin: '-20% 0px -70% 0px' }
    );

    enlaces.forEach((_, seccion) => observador.observe(seccion));
}

/**
 * Tooltips del sidebar comprimido. El tooltip va en position fixed (para
 * escapar del overflow de la barra) y su centro vertical se entrega con la
 * variable --y. Se recalcula al hacer scroll —la lista puede ser larga—
 * y al redimensionar.
 */
function tooltipsPanel() {
    const barra = document.querySelector('.panel__lateral');
    const enlaces = [...document.querySelectorAll('.panel__enlace[data-title], .panel__sede-boton[data-title]')];
    if (!barra || !enlaces.length) return;

    // El que realmente scrollea es .panel__nav (flex:1, min-height:0,
    // overflow-y:auto), no la barra, y los eventos de scroll no burbujean:
    // escuchar solo en la barra dejaba --y caduco al mover la lista, y el
    // tooltip salía centrado sobre la fila equivocada (la de abajo).
    const nav = barra.querySelector('.panel__nav');

    const situar = (a) => {
        a.style.setProperty('--y', `${a.getBoundingClientRect().top + a.offsetHeight / 2}px`);
    };
    const posicionar = () => enlaces.forEach(situar);

    posicionar();

    // Y la garantía de que nunca salga caduco: al entrar el cursor se recalcula
    // ese elemento en el acto. El tooltip aparece con retardo de animación, así
    // que --y llega fresco aunque un transitionend o un resize se hayan escapado.
    enlaces.forEach((a) => a.addEventListener('pointerenter', () => situar(a)));
    // Ambos por si una pantalla baja hace que la barra misma llegue a
    // scrollear; el que no scrollee simplemente no dispara nunca.
    barra.addEventListener('scroll', posicionar, { passive: true });
    nav?.addEventListener('scroll', posicionar, { passive: true });
    window.addEventListener('resize', posicionar);

    // Comprimir/expandir cambia el alto de la cabecera (padding distinto),
    // lo que desplaza verticalmente cada enlace. Sin esto, --y se queda con
    // la posición de antes de comprimir hasta el próximo scroll o resize:
    // el tooltip aparece desalineado justo después de tocar el botón.
    barra.addEventListener('transitionend', (e) => {
        if (e.propertyName === 'width') posicionar();
    });
}

/**
 * Interruptor claro/oscuro del panel. El tema se persiste en localStorage
 * ("temaPanel") y el estado inicial lo resuelve el script inline del layout
 * antes del primer pintado; aquí sólo se alterna y se avisa a quien quiera
 * reaccionar (los gráficos se reconstruyen con su paleta nueva).
 */
function temaPanel() {
    const boton = document.querySelector('[data-tema-boton]');
    if (!boton) return;

    const aplicar = (tema) => {
        document.documentElement.setAttribute('data-tema', tema);
        localStorage.setItem('temaPanel', tema);
        const meta = document.getElementById('tema-color');
        if (meta) meta.setAttribute('content', tema === 'claro' ? '#F4F2EE' : '#0A0A0B');
        document.dispatchEvent(new CustomEvent('tema:refrescar', { detail: tema }));
    };

    boton.addEventListener('click', () => {
        const actual = document.documentElement.getAttribute('data-tema') === 'claro' ? 'claro' : 'oscuro';
        aplicar(actual === 'claro' ? 'oscuro' : 'claro');
    });
}

/**
 * Splash de entrada (sólo landing, ver layouts/public.blade.php): se retira
 * apenas la página está lista, con un mínimo de medio segundo para que el
 * pulso se perciba incluso en redes rápidas — pero nunca más que eso, y
 * mucho menos si el usuario pidió menos movimiento.
 */
function splash() {
    const el = document.querySelector('[data-splash]');
    if (!el) return;

    const menosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const minimoMs = menosMovimiento ? 120 : 500;

    const ocultar = () => {
        el.setAttribute('data-oculto', '');
        el.addEventListener('transitionend', () => el.remove(), { once: true });
        // Por si transitionend no llega (pestaña en segundo plano, etc.).
        setTimeout(() => el.remove(), 900);
    };

    const minimo = new Promise((resolve) => setTimeout(resolve, minimoMs));
    const listo = document.readyState === 'complete'
        ? Promise.resolve()
        : new Promise((resolve) => window.addEventListener('load', resolve, { once: true }));

    Promise.all([minimo, listo]).then(ocultar);
}

export function iniciarInterfaz() {
    splash();
    cabecera();
    menuMovil();
    acordeon();
    seccionActiva();
    tooltipsPanel();
    temaPanel();
}
