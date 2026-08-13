# Plan de mejora: carruseles con profundidad en la landing

Objetivo: que los carruseles de la web pública pasen de "rejilla que desliza
en móvil" a **piezas con profundidad e interacción** que retengan la mirada,
sin tocar la paleta, la tipografía ni la identidad del sistema de tokens.

## Estado actual (inventario)

Los tres carruseles viven en el mismo mecanismo (`carrusel.js` + `data-carrusel`):

| Sección | Pista | Intervalo | Contenido |
|---|---|---|---|
| Planes | `.planes` (`planes.blade.php:12`) | 3200 ms | 3–6 tarjetas de precio |
| Testimonios | `.testimonios` (`testimonios.blade.php:9`) | 3600 ms | citas con estrellas y autor |
| Biblioteca | `.biblioteca` (`ejercicios.blade.php:28`) | 4000 ms | hasta ~18 ejercicios, filtro Alpine |

Comportamiento actual:

- **Solo móvil** (`max-width: 740px`): la rejilla se vuelve pista con
  `scroll-snap-type: x mandatory` y avance automático en bucle.
  CSS: `landing.css:752` (planes), `:875` (testimonios), `:1140` (biblioteca).
- Desktop = rejilla estática (todo visible, sin carrusel).
- `carrusel.js` maneja el avance, el bucle (clonando tarjetas sin Alpine;
  sin clonar la biblioteca, que es gobernada por Alpine), la pausa al
  deslizar a mano y respeta `prefers-reduced-motion`.
- Las tarjetas ya tienen `.tarjeta--interactiva`: brillo que sigue al cursor
  y una inclinación de 6° máx (`interacciones.js`, con `--brillo-x/y` y
  `--inclina-x/y`). **El 3D propuesto construye sobre esto, no compite con él.**

## Reglas que no se tocan

- Ningún color, tamaño o duración a mano: todo de `tokens.css`.
- `prefers-reduced-motion` → anula 3D y autoplay (queda el scroll plano).
- Sin dependencias nuevas (vanilla, como todo el front).
- La biblioteca tiene Alpine (`x-show`): no clonar, no duplicar estado.
- El revelado GSAP (`data-revelar`) no debe chocar con las transformaciones:
  el 3D se aplica después de que la tarjeta entra en pantalla.
- Contraste siempre vía escala/opacidad de las tarjetas **vecinas**, nunca
  cambiando la paleta (la marca no se "enfría"; las vecinas se atenúan).

## Propuestas

### P1 — Coverflow: la activa al frente, las vecinas replegadas

**Qué es.** La tarjeta centrada sube en Z (`translateZ` + escala leve) y las
vecinas retroceden, se apagan y giran un par de grados en Y. El clásico
"carrusel en 3D", contenido: un plato frontal, no un pasillo de cartas.

**Técnica.** `perspective` en un wrapper interior de la pista (para no chocar
con el `overflow-x: auto`, ver riesgos). En el evento `scroll` (pasivo), por
cada tarjeta se calcula su desfase al centro de la pista, normalizado a
`[-1, 1]` y se escribe como `--desfase` (CSS variable por tarjeta). El CSS
aplica la transformación: activa `translateZ(40px) scale(1.04)` + sombra
`--s-fuego`; vecinas `scale(.92)` + `opacity` decreciente; transiciones con
`--v-medio` y `--curva`. JS solo escribe variables, no anima (rendimiento).

**Dónde.** Testimonios (ideal: el que se lee está al frente). Biblioteca
(suave, para que los filtros sigan mandando). Planes: **no**, o solo el
destacado — comparar precios pide quietud.

**Esfuerzo:** medio. **Riesgo:** medio (clip de 3D en scroll horizontal).

### P2 — Contraste por atenuación, no por color

**Qué es.** Complemento de P1: las vecinas bajan `opacity` y un `grayscale`
parcial (ej. 40%) mientras la activa conserva todo su fuego. El ojo va solo.

**Técnica.** `filter` por CSS según `--desfase`. Los tonos de marca no se
tocan: el brillo del centro es relativo al apagado de los lados.

**Dónde.** Testimonios y biblioteca (con P1). **Esfuerzo:** trivial.

### P3 — Línea de avance "fuego" sobre la activa

**Qué es.** El autoplay hoy no da feedback. Una línea fina bajo la tarjeta
activa, con el gradiente `--fuego`, que avanza 0→100% sincronizada con el
intervalo y se **pausa** al tocar/arrastrar (mismo evento que ya pausa el
avance en `carrusel.js`).

**Técnica.** Barra con `transform: scaleX` y `transform-origin: left`,
actualizada por `requestAnimationFrame` con la fecha de la última
reanudación (pausa transparente al ocultarse la pestaña o salir de pantalla
si se reutiliza la lógica de pausa existente). `prefers-reduced-motion` → no
se dibuja.

