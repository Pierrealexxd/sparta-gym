# Responsive — Auditoría móvil del panel

Fecha: 7 ago 2026 · Herramienta: gstack browse (headless) · Vistas probadas: 375, 768 y 1280 px.

## Alcance

Paneles de **administrador**, **entrenador** y **cliente** sobre la app corriendo en local
(`php artisan serve` + `npm run build`). Se midió el desborde horizontal
(`document.body.scrollWidth - innerWidth`), el recorte de botones del membrete y el
ajuste de modales.

## Resultado

Tras los fixes, **ninguna página supera `ov:0` a 375 px**, en tablet (768 px) no hay
regresiones y los modales caben y desplazan internamente.

## Bugs encontrados y corregidos

Todos en `resources/css/panel.css`.

### 1. `/panel/socios` — desborde de 303 px

- **Causa:** `.tabla-bulk` (div plano sin `overflow-x`) es hijo directo del grid de
  `.panel__contenido`; su `min-width:auto` arrastra el min-content de la tabla
  (649 px) y expande el track implícito del grid.
- **Fix:** `.panel__contenido { grid-template-columns: minmax(0, 1fr); }`

### 2. `/panel/socios/{id}` (ficha) — desborde de 130 px

- **Causa:** el track `1fr` de `.ficha` crece por el ancho intrínseco del canvas del
  QR (~400 px) dentro de la tarjeta.
- **Fix:** `grid-template-columns: 300px minmax(0, 1fr)` en escritorio y
  `minmax(0, 1fr)` en móvil (≤900 px).

### 3. `/panel/actividad` — desborde de 122 px (calendario)

- **Causa:** el contador `"N movimientos"` en mayúsculas tiene un min-content de
  ~85 px que fuerza el track de `repeat(7, 1fr)`.
- **Fix:** `repeat(7, minmax(0, 1fr))` y `overflow-wrap: anywhere` en
  `.calendario__contador` dentro del media query ≤720 px.

### 4. Botones del membrete recortados — `/panel/pagos`, `/panel/socios`, `/panel/planilla`

- **Causa:** las secciones de acciones envuelven sus botones en
  `<div style="display:flex;gap:var(--e-3)">` sin `flex-wrap`, así que el div nunca
  envuelve y sus botones desbordan el membrete (recortados por `overflow:hidden`).
  En pagos, **"Registrar pago" quedaba fuera de pantalla**.
- **Fix:** `.membrete__acciones > div { flex-wrap: wrap; min-width: 0; }`

## Verificación a 375 px

- **18 páginas del panel admin** (dashboard, socios, ficha, pagos, membresías,
  inventario, ventas, asistencia, planilla, reportes, usuarios, entrenadores, planes,
  sedes, faqs, actividad, matrícula, alta de socio): todas `ov:0`.
- **Entrenador:** `/entrenador`, `/entrenador/rutinas`, `/entrenador/inscripciones/create`.
- **Cliente:** `/mi-cuenta`.
- **Modales** (registrar pago, cerrar caja, registrar venta, confirmar): 327 px de
  ancho, centrados, con scroll interno cuando el contenido es alto.
- **Toggle de tema:** claro ↔ oscuro funciona.
- **Tablet 768 px:** sin desbordes en las páginas arregladas.

## Notas

- Las pestañas de la ficha de socio se desplazan con scroll horizontal
  (`overflow-x: auto`), patrón intencional del sistema.
- El botón "Eliminar" de la ficha es sólo icono (sin etiqueta visible), pendiente
  de revisión menor de UX.

---

# Páginas públicas — móvil y tablet

Fecha: 8 ago 2026 · Vistas: 320, 375, 768, 1024 y 1440 px.

## Alcance

Landing `/` (hero, historia, beneficios, biblioteca de ejercicios, guías, planes,
testimonios, preguntas y contacto), `/acceso` y `/registro`.

## Resultado

Después del fix, **ninguna página pública supera `ov:0` en todo el rango
320 → 1440 px**. La landing ya era responsive por diseño (media queries ≤720 y
≤560 en `landing.css`); lo único roto eran las páginas de acceso.

## Bugs encontrados y corregidos

### `/acceso` y `/registro` — desborde de 38–77 px

- **Causa:** la brasa decorativa `.hero__brasa` (position:absolute, centrada al
  50%, con blur y sangría) sobresale del viewport; en login/registro la sección
  `.auth` no la recortaba.
- **Fix:** `.auth { overflow: hidden; }` en `resources/css/auth.css`.

## Ajustes de pulido

- `resources/css/landing.css`: `.filtro` pasa de `padding: .5rem` a `.55rem` para
  ganar altura táctil en los chips de la biblioteca (36 → 38 px).

## Verificación

| Vista | Landing | Acceso |
|-------|---------|--------|
| 320×568 | `ov:0` · menú móvil OK · hero encaja (moto 181 px) | `ov:0` · vidrio 288 px |
| 375×812 | `ov:0` · hero 812 px, CTA sin solape con cifras | `ov:0` · inputs 56 px |
| 768×900 | `ov:0` · cifras en banda, sin desbordes | `ov:0` · vidrio 480 px |
| 1024×768 | `ov:0` · nav con enlaces completos | `ov:0` |
| 1440×900 | `ov:0` | `ov:0` |

- **Menú móvil:** botón hamburguesa → `nav__enlaces.is-abierto`; en 320–768 el
  nav muestra marca + "Inscribirme" + hamburguesa y todo cabe.
- **Hero en pantallas bajas** (≤640 px de alto): se oculta la entradilla y se
  reduce la moto; el bloque completo cabe en 568 px sin solaparse.
- **Registro:** la fila de dos columnas pasa a una sola (≤560 px, `1fr`).
- **Inputs:** `.campo__control` mide 56–58 px de alto (padding `.9rem`), bien
  por encima del mínimo táctil de 44 px.
- **Hallazgo menor:** `.nav__marca` tiene área táctil de 29 px de alto (se deja
  tal cual, es enlace de marca no crítico).
