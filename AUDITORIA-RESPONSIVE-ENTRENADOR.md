# Auditoría responsive — Panel del entrenador

> Auditoría estática de código (sin navegador). Fecha: 2026-08-12.
> Ámbito: **solo el panel del entrenador** (`/entrenador`). El objetivo es entregar
> hallazgos localizados (archivo:línea) para que otro agente los mitigue.

---

## 1. Alcance

Rutas del panel del entrenador (`routes/entrenador.php`, todas bajo prefijo
`/entrenador`, middleware `rol:entrenador`):

| Ruta (URL) | Nombre | Vista |
|---|---|---|
| `GET /entrenador` | `entrenador.dashboard` | `resources/views/entrenador/dashboard.blade.php` |
| `GET /entrenador/clientes/{member}` | `entrenador.clientes.show` | `resources/views/entrenador/clientes/show.blade.php` |
| `POST /entrenador/clientes/{member}/medidas` | `entrenador.clientes.medidas.store` | (form en `clientes/show`) |
| `GET /entrenador/entrenamientos` | `entrenador.rutinas.*` (resource) | `rutinas/index`, `rutinas/show`, `rutinas/form` |
| `GET/POST /entrenador/rutinas` | `entrenador.inscripciones.*` | `resources/views/entrenador/inscripciones/index.blade.php` |
| `GET /entrenador/asistencia` | `entrenador.asistencia.calendario` | `resources/views/entrenador/asistencia/calendario.blade.php` |
| `GET /entrenador/asistencia/mi-marcacion` | `entrenador.asistencia.mi-marcacion` | `resources/views/entrenador/asistencia/mi-marcacion.blade.php` |
| `GET /entrenador/ventas` | `entrenador.ventas.index` | `resources/views/entrenador/ventas/index.blade.php` |

Vistas compartidas del panel (afectan al entrenador):

- `resources/views/layouts/panel.blade.php` (esqueleto: sidebar, cabecera, membrete, notificaciones)
- `resources/views/layouts/partials/panel-nav.blade.php` (menú lateral, bloque `esEntrenador`)
- `resources/views/entrenador/asistencia/_pestanas.blade.php`
- `resources/views/entrenador/asistencia/_escaneo-qr.blade.php`
- Componentes: `x-calendario`, `x-buscador-cliente`, `x-alterna-vista`, `x-estado-vacio`, `x-modal-confirmar`, `x-discos`
- CSS: `resources/css/panel.css`, `resources/css/components.css`, `resources/css/base.css`
- JS: `resources/js/escaneo-qr.js`, `resources/js/ui.js`, `resources/js/animations.js`

Breakpoints existentes: `960px` (sidebar → cajón móvil), `900px` (columnas),
`720px` (calendario), `640px` (tablas → tarjetas), `520px` (acciones),
`480px` (fila-borrable, paginación, cajón notificaciones), `380px` (botones grandes).

---

## 2. Resumen de hallazgos

| # | Severidad | Hallazgo | Archivo(s) |
|---|---|---|---|
| 1 | **ALTO** | Botones de turno del modal QR desbordan en móvil | `_escaneo-qr.blade.php:40-47` |
| 2 | **ALTO** | `formulario-panel__fila` (min 220px) recorta campos en ≤ ~340px | `panel.css:655` + 8 usos en vistas |
| 3 | **MEDIO** | Desplegables de búsqueda absolutos quedan recortados dentro de modales con `overflow-y:auto` | `buscador-cliente.blade.php:26`, `panel.css:819` |
| 4 | **MEDIO** | Tablas de rutinas sin tratamiento móvil (inconsistencia con el resto) | `rutinas/index.blade.php:11`, `rutinas/show.blade.php:31` |
| 5 | **BAJO** | Cabecera: migas sin `max-width`, riesgo de empujar campana/tema en 320px | `panel.css:322-331` |
| 6 | **BAJO** | `:hover` de `.tarjeta` se queda "pegado" en táctil (sticky hover) | `components.css:206-210` |
| 7 | **BAJO** | Texto del contador de celdas de calendario se rompe por palabras en móvil | `panel.css:1052` |

