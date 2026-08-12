/**
 * animations.js
 * --------------------------------------------------------------
 * Animaciones con GSAP. Dos reglas que evitan que esto se convierta en
 * ruido visual:
 *
 *   1. Un momento orquestado (la entrada del hero) pesa más que veinte
 *      efectos sueltos. El resto de la página se limita a revelar.
 *   2. Nada bloquea la lectura: si GSAP no carga o el usuario pide menos
 *      movimiento, todo queda visible en su sitio.
 */

import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const MENOS_MOVIMIENTO = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ============================================================
   Entrada del hero: la única secuencia coreografiada de la página
   ============================================================ */
function hero() {
    const raiz = document.querySelector('[data-hero]');
    if (!raiz) return;

    const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });

    tl.from('[data-hero-eyebrow]', { opacity: 0, x: -20, duration: .7 })
      // Las tres palabras del lema entran escalonadas, como se carga una
      // barra: HIERRO, SUDOR, SANGRE.
      .from('[data-hero-titulo] span', {
          opacity: 0,
          y: 70,
          rotateX: -45,
          duration: 1.1,
          stagger: .11,
      }, '-=.35')
      .from('[data-hero-texto]', { opacity: 0, y: 24, duration: .8 }, '-=.6')
      .from('[data-hero-acciones] > *', { opacity: 0, y: 20, duration: .6, stagger: .09 }, '-=.5')
      .from('[data-hero-cifra]', { opacity: 0, y: 18, duration: .6, stagger: .07 }, '-=.4');
}

/* ============================================================
   Revelado al entrar en pantalla
   ============================================================ */
function revelados() {
    document.querySelectorAll('[data-revelar]').forEach((el) => {
        // Un contenedor con data-revelar-grupo escalona a sus hijos.
        const esGrupo = el.hasAttribute('data-revelar-grupo');
        const hijos = esGrupo ? el.children : [el];

        gsap.to(hijos, {
            opacity: 1,
            y: 0,
            duration: .9,
            ease: 'power3.out',
            stagger: esGrupo ? .08 : 0,
            scrollTrigger: {
                trigger: el,
                start: 'top 85%',
                once: true,
            },
        });

        // El contenedor con grupo también revela su propio marco; sin esto
        // se quedaría en la opacidad inicial del CSS y ocultaría a sus hijos.
        if (esGrupo) {
            gsap.to(el, { opacity: 1, y: 0, duration: .5, ease: 'power3.out' });
        }
    });
}

/* ============================================================
   Contadores
   ============================================================ */
function contadores() {
    document.querySelectorAll('[data-contador]').forEach((el) => {
        const destino = parseFloat(el.dataset.contador);
        const sufijo = el.dataset.sufijo ?? '';
        const objeto = { valor: 0 };

        gsap.to(objeto, {
            valor: destino,
            duration: 1.8,
            ease: 'power2.out',
            // 'top 95%' (no 90%): en viewports altos y móviles, la última
            // cifra del hero queda apenas debajo del pliegue en la primera
            // pantalla — con 90% se quedaba en 0 hasta que alguien hacía
            // scroll. 95% la dispara ya en la carga inicial.
            scrollTrigger: { trigger: el, start: 'top 95%', once: true },
            onUpdate: () => {
                el.textContent = Math.round(objeto.valor).toLocaleString('es-PE') + sufijo;
            },
        });
    });
}

/* ============================================================
   El riel moleteado: avance de lectura
   ============================================================ */
function riel() {
    const avance = document.querySelector('[data-riel-avance]');
    const cifra = document.querySelector('[data-riel-cifra]');
    if (!avance) return;

    ScrollTrigger.create({
        trigger: document.body,
        start: 'top top',
        end: 'bottom bottom',
        onUpdate: ({ progress }) => {
            const pct = Math.round(progress * 100);
            avance.style.height = `${pct}%`;
            if (cifra) cifra.textContent = String(pct).padStart(2, '0') + '%';
        },
    });
}

/* ============================================================
   Pilas de discos: el dato se "carga" como una barra
   ============================================================ */
function discos() {
    document.querySelectorAll('[data-discos]').forEach((pila) => {
        const cargados = Number(pila.dataset.discos);
        const piezas = pila.querySelectorAll('.discos__disco');

        ScrollTrigger.create({
            trigger: pila,
            start: 'top 88%',
            once: true,
            onEnter: () => {
                piezas.forEach((disco, i) => {
                    // Cada disco entra con su propio retardo: se lee como
                    // alguien cargando la barra, no como una barra que crece.
                    gsap.to(disco, {
                        height: disco.dataset.alto,
                        duration: .5,
                        delay: i * .06,
                        ease: 'back.out(2)',
                        onStart: () => {
                            if (i < cargados) disco.dataset.cargado = 'si';
                        },
                    });
                });
            },
        });
    });
}

/* ============================================================
   Parallax suave en elementos marcados
   ============================================================ */
function parallax() {
    document.querySelectorAll('[data-parallax]').forEach((el) => {
        gsap.to(el, {
            yPercent: Number(el.dataset.parallax) || -12,
            ease: 'none',
            scrollTrigger: {
                trigger: el,
                start: 'top bottom',
                end: 'bottom top',
                scrub: true,
            },
        });
    });
}

/* ============================================================ */

export function iniciarAnimaciones() {
    // Con "menos movimiento" no se anima nada, pero hay que deshacer el estado
    // inicial que el CSS deja preparado o la página se quedaría en blanco.
    if (MENOS_MOVIMIENTO) {
        gsap.set('[data-revelar]', { opacity: 1, y: 0, clearProps: 'transform' });
        document.querySelectorAll('[data-contador]').forEach((el) => {
            el.textContent = Number(el.dataset.contador).toLocaleString('es-PE') + (el.dataset.sufijo ?? '');
        });
        riel();
        return;
    }

    hero();
    revelados();
    contadores();
    riel();
    discos();
    parallax();

    // Las tipografías cambian las alturas al cargar; sin esto, los disparadores
    // quedan calculados sobre una maqueta que ya no existe.
    document.fonts?.ready.then(() => ScrollTrigger.refresh());
}
