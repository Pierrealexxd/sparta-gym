# Auditoría responsive — Panel de administración (`/admin`)

**Proyecto:** Sparta Gym (Laravel 12 · Blade · CSS propio · Alpine)
**Fecha:** 2026-08-11
**Alcance:** todas las vistas del panel de administración en móvil (375×812 y 375×568) y tablet (768×1024).
**Sesión usada:** `admin@spartagym.pe` / `sparta2026` (rol admin, ve todas las secciones).
**Método:** navegador headless (Chromium) con mediciones reales de layout: ancho de tablas vs. contenedor, tamaños de botones/iconos, targets táctiles, truncamientos y flujos interactivos (sidebar móvil, modales, notificaciones).

---

## Resumen ejecutivo

El panel **no desborda la página** en ningún viewport (no hay scrollbar horizontal de documento) y el sidebar móvil, los modales, el chat, los formularios y el dashboard están **bien resueltos** en móvil y tablet.

El problema real está en las **tablas**: cuando una tabla desborda su contenedor (`.tabla-envoltorio`, que sí tiene `overflow-x:auto`), la **columna de acciones se estrecha a 44px y los botones de editar/eliminar se encogen a ~2px**, dejando los iconos **invisibles**. En móvil afecta a Clientes, Inventario, Entrenadores y Usuarios; en tablet solo a Usuarios.

| # | Prioridad | Página | Problema |
|---|-----------|--------|----------|
| 1 | **Alta** | Tablas del panel (todas) | Iconos de acción (lápiz/papelera) colapsados a 2px en móvil |
| 2 | **Alta** | `/admin/usuarios` | Tabla de 938px sigue rota en tablet (botones a 12px) |
| 3 | Media | `/admin/clientes` y otras | Tabla desborda el contenedor y obliga a scroll horizontal largo |
| 4 | Baja | Todas | "Administrador Sparta" truncado con `…` en el pie del sidebar |
| 5 | Baja | Todas | Enlace del avatar "Pierre Calderón" de 17px de alto (< 24px táctil) |

---

## Hallazgo 1 (Alta) — Los iconos de acción de las tablas colapsan a 2px en móvil

**Síntoma.** En móvil (375px), en las filas de las tablas de Clientes, Inventario y Entrenadores, los botones de editar/eliminar miden **2px de ancho** y su icono **0px**: se ven como una rendija invisible. En Usuarios miden 12px con icono de 10px (a medio tamaño).

**Evidencia (medida con la tabla desbordando el contenedor, viewport 375):**

| Página | Ancho tabla | Wrapper | Última columna | Botón acción | Icono |
|--------|------------:|--------:|---------------:|-------------:|------:|
| Clientes | 560px | 333px | 44px | **2px** | **0px** |
| Inventario | 513px | 333px | 44px | **2px** | **0px** |
| Entrenadores | 676px | 333px | 44px | **2px** | **0px** |
| Usuarios | 938px | 333px | 44px | 12px | 10px |

En desktop (1280px) el mismo botón mide 20px con icono de 18px → el colapso **solo ocurre cuando la tabla desborda** su contenedor.

**Causa raíz.** Tres cosas que se combinan:
1. La columna de acciones recibe el padding estándar de celda (`.9rem var(--e-4)` = ~16px por lado), así que una columna de 44px deja solo ~12px de área de contenido.
2. Los botones son `.btn` (flex) dentro de un contenedor `display:flex; gap:var(--e-2)`.
3. `.btn` tiene `overflow:hidden`, que en flexbox **anula el `min-width:auto`** → el botón puede encogerse a 0. Por eso el *min-content* de la columna resulta de ~12px y el navegador la reparte a 44px.

**Dónde corregir.**

CSS base de tablas: `resources/css/panel.css` (reglas en las líneas ~421-443).

Markup de acciones (el patrón se repite):
- `resources/views/admin/clientes/index.blade.php` (~línea 102)
- `resources/views/admin/inventario/index.blade.php` (líneas 39-42)
- `resources/views/admin/entrenadores/index.blade.php` (líneas 31-34)
- `resources/views/admin/usuarios/index.blade.php` (líneas 32-35)
- `resources/views/admin/ventas/index.blade.php` (mismo patrón)

**Fix mínimo (recomendado).** Impedir que los botones de acción se encojan:

```css
/* resources/css/panel.css */
.tabla td .btn--desnudo { flex: none; }
```

Con esto, el *min-content* de la columna pasa a ser el tamaño del icono (~18px + gap), la columna de acciones crece a su ancho natural y los iconos se ven **siempre**, incluso cuando la tabla desborda y hay que hacer scroll horizontal.

**Fix complementario (mejor accesibilidad táctil).** Los iconos solos (16-20px) son un target pequeño. Añadir padding y tamaño mínimo:

```css
.tabla td .btn--desnudo {
    flex: none;
    min-width: 2.5rem;
    min-height: 2.5rem;
    place-items: center;
    display: inline-flex;
}
```

> Nota: la barra de acciones se repite en las 5 vistas citadas. Si se prefiere, se puede añadir una clase común (p. ej. `tabla__acciones`) en los `<td>` y estilarla una sola vez.

---

## Hallazgo 2 (Alta) — La tabla de Usuarios sigue rota en tablet

