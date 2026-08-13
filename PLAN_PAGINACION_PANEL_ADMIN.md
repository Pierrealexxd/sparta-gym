# Plan de paginación del panel de administrador

Objetivo: que todo listado largo del panel muestre **10 filas por página** con
paginación, tanto en desktop como en móvil.

## Hallazgo principal

El inventario revela que **todas las vistas `index` ya paganinan** (10 o 12).
Los únicos cuadros sin paginación son tres: la cola de solicitudes de
corrección, y dos tablas dentro de la ficha de cliente. El detalle está abajo.

## Inventario de cuadros (tablas) del panel

Convención: cada fila = un cuadro. Columna **Estado**: `OK` (ya pagina),
`LIMITE` (capado con `take()`, intencional) o `GAP` (sin paginar).

| Vista | Variable | Fuente (controlador) | Estado |
|---|---|---|---|
| `admin/clientes/index.blade.php` (:166) | `$clientes` | `MemberController::index` → `paginate(10)` | OK |
| `admin/membresias/index.blade.php` (:58) | `$membresias` | `MembershipController::index` → `paginate(10)` | OK |
| `admin/ventas/index.blade.php` (:95, :125) | `$ventas` (2 pestañas) | `SaleController::index` → `paginate(10)->withQueryString()` | OK |
| `admin/usuarios/index.blade.php` (:79) | `$usuarios` | `UserController::index` → `paginate(10)` | OK |
| `admin/entrenadores/index.blade.php` (:75) | `$entrenadores` | `TrainerController::index` → `paginate(10)` | OK |
| `admin/planes/index.blade.php` (:76) | `$planes` | `PlanController::index` → `paginate(10)` | OK |
| `admin/sedes/index.blade.php` (:83) | `$sedes` | `GymController::index` → `paginate(10)` | OK |
| `admin/inventario/index.blade.php` (:88) | `$productos` | `ProductController::index` → `paginate(12)` | OK |
| `admin/contenido/ejercicios/index.blade.php` (:101) | `$ejercicios` | `ExerciseController::index` → `paginate(12)` | OK |
| `admin/contenido/recetas/index.blade.php` (:106) | `$recetas` | `RecipeController::index` → `paginate(12)` | OK |
| `admin/contenido/faqs/index.blade.php` (:100) | `$faqs` | `FaqController::index` → `paginate(10)` | OK |
| `admin/contenido/testimonios/index.blade.php` (:155) | `$testimonios` | `TestimonialController::index` → `paginate(10)` | OK |
| `admin/dashboard.blade.php` (:151) — "Vencen esta semana" | `$porVencer` | `DashboardController::index` → `take(8)` | LIMITE |
| `admin/dashboard.blade.php` (:173) — "Últimas ventas" | `$ultimasVentas` | `DashboardController::index` → `take(6)` | LIMITE |
| `admin/asistencia/solicitudes.blade.php` (:18) | `$solicitudes` | `AttendanceEditRequestController::index` → `get()` | **GAP** |
| `admin/clientes/show.blade.php` (:99) — pestaña Medidas | `$cliente->measurements` | `MemberController::show` → sin límite | **GAP** |
| `admin/clientes/show.blade.php` (:144) — pestaña Membresías | `$cliente->memberships` | `MemberController::show` → sin límite | GAP (bajo volumen) |
| `admin/clientes/show.blade.php` (:163) — pestaña Pagos | `$cliente->sales` | `MemberController::show` → `take(10)` | LIMITE |
| `admin/clientes/show.blade.php` (:181) — pestaña Asistencia | `$cliente->attendances` | `MemberController::show` → `take(10)` | LIMITE |

No aplica (no son tablas): `admin/asistencia/calendario.blade.php` (calendario
por día), KPIs y gráficos del dashboard, formularios y modales.

## El plan

### Fase 1 — Cola de solicitudes (`GAP` principal)

Archivo: `app/Http/Controllers/Admin/AttendanceEditRequestController.php:35`

- Cambiar `->get()` por `->paginate(10)->withQueryString()`.
- Vista `admin/asistencia/solicitudes.blade.php:104`: añadir
  `<div class="paginacion">{{ $solicitudes->links() }}</div>` tras la tabla.
- **Cuidado:** el subtítulo usa `$solicitudes->count()` (:5), que con un
  paginador devuelve solo las filas de la página actual. Cambiarlo a
  `$solicitudes->total()`.
- `withQueryString()` preserva la pestaña `?estado=historial`.

### Fase 2 — Ficha de cliente, pestaña Medidas (`GAP`)

`MemberController::show` (:95) carga `measurements` completas
(`orderBy('measured_at')`, sin límite). Un socio medido semanalmente acumula
cientos de filas en años.

Opción recomendada (sin rutas nuevas): en `show` cargar
`'medidasPag' => $member->measurements()->latest('measured_at')->simplePaginate(10)`
y en la vista iterar `$medidasPag` con
`$medidasPag->links('pagination::panel')->appends(['tab' => 'medidas'])`.

- La pestaña activa hoy vive solo en Alpine (`x-data="{ tab: 'resumen' }"`,
  `clientes/show.blade.php:24`). Para que la paginación no resetee a
  "Resumen", el estado inicial debe leer `request('tab', 'resumen')`.
- Membresías queda igual (volumen ínfimo); si crece, se aplica el mismo patrón.

### Fase 3 — Dashboard (`LIMITE`, intencional)

`take(8)` y `take(6)` son listas de "últimos", no páginas. Sin cambios.
Opcional: enlace "Ver todo" → `admin.clientes.index` y `admin.ventas.index`.

### Fase 4 — Móvil (ya cubierto por el sistema actual)

- Las tablas usan `.tabla--tarjetas` (`resources/css/panel.css:497-559`): en
  `max-width` las filas se vuelven tarjetas apiladas con `data-etiqueta`.
- La paginación propia `resources/views/vendor/pagination/panel.blade.php`
  renderiza `.paginacion__info` + `.paginacion__nav` con `flex-wrap` y media
  query compacta (`panel.css:922-940`). No usa Tailwind.
- Solo verificar visualmente tras Fase 1 y 2 (`npm run build` + navegar en
  móvil).

## Archivos implicados

- `app/Http/Controllers/Admin/AttendanceEditRequestController.php`
- `resources/views/admin/asistencia/solicitudes.blade.php`
- `app/Http/Controllers/Admin/MemberController.php`
- `resources/views/admin/clientes/show.blade.php`
- `resources/views/vendor/pagination/panel.blade.php` (sin cambios, referencia)
- `resources/css/panel.css` (sin cambios previstos, referencia)

## Fuera de alcance

- Vistas de entrenador/cliente (`/entrenador`, `/mi-cuenta`) — paneles aún en
  marcador de posición según AGENTS.md.
- `admin/dashboard.blade.php` (solo tocar si se aprueba "Ver todo").
