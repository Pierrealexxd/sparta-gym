# Plan de paginación del panel de entrenador

Objetivo: que todo listado largo del panel de entrenador (`/entrenador`)
muestre **10 filas por página** con paginación, tanto en desktop como en
móvil. Mismo ejercicio que `PLAN_PAGINACION_PANEL_ADMIN.md`.

## Hallazgo principal

Solo dos de los cinco listados index paganinan (`inscripciones` y `rutinas`).
El hueco real está en **Ventas** (las dos pestañas usan `->get()` sin
paginación), que además es la versión reducida de `admin/ventas/index.blade.php`
— que sí pagina. "Mi marcación" lista un mes entero con `->get()`, pero el
calendario comparte esa misma colección.

## Inventario de cuadros del panel de entrenador

Convención: cada fila = un cuadro. Columna **Estado**: `OK` (ya pagina),
`LIMITE` (capado, intencional) o `GAP` (sin paginar).

| Vista | Cuadro | Fuente (controlador) | Estado |
|---|---|---|---|
| `entrenador/inscripciones/index.blade.php` (:29) | Tabla inscripciones | `Entrenador\InscripcionController::index:44` → `paginate(10)` | OK |
| `entrenador/rutinas/index.blade.php` (:10) | Tabla rutinas | `Entrenador\RoutineController::index:31` → `paginate(10)` | OK |
| `entrenador/ventas/index.blade.php` (:56) | Tabla ventas · pestaña Productos | `Entrenador\VentaController::index:70` → `get()` | **GAP** |
| `entrenador/ventas/index.blade.php` (:78) | Tabla inscripciones · pestaña Registros | `Entrenador\VentaController::index:56` → `get()` | **GAP** |
| `entrenador/asistencia/mi-marcacion.blade.php` (:55) | Tabla marcaciones (vista lista) | `Entrenador\AttendanceController::miMarcacion:100` → `get()` | GAP (acotada por mes+turno) |
| `entrenador/rutinas/show.blade.php` (:30) | Tabla ejercicios por día | `Entrenador\RoutineController::show` (eager load `days.exercises`) | No pagina (acotada por diseño de rutina) |
| `entrenador/clientes/show.blade.php` (:57, :82) | Objetivos y Rutinas activas (listas) | `Entrenador\MemberController::show` → `goals` activos, `routines` activas | No pagina (acotadas: solo activas) |
| `entrenador/clientes/show.blade.php` (:40) | Comidas de hoy (grid) | `MemberController::show` → `mealLogs` del día | No aplica (4 comidas fijas) |
| `entrenador/asistencia/calendario.blade.php` (:18) | Calendario por día | `AttendanceController::calendario:61` → `get()` agrupado por día | No aplica (calendario) |
| `entrenador/asistencia/mi-marcacion.blade.php` (:106) | Calendario por día (vista calendario) | `AttendanceController::miMarcacion` → `$porDia` | No aplica (calendario) |

KPIs (no son tablas): inscripciones (2 tarjetas), ventas (por pestaña),
mi-marcación (2 tarjetas).

Modales (no son tablas): wizard de inscripción (`inscripciones/index.blade.php:60`),
modal de venta (`ventas/index.blade.php:98`), modal de registro de asistencia
(`asistencia/calendario.blade.php:71`), escaneo QR (`_escaneo-qr.blade.php`).

## El plan

### Fase 1 — Ventas (`GAP` principal)

Archivo: `app/Http/Controllers/Entrenador/VentaController.php`

- Cambiar ambos `->get()` por `->paginate(10)->onEachSide(1)->withQueryString()`
  (mismo patrón que `Admin\SaleController::index:46`).
- **Cuidado con los KPIs:** hoy se calculan sobre la misma colección
  (`$inscripciones->count()` :62 y `$ventas->count()/sum('total')` :73-74).
  Sobre un paginador, `count()` y `sum()` solo miran la página actual. Hacen
  falta agregados propios, como hace el Admin:
  `Sale::completadas()->where('sold_by', $user->id)->whereBetween(...)->sum('total')`
  y su `count()`. Mantener el `rango` ya calculado (:35-38).
- Vista `entrenador/ventas/index.blade.php`: añadir
  `<div class="paginacion">{{ $ventas->links() }}</div>` tras la tabla de
  Productos (:74) y `<div class="paginacion">{{ $inscripciones->links() }}</div>`
  tras la de Registros (:96).
- `withQueryString()` preserva `?tipo=` y el rango de fechas `desde`/`hasta`.

### Fase 2 — Mi marcación, vista lista (`GAP` acotado)

`AttendanceController::miMarcacion:100` carga el mes completo con `->get()`.
Un mes con doble turno puede llegar a ~60-90 filas.

- El **calendario y los KPIs derivan de la misma colección** (`$marcaciones`,
  `$porDia`), así que no se puede paginar la colección sin romperlos.
- Opción recomendada (bajo impacto): **dejarlo como está**. El filtro de
  mes + turno ya acota; el calendario es la vista principal y la tabla la
  secundaria. Se revisa de nuevo si un mes excede ~100 marcaciones.
- Alternativa si se decide paginar: una segunda consulta paginada solo para
  la vista lista (`$marcacionesPag`), conservando `$marcaciones` completo
  para calendario/KPIs.

### Fase 3 — Móvil (ya cubierto por el sistema actual)

- Las tablas usan `.tabla--tarjetas` (`resources/css/panel.css:497-559`):
  en móvil las filas se vuelven tarjetas apiladas con `data-etiqueta`.
- Paginación propia `resources/views/vendor/pagination/panel.blade.php`
  (`.paginacion__info` + `.paginacion__nav`, `flex-wrap`, media query
  compacta en `panel.css:922-940`). Sin Tailwind.
- Solo verificar visualmente tras Fase 1 (`npm run build` + navegar en móvil).

## Archivos implicados

- `app/Http/Controllers/Entrenador/VentaController.php`
- `resources/views/entrenador/ventas/index.blade.php`
- `app/Http/Controllers/Entrenador/AttendanceController.php` (solo si se
  hace Fase 2)
- `resources/views/entrenador/asistencia/mi-marcacion.blade.php` (ídem)
- Referencia del patrón: `app/Http/Controllers/Admin/SaleController.php:43-63`,
  `resources/views/admin/ventas/index.blade.php`
- Referencia responsive: `resources/views/vendor/pagination/panel.blade.php`,
  `resources/css/panel.css`

## Fuera de alcance

- `entrenador/clientes/show.blade.php` (listas acotadas a activos/hoy).
- `entrenador/rutinas/show.blade.php` (ejercicios por día, volumen ínfimo).
- Calendarios de asistencia (vista por mes, no por página).
- Panel de cliente `/mi-cuenta` — se tratará aparte si se pide.
