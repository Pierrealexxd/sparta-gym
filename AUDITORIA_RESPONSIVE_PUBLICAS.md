# Auditoría responsive — Páginas públicas

**Proyecto:** Sparta Gym (Laravel 12 · Blade · CSS propio · Alpine · GSAP)
**Fecha:** 2026-08-11
**Páginas auditadas:** `/` (landing), `/acceso` (login), `/registro` (registro)
**Método:** navegador headless (Chromium). Mediciones reales de layout: overflow horizontal, anchos de contenedores, targets táctiles, tipografías y flujos interactivos (menú, modal de video, acordeón, envío de login). Sin captura visual (entorno sin soporte de imagen).
**Viewports probados:** 320×568 · 375×812 (móvil) · 768×1024 (tablet) · 1024×768 · 1280×800 (escritorio)

---

## Resumen ejecutivo

Las tres páginas públicas **no presentan overflow horizontal** en móvil ni tablet: no hay barra de scroll lateral en ningún punto de la landing, el login ni el registro. Los grids colapsan bien a 1 columna en móvil y a 2 en tablet, y los flujos interactivos (menú hamburguesa, modal de video, acordeón de FAQ, error de login) funcionan correctamente.

Se detectaron **6 hallazgos**: 1 de prioridad media que afecta a la conversión (acceso oculto en móvil/tablet), 1 de jerarquía tipográfica, 1 de overflow en pantallas muy pequeñas (320px), y 3 menores.

| # | Prioridad | Hallazgo | Página | Estado |
|---|-----------|----------|--------|--------|
| 1 | **Media** | "Acceder" oculto en móvil/tablet y ausente del menú hamburguesa | Landing | Abierto |
| 2 | **Media** | Nav desborda 26px a la derecha en pantallas de 320px | Landing | Abierto |
| 3 | **Media** | H1 del hero a 21px vs. H2 de sección a 40px en móvil (jerarquía invertida) | Landing | Abierto |
| 4 | Baja | Cifra "Clientes activos" muestra "0+" por caché caducada | Landing | Abierto |
| 5 | Baja | Texto "Enviar mensaje" desborda 7px su botón a 320px | Landing | Abierto |
| 6 | Baja | Enlaces de teléfono/correo con altura táctil de 17-19px | Landing | Abierto |

---

## Hallazgo 1 — "Acceder" inaccesible en móvil y tablet (Media)

**Síntoma.** En el breakpoint de escritorio (≥1024px) el nav muestra los botones "Acceder" e "Inscribirme". Por debajo de 1024px, el botón "Acceder" se oculta con `display: none` y el menú hamburguesa **no lo incluye**.

**Evidencia.**
- 375px: `Acceder → display:none`; menú panel contiene solo: Historia, Biblioteca, Guías, Planes, Preguntas, Contacto.
- 768px: `Acceder → display:none`; hamburguesa visible y operativa; los enlaces inline solo aparecen a partir de 1024px.
- El footer tampoco enlaza a `/acceso`.

**Impacto.** Un usuario que ya es socio y quiere entrar desde el móvil o la tablet no tiene ninguna vía visible desde la landing. Única opción: teclear la URL `http://localhost:8000/acceso`.

**Recomendación.** Incluir un enlace "Acceder" dentro del menú hamburguesa (panel `[data-menu-panel]`) y/o mantener visible el botón "Acceder" compacto (solo texto o icono) junto al hamburguesa en móvil/tablet.

---

## Hallazgo 2 — Nav desborda el borde derecho a 320px (Media)

**Síntoma.** En 320×568 (iPhone SE 1ª gen, Android pequeños) la barra de navegación fija desborda el viewport y la hamburguesa queda **cortada 26px fuera de pantalla**.