---

## 3. Hallazgos detallados

### 1. [ALTO] Botones de turno del modal QR desbordan en móvil

**Ubicación:** `resources/views/entrenador/asistencia/_escaneo-qr.blade.php:40-47`

```blade
<div style="display:flex;gap:var(--e-2)">   {{-- sin flex-wrap --}}
    <button ... class="btn">Mañana</button>
    <button ... class="btn">Tarde</button>
    <button ... class="btn">Doble</button>
</div>
```

**Por qué falla:**
- `.btn` tiene `white-space:nowrap` + `padding: .95rem 1.9rem` (`components.css:69-91`): un botón mide ~115-125px y no puede encogerse.
- El modal es `.tarjeta.modal__caja` con `max-width:24rem` inline (`_escaneo-qr.blade.php:13`). En un viewport de 320px: 320 − 48 (padding de `.modal__fondo`) − 64 (padding `e-6` de `.modal__caja`) ≈ **208px de contenido** para 3 botones (~360px).
- El contenedor es flex sin `flex-wrap` → los botones se salen. Como `.modal__caja` tiene `overflow-y:auto` (`panel.css:819`), el `overflow-x` computado pasa a `auto`: el contenido se corta o aparece un scroll lateral invisible.

**Impacto:** en móvil estrecho (≤ ~360px) la elección de turno antes de escanear es inutilizable o queda recortada.

**Fix sugerido:** añadir `flex-wrap:wrap` + `flex:1` a los botones (o un breakpoint que los apile a ancho completo). El patrón correcto ya existe en `mi-marcacion.blade.php:13` (`flex-wrap:wrap`).

---

### 2. [ALTO] `formulario-panel__fila` recorta campos en pantallas ≤ ~340px

**Ubicación:** `resources/css/panel.css:655`

```css
.formulario-panel__fila { display:grid; gap:var(--e-4); grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
```

**Por qué falla:**
- `minmax(220px, 1fr)` fuerza un mínimo de 220px por columna. Si el contenedor mide menos, la columna se queda en 220px y **desborda hacia la derecha**.
- Como `.tarjeta` tiene `overflow:hidden` (`components.css:189`), el borde derecho del/los campo(s) se **recorta** (no hay scroll: se pierde el final del input).
- Cálculo en 320px (iPhone SE) dentro de una `.tarjeta` con padding `e-6` (32px): contenido ≈ 320 − 40 (padding `.panel__contenido`) − 64 (tarjeta) ≈ **216px < 220px** → recorte de ~4-6px. En 280px (Galaxy Fold plegado) el recorte supera los 40px.

**Vistas del entrenador afectadas (todas usan la clase):**
- `entrenador/asistencia/mi-marcacion.blade.php:23` — form "Marcar entrada" (turno + botones).
- `entrenador/inscripciones/index.blade.php:95, 115, 127, 146, 160` — wizard de inscripción (pasos 1, 2 y 3), dentro del modal.
- `entrenador/rutinas/show.blade.php:56, 80` — "Agregar ejercicio" y "Agregar día".
- `entrenador/rutinas/form.blade.php:21, 28` — formulario nueva/editar rutina.
- `entrenador/ventas/index.blade.php:116` — modal de venta (producto + cantidad).

**Fix sugerido:** `grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr))` (patrón estándar para grids fluidos sin desborde).

---

### 3. [MEDIO] Desplegables de búsqueda absolutos se recortan dentro de modales con scroll

**Ubicación:**
- Componente: `resources/views/components/buscador-cliente.blade.php:26-33` (`.buscador__lista` → `position:absolute; top:calc(100% + e-2); max-height:15rem`).
- Modal: `resources/css/panel.css:819` (`.modal__caja { max-height:88dvh; overflow-y:auto }`).

**Por qué falla:**
- Un hijo `position:absolute` no participa del flujo y no estira el modal; si la lista desplegada pasa el borde inferior visible del `modal__caja`, queda **recortada** y el usuario no puede hacer scroll hasta ella (el scroll del modal solo alcanza el contenido en flujo).
- En móvil (modales altos, teclado virtual abierto) el desplegable casi siempre se abre hacia abajo y aterriza fuera de la zona visible.

