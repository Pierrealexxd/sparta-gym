# Plan de paginación del panel de cliente

Objetivo: que todo listado largo del panel de cliente (`/mi-cuenta`) muestre
**10 filas por página** con paginación, tanto en desktop como en móvil. Mismo
ejercicio que `PLAN_PAGINACION_PANEL_ADMIN.md` y
`PLAN_PAGINACION_PANEL_ENTRENADOR.md`.

## Hallazgo principal

El panel de cliente tiene **dos vistas** (`dashboard` y `progreso`) y hoy
**no existe ninguna paginación** en ninguna (`grep` de `links()` sin
resultados). La mayoría de listados están acotados a propósito ("recientes",
"activos", "de hoy"), pero hay **dos listados sin límite**: el historial de
medidas y los platos habituales, ambos en `progreso.blade.php`.

## Inventario de cuadros del panel de cliente

Convención: cada fila = un cuadro. Columna **Estado**: `OK` (ya pagina),
`LIMITE` (capado, intencional) o `GAP` (sin paginar).

| Vista | Cuadro | Fuente (controlador) | Estado |
|---|---|---|---|
| `cliente/dashboard.blade.php` (:82) | Tabla "Últimas ventas" | `Cliente\DashboardController::__invoke:18` → `sales` `take(8)` | LIMITE |
| `cliente/dashboard.blade.php` (:97) | Tabla "Mi asistencia reciente" | `Cliente\DashboardController::__invoke:19` → `attendances` `take(10)` | LIMITE |
| `cliente/dashboard.blade.php` (:62) | Tabla ejercicios por día (dentro de "Mi rutina") | `DashboardController::__invoke:17` → `routines` activas con `days.exercises` | No pagina (acotada por diseño de rutina) |
| `cliente/progreso.blade.php` (:365) | Tabla "Historial de medidas" | `Cliente\ProgressController::__invoke:22` → `measurements` completas | **GAP** |
| `cliente/progreso.blade.php` (:325) | Lista "Mis platos habituales" | `ProgressController::__invoke:26` → `savedMeals` latest, sin límite | GAP (volumen moderado) |
| `cliente/progreso.blade.php` (:105) | Lista "Mis metas" | `ProgressController::__invoke:23` → `goals` activos | No pagina (solo activas) |
| `cliente/progreso.blade.php` (:276) | Grid "Hoy comiste" (4 formularios) | `ProgressController::__invoke:24` → `mealLogs` del día | No aplica (4 comidas fijas) |
| `cliente/progreso.blade.php` (:188) | Tabla guía de porciones (modal) | estática en la vista | No aplica (4 filas fijas) |
| `cliente/dashboard.blade.php` (:33) / `progreso.blade.php` (:7) | Tarjetas KPI | KPIs del controlador | No aplica (no son tablas) |
| `cliente/dashboard.blade.php` (:45) | "Mis objetivos" (lista) | `DashboardController::__invoke:16` → `goals` activos | No pagina (solo activas) |

Gráficos (no son tablas): peso y % grasa en `progreso.blade.php` (:351-363),
peso en ficha.

## El plan

### Fase 1 — Historial de medidas (`GAP` principal)

Archivo: `app/Http/Controllers/Cliente/ProgressController.php`

- `ProgressController::__invoke` carga `measurements` completas
  (`orderBy('measured_at')`, sin límite). Un socio que se pesa a diario
  acumula ~365 filas al año.
- **Cuidado:** esa colección alimenta también los KPIs (`ultima`, `primera`,
  `hoy`), los gráficos (`graficoPeso`, `graficoGrasa`) y el cálculo de
  progreso de metas. No se puede paginar sin romperlos.
- Opción recomendada: **segunda consulta paginada solo para la tabla**:
  `'medidasPag' => $socio->measurements()->latest('measured_at')->simplePaginate(10)`
  y en la vista iterar `$medidasPag` en el historial (:369).
- Vista `cliente/progreso.blade.php`: añadir tras el historial (:382)
  `<div class="paginacion">{{ $medidasPag->links() }}</div>`.
- Como la página no tiene pestañas (es una sola vista), `simplePaginate` no
  necesita `appends()` — los enlaces solo cambian `?page=`.
- Los gráficos y KPIs siguen usando la colección `measurements` completa,
  sin tocar.

### Fase 2 — Mis platos habituales (`GAP` moderado)

`ProgressController::__invoke:26` carga `savedMeals` latest sin límite
(`cliente/progreso.blade.php:325-347`, lista de filas con botones "Usar hoy" /
papelera).

- Volumen típico bajo (un plato guardado por comida habitual), pero crece sin
  tope con el uso.
- Opción recomendada: **aplicar el mismo patrón** con `simplePaginate(10)` en
  una colección aparte (`'platosPag'`) y paginar la lista, o **dejarlo** si se
  prefiere un primer alcance mínimo. Decidir en la ejecución.

### Fase 3 — Dashboard (`LIMITE`, intencional)

`sales take(8)` y `attendances take(10)` son "recientes" a propósito, igual
que en el dashboard de admin. Sin cambios.

### Fase 4 — Móvil (ya cubierto por el sistema actual)

- Las tablas usan `.tabla--tarjetas` (`resources/css/panel.css:497-559`):
  en móvil las filas se vuelven tarjetas apiladas con `data-etiqueta`.
- Paginación propia `resources/views/vendor/pagination/panel.blade.php`
  (`.paginacion__info` + `.paginacion__nav`, `flex-wrap`, media query
  compacta en `panel.css:922-940`). Sin Tailwind.
- Solo verificar visualmente tras Fase 1 (`npm run build` + navegar en móvil).

## Archivos implicados

- `app/Http/Controllers/Cliente/ProgressController.php`
- `resources/views/cliente/progreso.blade.php`
- Referencia responsive: `resources/views/vendor/pagination/panel.blade.php`,
  `resources/css/panel.css`

## Fuera de alcance

- `cliente/dashboard.blade.php` (ventas y asistencia ya capadas; rutina y
  objetivos acotadas).
- Formularios y guías estáticas de `progreso.blade.php`.
- Mensajería (`MensajeController`) — se tratará aparte si se pide.
