# Estado de las mejoras del panel — Sparta Gym

Referencia única de qué se hizo, en qué archivos, y qué queda pendiente
para el equipo. Todo lo marcado ✅ ya está en el código y compilado
(`npm run build` + `php artisan view:cache` corridos sin error). Lo
marcado 🔲 es lo único que falta ejecutar.

No se tocó nada de `resources/views/landing/**` ni `landing.css` en
ninguna fase — la portada pública queda intacta, tal como se pidió.

---

## Fase 1 — Base visual del panel ✅

| Qué | Archivos |
|---|---|
| Tarjetas KPI con glow interactivo (reusa `.tarjeta--interactiva`, que ya existía en la landing) | `dashboard.blade.php`, `pagos/index.blade.php` |
| Contadores animados (`data-contador`, reusa el mecanismo GSAP del hero) | mismos dos archivos |
| Entrada suave de tablas (`data-revelar` en `.tabla-envoltorio`) | las 6 tablas del panel |
| Hover de fila con filo de color | `panel.css` |
| Toasts de sesión (reemplazan los avisos fijos que empujaban el layout) | `layouts/panel.blade.php`, `panel.css` |

## Fase 2 — Flujo de matrícula: separación de dinero y trazabilidad ✅

| Qué | Archivos |
|---|---|
| Migración y modelo `cash_closings` (cierre de caja diario) | `database/migrations/2026_08_07_184902_create_cash_closings_table.php`, `app/Models/CashClosing.php` |
| Scopes `mensualidades()` / `sueltos()` / `deTipo()` en `Payment` | `app/Models/Payment.php` |
| `CashClosingController::store` — compara efectivo esperado vs contado | `app/Http/Controllers/Admin/CashClosingController.php` |
| `ReporteController` — CSV e impresión (→ PDF vía navegador) de pagos y membresías, con filtro de fechas y tipo | `app/Http/Controllers/Admin/ReporteController.php` + vistas en `admin/reportes/` |
| Filtro por tipo, columna "Cobrado por", botón/modal "Cerrar caja", historial de cierres | `admin/pagos/index.blade.php` |
| Permisos nuevos `caja.cerrar` (sólo admin por defecto), uso de `reportes.ver` / `reportes.exportar` (ya existían) | `database/seeders/RolePermissionSeeder.php`, `routes/admin.php` |
| Enlace "Reportes" en el menú lateral | `layouts/partials/panel-nav.blade.php` |

## Fase 3 — Dashboard ✅

Cubierta dentro de la Fase 1: los contadores animados y las tarjetas
interactivas son exactamente lo que pedía esta fase. No quedó nada
adicional pendiente aquí — el gráfico de Chart.js ya trae su propio
tooltip al pasar el cursor, no se justificaba tocarlo más.

## Fase 4 — Detalle del socio ✅

| Qué | Estado |
|---|---|
| Layout de la ficha con pestañas (resumen / medidas / membresías / pagos / asistencia) | Ya existía en `admin/socios/show.blade.php` antes de este trabajo — no requirió cambios. |
| Estados vacíos ilustrados (icono + texto, en vez de una celda de texto suelto) | ✅ Nuevo componente `resources/views/components/estado-vacio.blade.php` + CSS en `panel.css`. Aplicado en las 6 tablas del panel, las 4 pestañas de la ficha del socio y las 2 tablas del dashboard. |

**No queda nada pendiente de la Fase 4.** Está completa y no requiere
acción del equipo.

---

## Wizard de matrícula ✅

Implementado por completo: junta "crear socio" + "elegir plan" +
"registrar pago" en un solo flujo guiado de 3 pasos, sin tocar ninguno de
los controladores existentes (`MemberController`, `MembershipController`,
`PaymentController` siguen funcionando exactamente igual como respaldo).

| Qué | Archivos |
|---|---|
| `generarCodigo()` movido de `MemberController` a `Member` (refactor puro, reusado por el wizard) | `app/Models/Member.php`, `app/Http/Controllers/Admin/MemberController.php` |
| `MatriculaController` (crear, buscar socio, guardar) | `app/Http/Controllers/Admin/MatriculaController.php` |
| Rutas `admin.matricula.*` con permiso `socios.crear` | `routes/admin.php` |
| Vista con los 3 pasos (Alpine) | `resources/views/admin/matricula/create.blade.php` |
| CSS del stepper (`.wizard__pasos`) | `panel.css` |
| Accesos: botón en Socios y enlace en el menú lateral | `admin/socios/index.blade.php`, `layouts/partials/panel-nav.blade.php` |
| `@stack('scripts')` en el layout, necesario para el JS del wizard | `layouts/panel.blade.php` |

La spec original con el detalle paso a paso queda en
[`docs/wizard-matricula.md`](./wizard-matricula.md) como referencia de
diseño, pero ya no hay nada pendiente de ejecutar ahí.

---

## Auditoría de distribución (roles y sidebar) ✅

Verificación pedida por el dueño de que "los módulos estén bien
distribuidos". Se encontraron y corrigieron 2 cosas:

1. **Permisos por rol no se comprobaban en 3 rutas** — recepción podía
   anular pagos, gestionar entrenadores y gestionar planes, aunque el
   seeder de permisos nunca le dio esos permisos. Se agregó
   `->middleware('permiso:...')` a esas 3 rutas en `routes/admin.php`
   (`pagos.anular`, `entrenadores.gestionar`, `planes.gestionar`), y se
   ocultan del menú lateral si el usuario no tiene el permiso. Probado por
   tinker: recepción ya no tiene esos 3 permisos, admin los conserva.
2. **Sidebar comprimible** — no existía. Se agregó: botón junto al logo
   (`layouts/panel.blade.php`), que comprime la barra lateral a sólo
   iconos (76px) en escritorio, guarda la preferencia en `localStorage`
   (persiste entre sesiones), y se expande temporalmente al pasar el
   cursor por encima. Sin cambios de markup en cada enlace — el texto se
   recorta/revela solo por el ancho del contenedor.

## Checklist general antes de dar por cerrado el trabajo

- [x] `npm run build` sin error
- [x] `php artisan view:clear && php artisan view:cache` sin error
- [x] Wizard de matrícula implementado
- [ ] Probado en navegador por el equipo (dashboard, pagos, cierre de
      caja, reportes CSV/impresión, estados vacíos, wizard de matrícula
      completo: socio nuevo y socio existente)
- [ ] `php artisan test` tiene una falla preexistente
      (`SQLSTATE: no such table: gyms` en SQLite en memoria) no relacionada
      con este trabajo — la base de pruebas no está migrada. Vale la pena
      que alguien del equipo revise la config de `phpunit.xml` /
      `.env.testing` en algún momento, fuera de este alcance.