**Evidencia** (320px):
```
logo: 85px + gap 24px + "Inscribirme" 165px + gap 12px + hamburguesa 44px
      → acciones terminan en right:346, viewport 320 → 26px fuera
```
- `nav__acciones`: left 124, width 221 → right 346 (viewport 320).
- Hamburguesa: 302→346. Solo ~18px visibles/tocables.
- El botón sigue funcionando (se abre el menú), pero se ve cortado y el target táctil queda reducido a un tercio.

**Recomendación.** Sobre ~360px, reducir el padding/gap del nav o compactar el botón "Inscribirme" (p. ej. ocultar el texto y dejar solo el icono), o permitir que el logo se acorte. Verificar el punto de ruptura mínimo de diseño.

---

## Hallazgo 3 — H1 del hero muy pequeño en móvil (Media)

**Síntoma.** El titular del hero "Hierro. Sudor. Sangre." se renderiza a **21.2px** en móvil (≤480px) mientras los títulos de sección (H2) se renderizan a **40.7px**. La jerarquía queda invertida: la declaración más importante de la marca es la más pequeña de la página.

**Evidencia.**
- 375px: `.hero__moto` → 21.185px. 1280px: 69px.
- Sección H2 (Historia, Planes, etc.) → 40.7px en el mismo móvil.
- Causa: `@media (max-width: 480px)` fuerza `--t-xl` (clamp 1.31→1.75rem) al hero, mientras las secciones usan `--t-3xl`.

**Impacto.** Subjetivo/percepción de marca: el lema pierde el impacto de la display Big Shoulders. Es legible, pero rompe la pirámide visual.

**Recomendación.** En `landing.css` (línea 284) subir el hero en móvil a `--t-2xl` (o un clamp propio ~1.75-2.25rem). Mantener las tres palabras apiladas; solo subir el tamaño.

---

## Hallazgo 4 — "Clientes activos: 0+" por caché caducada (Baja)

**Síntoma.** La fila inferior del hero muestra "0+ Clientes activos" y "0 Años abiertos" en la primera pantalla, junto a "36,903 Sesiones" y "3 Entrenadores".

**Evidencia.**
- La BD real tiene 2 socios activos y 3 entrenadores; `Member::activos()->count()` = 2.
- El hero renderizó `data-contador="0"` para clientes → la caché `landing.cifras` (1h) se generó cuando la BD estaba vacía y aún no ha expirado.
- "Años abiertos" (dato 7) aparece en "0" por otro motivo: el contador se dispara con ScrollTrigger `start: 'top 90%'` y esa cifra queda por debajo de la línea en pantallas altas (top 768 > 730 en un viewport de 812), así que anima al hacer scroll.

**Impacto.** En móvil, la fila de cifras — lo más "real" del hero según el proyecto — se ve incorrecta en el primer vistazo.

**Recomendación.** Además de dejar que la caché expire, conviene: invalidar `landing.cifras` cuando cambian socios/asistencias (o bajar el TTL), y adelantar el disparo de los contadores (`start: 'top 95%'`) para que animen en la primera pantalla.

---

## Hallazgo 5 — "Enviar mensaje" desborda su botón a 320px (Baja)

**Síntoma.** En el formulario de contacto a 320px, el botón de 220px de ancho no cabe su texto: `scrollWidth 227 > clientWidth 220` (7px de desborde). El texto se corta o sangra según el navegador.

**Evidencia.** `.btn--fuego.btn--bloque.btn--grande` en `.vidrio.formulario` → clientW 220, scrollW 227.

**Recomendación.** Permitir que el botón crezca con el texto (`min-width: max-content`) o reducir ligeramente su padding/fuente bajo 360px.

---

## Hallazgo 6 — Targets táctiles pequeños en pie/footer (Baja)

**Síntoma.** Los enlaces de contacto del footer y la barra superior (teléfono `+51 900 000 000`, `contacto@spartagym.pe`) y los enlaces de menú del pie tienen **17-20px de alto**, por debajo del mínimo recomendado de 24px.

**Evidencia.** 8 elementos con `h < 24px` en 375px y 768px (links de pie y enlaces de texto de la marca).