**Usos en el panel del entrenador:**
- `entrenador/inscripciones/index.blade.php:88` — `x-buscador-cliente` en el paso 1 del wizard.
- `entrenador/asistencia/calendario.blade.php:100` — modal "Registrar asistencia".
- `entrenador/ventas/index.blade.php:128` — buscador de producto en el modal de venta (mismo patrón inline).

**Fix sugerido (elegir uno):**
- Abrir el desplegable hacia arriba en móvil (`bottom:calc(100%+e-2)`), o
- renderizarlo en un `x-teleport` al `body` (el layout ya usa `x-teleport` para el cajón de notificaciones, `layouts/panel.blade.php:117`), o
- convertir el modal en scroll sobre todo el panel.

---

### 4. [MEDIO] Tablas del módulo de rutinas sin tratamiento móvil (inconsistencia)

**Ubicación:**
- `resources/views/entrenador/rutinas/index.blade.php:10-27` — `table.tabla` (Socio, Rutina, Objetivo, Estado, acción).
- `resources/views/entrenador/rutinas/show.blade.php:30-50` — `table.tabla` (Ejercicio, Prescripción, acción).

**Por qué falla:**
- El resto de tablas del panel usan `tabla--tarjetas` + `data-etiqueta` y a ≤640px se convierten en tarjetas (`panel.css:489-538`): inscripciones (`inscripciones/index.blade.php:17`), mi-marcación (`mi-marcacion.blade.php:56`), ventas (`ventas/index.blade.php:34,56`).
- Estas dos no: se quedan como tabla con `white-space:nowrap` (`panel.css:440`) y dependen del scroll horizontal del `.tabla-envoltorio` (`overflow-x:auto`). Funcionan, pero la experiencia en móvil es inferior y el diseño queda incoherente dentro del mismo panel.

**Fix sugerido:** añadir clase `tabla--tarjetas`, atributos `data-etiqueta` en cada `<td>` (con `data-etiqueta="nada"` en la columna de acciones) y, si conviene, `tabla__oculta-movil` en la columna menos crítica (Objetivo / Prescripción).

---

### 5. [BAJO] Migas de cabecera sin `max-width` en móvil

**Ubicación:** `resources/css/panel.css:322-331` (`.panel__migas-actual`) y `layouts/panel.blade.php:93-101`.

**Por qué es un riesgo:** `.panel__migas-actual` tiene `overflow:hidden + text-overflow:ellipsis` pero sin `max-width` ni `flex-basis`; en un flex `min-width:0` suele encoger bien, pero con títulos largos + campana + tema en un viewport de 320px la miga puede empujar los iconos o recortarse sin puntos suspensivos limpios.

**Fix sugerido (verificar en 320px y ajustar):** dar `flex:1 1 auto; min-width:0; max-width:100%` a `.panel__migas` y comprobar que la campana y el botón de tema no se desplacen.

---

### 6. [BAJO] `:hover` de tarjeta se "pega" en táctil

**Ubicación:** `resources/css/components.css:206-210` (`.tarjeta:hover { --elevar:-4px; ... }`).

**Por qué es un riesgo:** en pantallas táctiles, el primer toque deja la tarjeta en estado `:hover` (sticky hover). En el dashboard del entrenador (`entrenador/dashboard.blade.php:7-26`) las tarjetas KPI quedan elevadas/teñidas tras tocarlas. `interacciones.js` ya ignora `pointerType !== 'mouse'` para el brillo, pero el `:hover` CSS aplica igual.

**Fix sugerido:** envolver el `:hover` de `.tarjeta` en `@media (hover:hover) { ... }`.

---

### 7. [BAJO] Contador de celdas de calendario se rompe por palabras en móvil

**Ubicación:** `resources/css/panel.css:1049-1053` (breakpoint 720px: `.calendario__contador { font-size:.58rem; overflow-wrap:anywhere }`).