**Dónde.** Las tres secciones en móvil. **Esfuerzo:** bajo. **Riesgo:** bajo.

### P4 — Indicador de posición "riel" + contador mono

**Qué es.** El elemento firma de la marca (el riel moleteado) como indicador
de posición, más un contador de instrumentación: `03 / 12` en `--f-mono`.

**Técnica.** Una micro-barrita con el mismo degradado repetido del riel
(`.riel`) y una muesca que se mueve por posición; el contador se actualiza
desde el mismo cálculo de desfase de P1. Sin puntos de paginación (la nota de
la biblioteca ya anuncia "X / N"; hoy esa pieza no existe: `carrusel.js` no
dibuja flechas ni contador).

**Dónde.** Biblioteca (imprescindible con 18 tarjetas) y, opcional,
testimonios. **Esfuerzo:** bajo. **Riesgo:** bajo.

### P5 — Capa de profundidad en el tilt existente

**Qué es.** Refinar `.tarjeta--interactiva` para que el tilt actual de 6° gane
verdadera profundidad: `transform-style: preserve-3d` en la tarjeta y cada
capa (filo, icono, cabecera) en su propio `translateZ`. El brillo y la
inclinación que ya existen se sienten "de hierro", no de cartón.

**Técnica.** `perspective` en el padre + `translateZ` por capa, todo con
tokens de movimiento. Solo `(hover: hover) and (pointer: fine)`, igual que ya
hace `interacciones.js:15`.

**Dónde.** Todas las tarjetas interactivas de la landing. **Esfuerzo:** bajo.
**Riesgo:** bajo.

### P6 — Desktop interactivo (opcional, mayor alcance)

**Qué es.** Hoy el 3D solo se vería en móvil. Convertir testimonios (y
opcionalmente la biblioteca) en carrusel **arrastrable también en desktop**
(ratón + rueda + snap), con el coverflow de P1. La rejilla de testimonios deja
de verse entera, pero la sección gana presencia.

**Técnica.** Reutilizar `carrusel.js` ampliando el breakpoint (hoy fijo en
740 px) y sumar el gesto de arrastre (mismo patrón de pausa manual). Planes
queda rejilla en desktop siempre.

**Esfuerzo:** alto. **Riesgo:** alto (cambia el comportamiento actual de
escritorio; requiere verificación visual dedicada).

## Combinación recomendada

- **Testimonios** (la sección emocional): P1 + P2 + P3 + P5. El 3D está en
  su sitio: una voz al frente, el resto en la penumbra.
- **Biblioteca** (la más grande): P1 suave + P2 + P3 + P4. El contador
  "X / N" es obligatorio ahí; los filtros de Alpine no se tocan.
- **Planes** (comparar precios): **sin 3D**. Solo P3 en móvil y P5. El
  destacado ya manda con `--acento-plan`; mover precios en 3D estorba la
  decisión. La disciplina es parte de la marca: no todo gira.

## Fases sugeridas para el agente

- **Fase A (bajo riesgo, sin tocar estructura):** P5 (profundidad del tilt) +
  P3 (línea de avance). Módulo JS nuevo `carrusel-avance.js` o extensión de
  `carrusel.js`; CSS en `landing.css`. Verificación: `npm run build`.
- **Fase B (el efecto):** P1 + P2 en testimonios y biblioteca. Nuevo módulo
  `carrusel-3d.js` (escribe `--desfase`, no anima) importado en
  `app-public.js`. Cuidado con el clip 3D en pista con `overflow-x: auto`.
- **Fase C (opcional):** P6 (desktop arrastrable) y P4 (riel + contador) si
  la Fase B se siente bien.

## Archivos implicados

- `resources/js/carrusel.js` (pausa/avance existente — reutilizar sus eventos)
- `resources/js/app-public.js` (import del módulo nuevo)
- `resources/css/landing.css` (`:752`, `:875`, `:1140` + bloque 3D nuevo)
- `resources/views/landing/sections/planes.blade.php` (solo P3/P5)
- `resources/views/landing/sections/testimonios.blade.php` (sin cambios de
  markup si el 3D es puramente CSS-var; solo si se añade P4)
- `resources/views/landing/sections/ejercicios.blade.php` (ídem)
- Referencia de sistema: `tokens.css` (`--fuego`, `--s-fuego`, `--v-*`,
  `--curva*`, `--f-mono`), `interacciones.js`, `components.css`
  (`.tarjeta--interactiva`, `.riel`)

## Fuera de alcance

- Panel `/panel`, `/entrenador`, `/mi-cuenta` (solo landing pública).
- La galería (`galeria` en landing) y los bentos de beneficios/instalaciones:
  son retículas de diseño deliberado, no carruseles.
- Cambiar el avance automático de `carrusel.js` (el bucle, los clones y la
  pausa manual ya funcionan y se reutilizan).