**Síntoma.** En tablet (768px) la tabla de `/admin/usuarios` mide **957px** dentro de un wrapper de 720px → sigue desbordando y los botones de acción quedan a 12px con icono de 10px (mitad del tamaño). Las demás tablas caben en tablet y se ven bien.

**Dónde corregir.** La misma regla del Hallazgo 1 arregla esto (no hay nada específico de usuarios: es la tabla más ancha del panel, 6 columnas con Correo y Sede largos).

**Recomendación opcional.** Para reducir el ancho de la tabla de usuarios se puede ocultar la columna "Sede" en móvil (los usuarios del mismo gimnasio la verían repetida) o permitir wrap en "Correo".

---

## Hallazgo 3 (Media) — Las tablas desbordan el contenedor y exigen scroll horizontal largo

**Síntoma.** Las tablas son más anchas que la pantalla móvil y solo son utilizables con scroll horizontal:

| Página | Ancho en móvil | Visible sin scroll | Scroll para llegar a acciones |
|--------|---------------:|-------------------:|------------------------------:|
| Clientes | 560px | ~333px | ~225px |
| Inventario | 513px | ~333px | ~180px |
| Entrenadores | 676px | ~333px | ~345px |
| Usuarios | 938px | ~333px | ~600px |
| Ventas | 591px | ~333px | ~260px |

El wrapper `.tabla-envoltorio` **sí** tiene `overflow-x:auto` (los datos no se pierden, solo se desplazan), pero en móvil no hay barra de scroll visible y el usuario no sabe que puede deslizar.

**Dónde corregir.** `resources/css/panel.css` (`.tabla-envoltorio`, línea 421).

**Opciones (de menor a mayor esfuerzo):**
1. **Mínimo:** reducir columnas ocultando las menos críticas en móvil, p. ej. `@media (max-width: 640px) { .tabla th:nth-child(3), .tabla td:nth-child(3) { display: none; } }` (en Clientes, "Código"; en Ventas, "Método" o "N°").
2. **Medio:** permitir que una columna haga wrap en vez de `white-space:nowrap` (ya existe el patrón `.tabla td.tabla__nota`, línea 448).
3. **Deseable a futuro:** tarjetas por fila en móvil (cada registro como tarjeta apilada), como se hace en la mayoría de paneles SaaS.

---

## Hallazgo 4 (Baja) — "Administrador Sparta" truncado en el sidebar

**Síntoma.** En el pie del sidebar, el nombre del usuario se corta: contenedor de **118px** vs contenido de **143px** (`clientW 118 / scrollW 143`). Se muestra "Administrador…".

**Dónde corregir.**
- Markup: `resources/views/layouts/panel.blade.php` (bloque `.panel__usuario-datos`, línea 78-81)
- CSS: `resources/css/panel.css` (`.panel__usuario-datos`, línea 153: `flex: 1; min-width: 0; overflow: hidden`)

**Fix.** Permitir wrap del nombre (o quitar el `nowrap` implícito):

```css
.panel__usuario-datos b { white-space: normal; }
```

---

## Hallazgo 5 (Baja) — Target táctil del avatar en la cabecera

**Síntoma.** El enlace del nombre del usuario en la cabecera ("Pierre Calderón") mide **104×17px**; los 17px de alto están por debajo del mínimo de 24px recomendado para touch.

**Dónde corregir.** El bloque de avatar en la cabecera del panel (en `resources/views/layouts/panel.blade.php` o en un partial de cabecera). Añadir altura mínima:

```css
.panel__cabecera-acciones a { min-height: 24px; display: inline-flex; align-items: center; }
```

---

## Comprobaciones que pasan correctamente (no tocar)

| Vista | Resultado |
|---|---|
| Dashboard KPIs móvil | 1 columna, sin overflow |
| Dashboard KPIs tablet | 3 columnas (230px c/u) |
| Dashboard gráficos | 1 columna en móvil y tablet |
| Sidebar móvil (375×568) | Abre, bloquea scroll (`sin-scroll`), nav scrollea interno, usuario y "Cerrar sesión" visibles |
| Sidebar ≥1024 | Inline, 260px, botón hamburguesa oculto |
| Sidebar 768 (tablet) | Off-canvas con hamburguesa (correcto) |
| Modal "Nuevo cliente" | 327px, 15 campos sin desborde, scroll vertical |
| Wizard "Nueva matrícula" | 327px, 19 campos sin desborde |
| Mensajes / chat móvil | 1 columna, sin overflow |
| Mi perfil móvil | Sin overflow |
| Calendario de asistencia móvil | Sin overflow |
| Notificaciones (campana) móvil | Panel 329px, cabe en 375 |
| Formularios (toolbar clientes) | Buscador y select apilados, sin desborde |

---

## Notas de método

- El daemon del navegador no persiste cookies entre sesiones; cada auditoría encadena login → navegación → medición en una sola invocación (`chain`).
- Anchos medidos con `getBoundingClientRect()` y `scrollWidth` vs `clientWidth` del wrapper.
- El colapso de botones se comprobó también en desktop (1280px) para descartar que fuera un fallo general de estilos: en desktop los iconos miden 18-20px, así que el bug es exclusivo de tablas desbordadas.