**Por qué es un riesgo:** a 720px o menos, el contador (p. ej. "12 asistencias") en celdas de ~36px de ancho puede partirse en medio de una palabra ("12 asistenci as"), porque `overflow-wrap:anywhere` permite cortar en cualquier punto.

**Fix sugerido:** `hyphens:auto` + `text-wrap:balance`, o acortar el texto a nivel de Blade (contador corto: "12") dentro del breakpoint.

---

## 4. Lo que ya está bien (no romper al mitigar)

- **Sidebar móvil:** `panel.css:185-196` lo convierte en cajón `position:fixed; inset:0 30% 0 0` con `transform:translateX(-100%)`, velo por detrás (`z 55 < z-nav 60`, `layouts/panel.blade.php:187-189`) y `sin-scroll` en `<html>` (`base.css:42`). Correcto.
- **Tablas → tarjetas a ≤640px** con `data-etiqueta` (`panel.css:489-538`): inscripciones, mi-marcación y ventas ya lo usan bien.
- **Fila de formulario repetible** (`fila-borrable`) tiene su breakpoint a 480px (`panel.css:666-670`).
- **Acciones con botón bloque** se apilan a ≤520px (`panel.css:674-677`).
- **Wizard de inscripción**: pasos con `overflow-x:auto` (`panel.css:890-897`), pensado para móvil.
- **Calendario mensual**: celdas reducen su `min-height` a 70px en móvil (`panel.css:1049-1053`).
- **KPIs**: `grid auto-fit minmax(210px,1fr)` colapsa bien a una columna (`panel.css:407`).
- **Columnas 2-1 / 1-1**: colapsan a una columna a ≤900px (`panel.css:861`).
- **Modal**: `width:min(34rem,100%)`, `max-height:88dvh`, `overflow-y:auto` (`panel.css:819`).
- **Cajón de notificaciones**: `width:min(26rem,100vw)` y 100vw a ≤480px (`panel.css:798-800`).
- **Buscador de cliente** desplegable: ancho completo del campo (`buscador-cliente.blade.php:26`, `left:0;right:0`).
- **Paginación** propia del panel sin Tailwind (`vendor/pagination/panel.blade.php`), compacta a ≤480px.
- **Escaneo QR**: la cámara se pide dentro del gesto de clic para móvil (`escaneo-qr.js:44-50`), `playsinline` + `muted` (`escaneo-qr.js:130-131`).

---

## 5. Checklist manual sugerida para después de mitigar

Viewports a probar: 320, 360, 375, 390, 414, 768, 960, 1024, 1440.

1. `/entrenador/asistencia/mi-marcacion` → abrir "Escanear QR": los 3 botones de turno deben caber o apilarse sin recorte; escanear con cámara real.
2. `/entrenador/asistencia` → "Registrar asistencia": escribir 2+ letras y comprobar que el desplegable no se corte en el borde del modal.
3. `/entrenador/rutinas` → "Nueva inscripción": paso 1, 2 y 3 sin recorte de campos; buscar cliente existente.
4. `/entrenador/entrenamientos` → listado y detalle: verificar tabla en móvil (decidir el tratamiento a darle).
5. `/entrenador/clientes/{id}` → formulario de medidas + gráfico de peso a una columna.
6. `/entrenador/ventas` → modal de venta con 2 filas de producto en 320px.
7. Dashboard `/entrenador`: KPIs a una columna; tocar una tarjeta y confirmar que no queda elevada.
8. Menú móvil: abrir/cerrar, velo, cierre al elegir destino, scroll bloqueado.
9. Paginación y filtros de fechas en `/entrenador/ventas`.

---

## 6. Prioridad sugerida de mitigación

1. Hallazgo #1 (botones QR) — bloquea una función core del entrenador en móvil.
2. Hallazgo #2 (grid 220px) — recorte visual en 6 pantallas; un solo cambio en CSS.
3. Hallazgo #3 (desplegables en modales) — UX rota en 3 pantallas.
4. Hallazgo #4 (tablas de rutinas) — coherencia y una línea por vista.
5. #5-#7 — pulido.