**Impacto.** Bajo: son enlaces de texto, fáciles de acertar. Solo molesta en uso real con el dedo.

**Recomendación.** Añadir `padding` vertical (~4px) y `line-height` mayor en los enlaces del pie.

---

## Comprobaciones que pasan correctamente

| Comprobación | Móvil 375 | Tablet 768 |
|---|---|---|
| Overflow horizontal de página | Ninguno | Ninguno |
| Meta viewport correcto | `width=device-width, initial-scale=1` | idem |
| Grids colapsan (planes, biblioteca, guías, testimonios) | 1 columna | 2 columnas |
| Menú hamburguesa abre/cierra y bloquea scroll (`body overflow:hidden`) | OK | OK |
| Enlaces del menú (76px de alto) | OK | — |
| Modal de video "Ver técnica" abre, ajusta (327×248) y cierra | OK | — |
| Acordeón FAQ abre sin cortes | OK | — |
| Login: tarjeta 343px móvil / 480px centrada tablet | OK | OK |
| Login: banner de error ("Esas credenciales no coinciden…") cabe y no desborda | OK | — |
| Registro: campos apilados 293px (móvil) / 446px (tablet), 56px alto | OK | OK |
| Footer: 1 columna móvil / 3 columnas tablet | OK | OK |
| Formulario de contacto: fila inicial 2 columnas en tablet | — | OK |
| Video del hero: autoplay + loop activo (readyState 4) | OK | OK |
| Sin errores de consola en ninguna página | OK | OK |

---

## Detalle por página

### Landing `/`
- 9 secciones (hero, historia, beneficios, ejercicios, guías, planes, testimonios, FAQ, contacto). Sin galería de imágenes en esta versión.
- Hero: contenido visible en la primera pantalla (top 281 en 375×812), vídeo autoplay OK. H1 pequeño (ver Hallazgo 3).
- Filtros de biblioteca: hacen wrap a 3 filas en móvil sin desborde (Todas/Fuerza/Cardio / Movilidad/Core / Funcional).
- Cifras del hero: wrap 2×2 centrado en móvil (ver Hallazgo 4).
- Formulario de contacto: campos 277px móvil, fila inicial 2 col en tablet (320px cada una).

### Login `/acceso`
- Cabe entera en la pantalla de móvil (scrollHeight 812 en 375×812); tarjeta 343px con margen 16px.
- El `hero__brasa` (decoración de fondo) se extiende hasta right:413 en 375px, pero el contenedor `.auth` la recorta (overflow hidden) y no genera scrollbar. Cosmético, no es un bug.
- Checkbox "Mantener la sesión abierta" de 13×13px: pequeño pero funcional.

### Registro `/registro`
- 6 campos apilados a 293px/56px en móvil, 446px en tablet. Sin desborde.
- Enlaces alternativos presentes ("Entrar" / "Regístrate").

---

## Recomendaciones priorizadas

1. **(Alta)** Añadir "Acceder" al menú hamburguesa (Hallazgo 1).
2. **(Media)** Compactar el nav bajo 360px para que la hamburguesa no quede cortada (Hallazgo 2).
3. **(Media)** Subir el tamaño del H1 del hero en móvil (Hallazgo 3).
4. **(Baja)** Invalidar o bajar el TTL de `landing.cifras` y adelantar el disparo de los contadores (Hallazgo 4).
5. **(Baja)** Ajustar el botón "Enviar mensaje" a 320px y el padding de los enlaces del pie (Hallazgos 5 y 6).

---

## Notas de método

- El daemon de navegación no persiste estado entre llamadas; cada prueba se ejecutó con una sesión fresca, por lo que los flujos interactivos se verificaron encadenando navegación → acción → medición en una misma sesión.
- Las mediciones de desborde usan `document.documentElement.scrollWidth` (overflow real de página) y `getBoundingClientRect` (geometría de elementos). No se realizó revisión visual (entorno sin soporte de imágenes), por lo que hallazgos puramente estéticos sin efecto medible no figuran.
