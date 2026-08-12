# Plan de reestructuración — Sparta Gym

> **Fecha:** 2026-08-08 · **Modo:** plan → ejecución · **Agente destino:** agente ejecutor (lee este archivo completo antes de tocar nada).
>
> **Decisiones confirmadas por el dueño del proyecto (no negociables):**
> 1. **`payments` se archiva en BD.** Tras migrar sus datos a `sales`, la tabla queda guardada pero ningún código nuevo la lee ni escribe. **No droppear.**
> 2. **El registro web sigue creando el expediente `Member` de inmediato** (cliente sin membresía). Sin cambios en `RegisterController`.
> 3. **"Cliente" = expediente (ex-Socio).** El rol `cliente` (acceso a `/mi-cuenta`) se queda como está. La terminología "Socio" se reemplaza por "Cliente" en toda la presentación.
> 4. **El arqueo de caja pasa a ser una pestaña dentro de Ventas** (Ventas / Membresías y servicios / **Caja del día**), junto al selector de tipo de venta.

---

## A. Estado actual

- Laravel 12 · Blade · CSS propio · Alpine · GSAP · Vite · MySQL. Monorepo de un solo gimnasio pensado como SaaS multi-sede.
- **Aislamiento por sede:** `App\Models\Concerns\BelongsToGym` (scope global `gym_id` + auto-relleno al crear). `Gym` y `Exercise` no lo usan a propósito. Sede activa resuelta por `App\Support\GymContext` + middleware `sede.activa` (`EstablecerSedeActiva`).
- **RBAC propio:** 4 roles (`config/sparta.php`): admin (100), recepcion (60), entrenador (40), cliente (10). Permisos cacheados por rol (`Role::permisosCacheados`). El admin no se enumera: `User::tienePermiso()` le concede todo.
- **31 permisos en 7 grupos** (`database/seeders/RolePermissionSeeder.php`): Socios, Membresías, Pagos, Asistencia, Entrenamiento, Inventario, Sistema.
- **135 rutas** en 3 archivos de panel: `routes/admin.php`, `routes/entrenador.php`, `routes/cliente.php`, todos montados bajo `auth + sede.activa` en `routes/web.php`.
- **Dinero dividido (el problema raíz):** `payments` cobra membresías y pagos sueltos; `sales` cobra productos de mostrador. La caja (`cash_closings`) solo mira `payments`; el dashboard solo suma `payments`; las ventas de producto **no entran a caja ni al dashboard**.
- `SaleController::index` simula "Ventas" sumando **tres fuentes distintas** (payments de mensualidad + memberships de inscripción + sales de producto) con filtros y formas de fecha diferentes.
- Paneles: admin/recepción completo (dashboard, socios, matrícula, membresías, pagos y caja, asistencia, personal, actividad, ejercicios, entrenadores, planes, sedes, faqs, testimonios, instalaciones, inventario, ventas, usuarios, reportes, planilla). Entrenador operativo. Cliente operativo.

---

## B. Problemas

### B1 — CRÍTICO: rutinas del entrenador inaccesibles (403)
`routes/entrenador.php:27`: `Route::resource('rutinas', RoutineController::class)` **sin** `->parameters(['rutinas' => 'routine'])`. Laravel genera `{rutina}` pero los métodos piden `Routine $routine` → el binding implícito falla y `show/edit/update/destroy` devuelven **403 siempre**. (El resto de resources del proyecto sí usan `->parameters()`: ver `admin.php:110-132`.)
**Fix:** añadir `->parameters(['rutinas' => 'routine'])`. Es la tarea 1.

### B2 — Dinero dividido: la caja y el dashboard no ven todo lo que entra
`Payment` y `Sale` son dos "cuentas" paralelas del mismo dinero:
- `CashClosingController` cuadra la caja **solo con `payments`** — una venta de mostrador en efectivo no aparece en el arqueo.
- `DashboardController` (`ingresosHoy`, `ingresosMes`, gráficos, desglose por sede) **solo suma `payments`** — los ingresos reales se subestiman.
- La pantalla "Ventas" ya intenta juntar las tres fuentes a mano, lo que es la prueba de que el modelo correcto es uno solo.

### B3 — `sales.member_id` es un campo muerto
La venta de mostrador acepta `member_id` nullable en validación (`SaleController.php:87`) y lo guarda, pero **la vista de venta no ofrece elegir cliente**; además el correlativo `V-000001` se calcula con `Sale::count() + 1` (`SaleController.php:171-174` y duplicado en `VentaController.php:118`): frágil (colisiones si se anula, huecos tras borrado) y repetido.

### B4 — Lógica de matrícula triplicada
La misma transacción (socio + plan + pago + crear acceso) vive en tres sitios casi idénticos:
1. `Admin\MatriculaController::store`
2. `Entrenador\InscripcionController::store`
3. `Admin\MembershipController::store`
Cada uno con su propia creación de `Payment`. Arreglar un bug en uno no arregla los otros dos.

### B5 — La matrícula re-matricula a clientes existentes
`MatriculaController::store` e `InscripcionController::store` aceptan `socio_id` (socio existente) y le crean una **nueva membresía** marcando la anterior como `vencida` (`$anterior?->update(['status' => 'vencida'])`). Es decir: el trámite "matrícula" (reservado a nuevos) también sirve de renovación, mezclando dos flujos con reglas distintas. La renovación ya tiene su sitio: `MembershipController::store`.

### B6 — Vínculo User↔Member incompleto y sin garantías
- `UserController::update` no gestiona el vínculo `member.user_id`: cambiar el rol de una cuenta a `cliente` no la enlaza, ni libera al member anterior.
- La protección de "último admin" existe en `destroy` (`UserController.php:100`) pero **no en `update`**: se puede cambiar el rol del último admin.
- `members.user_id` **no tiene índice único** (`migrations/000103`): nada impide dos members apuntando a la misma cuenta.

### B7 — Tabla `gym_user` muerta
`2026_08_07_192149_create_gym_user_table.php` crea el puente user↔sede, pero **ningún código la llena ni la lee**; `User::gyms()` y `sedesDisponibles()` la consultan y siempre devuelven vacío. Es deuda técnica que invita a confusiones de multi-sede.

### B8 — Dashboard "todas las sedes" incompleto y selector sin validar
- En modo `todas`, `sedesElegidas()` (`DashboardController.php:70-79`) filtra **solo el desglose** (`desglosePorSede`), no los KPIs ni las series: marcar sedes deja números arriba que no cambian.
- `SedeActivaController::store` no valida: acepta cualquier `sede_id` y lo escribe en sesión sin comprobar que pertenezca al usuario (el middleware lo contiene al final, pero el controlador escribe basura).

### B9 — Sede invisible en la práctica para roles sin `sedes.ver-todas`
El selector de sede (`panel-nav.blade.php:4-25`) se oculta cuando `sedesDisponibles()` trae 1 sola sede. Como `gym_user` está vacío, entrenador/recepción/cliente **nunca** ven el selector aunque existan varias sedes. Solo el admin (vía `sedes.ver-todas`) ve todas.

### B10 — Menores
- `Member::scopeBuscar` no busca por email (solo nombre/apellido/código/documento/teléfono) — `Member.php:107-122`.
- El correlativo de venta es `count()+1` (ver B3), frágil.
- `DemoSeeder` genera `payments` (membresías) y **cero `sales`**: tras la migración a `sales`, los datos demo dejarían vacío todo lo económico.
- El `amount` del pago duplica `plan.price - discount` de la membresía (`MatriculaController:124-134`, `InscripcionController:120-132`, `MembershipController:80-91`): dos lugares donde el importe puede desincronizarse.
- El orden de `metodos_pago` en `config/sparta.php` (efectivo, yape, plin, transferencia, tarjeta, otro) no coincide con el orden de los `enum` en la BD (efectivo, transferencia, yape, plin, tarjeta, otro) en `sales`/`payments` — inconsistencia cosmética.
- `resources/views/welcome.blade.php` es el scaffold muerto de Laravel (apunta a `/dashboard`, `login`, `register` en inglés); no se usa (la raíz la sirve `LandingController`).
- Fallo preexistente de `php artisan test` (SQLite `no such table: gyms`) → **fuera de alcance**.

---

## C. Arquitectura objetivo

> **`Sale` es la ÚNICA fuente económica.** Todo lo que entra por dinero es una venta: producto, membresía, servicio o pago suelto. `Payment` queda archivado en BD y desaparece del código.

**`sales` pasa a tener:**
- `sale_type` (enum): `producto | membresia | servicio | otro`.
- `membership_id` (FK nullable → `memberships`): para ventas de tipo membresía.
- `concept` (string nullable): texto libre (pago suelto: "Cuota atrasada", "Ajuste"...).
- `reference` (string nullable): ya existe la necesidad en pagos; se traslada.
- `notes` (text nullable).
- `member_id` ahora **se captura en el formulario** (cliente opcional para producto, obligatorio si `sale_type` es membresía/servicio vía trámite de matrícula).
- Los `sale_items` solo existen para `sale_type = producto` (ya están congelados). Para membresías el importe vive en la propia `sale` y en `memberships` (congelado según AGENTS.md).

**Reglas que no se rompen (herederas del proyecto):**
- Importes congelados en `sales` y `memberships` (nada de punteros a catálogo).
- `paid_at`/`sold_at` ≠ `created_at`: la caja cuadra por **fecha real de cobro**, no de captura.
- `products.stock` es un saldo; la verdad vive en `stock_movements` (incluye al anular una venta: movimiento de **entrada**).
- `attendances.attended_on` es columna generada; no escribirla desde PHP.
- Todo modelo con datos de un gimnasio usa `BelongsToGym`; cruzar a propósito solo con `sinFiltroDeGimnasio()`.
- Las contraseñas con cast `hashed`; login con mensaje único para cuenta inexistente/incorrecta/desactivada.

---

## D. Cambios por módulo

### D1 — Clientes (ex-Socios)
Renombrado de **presentación**, no de esquema (`members` y `Member` se quedan):
- Rutas: `admin.socios.*` → `admin.clientes.*` (resource con `->parameters(['clientes' => 'member'])`); `entrenador.socios.*` → `entrenador.clientes.*`.
- Permisos: `socios.*` → `clientes.*` (seeder, tarea 10).
- Vistas: `resources/views/admin/socios/` → `resources/views/admin/clientes/`; textos "Socio"→"Cliente" en sidebar, ficha, listados, dashboard, wizard.
- Modelo/BD: sin cambios de nombre.
- Verificación: `grep -ri "socios"` en `routes/`, `resources/views/`, `database/seeders/`.

### D2 — Matrícula e inscripciones
- **`MatriculaController` = solo clientes nuevos** (sin `socio_id`): crea `Member` + `Membership` (sin `renewed_from`) + `Sale` (`sale_type='membresia'`) + opcional acceso. Redirige a `admin.clientes.show`.
- **`MembershipController::store` = renovación/venta de membresía a cliente existente**: encadena `renewed_from`, marca la anterior `vencida`, crea `Sale` (`sale_type='membresia'`).
- **`Entrenador\InscripcionController` = versión reducida que reusa la misma lógica** (solo nuevos; sin pantallas de configuración).
- Extraer `App\Services\MatriculaService` con métodos `nuevaMatricula(...)`, `renovarMembresia(...)` y `crearAcceso(...)`. Los tres controladores quedan delgados. **Un solo lugar para la regla de negocio.**

### D3 — Ventas
- `SaleController::store` recibe `sale_type`: `producto` (líneas + stock) | `membresia`/`servicio`/`otro` (concept + reference + member_id).
- El formulario de venta **permite elegir cliente** (autocompletar con `Member::buscar`).
- Correlativo robusto: numeración por gimnasio **dentro de la transacción** (p. ej. `MAX(number)` por prefijo + lock, o columna dedicada); misma función para `Admin\SaleController` y `Entrenador\VentaController` (extraer a `App\Support\NumeradorVentas` o método en el modelo).
- **`ventas.anular`**: `Sale::update(['status' => 'anulada'])`; si es `producto`, reponer stock con `StockMovement` de tipo `entrada` + `reason: "Anulación V-…"`.
- `PaymentController` se **elimina**: los pagos sueltos pasan a `Sale` con `sale_type='otro'` (concept obligatorio). La ruta `admin.pagos.*` desaparece.

### D4 — Caja del día (pestaña de Ventas)
- `CashClosingController` pasa a leer **`Sale`** (`status='completada'`, `sold_at` del día, `sale_type` filtrable) agrupado por `method`. El resto igual: único cierre por `gym_id + business_date`, `counted_cash` solo efectivo, `breakdown` JSON.
- La pantalla "Pagos y caja" se elimina; su contenido de arqueo (totales por método, `counted_cash`, historial de cierres) se mueve a la pestaña **"Caja del día"** dentro de Ventas (decision 4).

### D5 — Dashboard (admin)
- KPIs desde `Sale` (completadas): `ingresosHoy`, `ingresosMes`, series diarias/acumulado, distribución por método, desglose por sede.
- Eliminar `pagosPendientes` (no existe el concepto de pendiente en ventas); sustituir por `ventasHoy`.
- `ultimosPagos` → `ultimasVentas` (las 6 más recientes).
- **Modo `todas las sedes`:** los filtros `?sedes[]` se aplican **también a los KPIs y series**, no solo al desglose. Solución limpia: derivar un scope `Sale::deSedes($ids)` y aplicarlo a cada agregado cuando venga el filtro.

### D6 — Usuarios y cuentas
- `UserController::update`: al cambiar el rol a `cliente`, gestionar `member.user_id` (liberar el member anterior del usuario y enlazar el nuevo); al cambiar a otro rol, liberar el enlace.
- Protección "último admin" también en `update` (no solo en `destroy`).
- Índice único en `members.user_id` (migración; sanear duplicados antes).
- `RegisterController`: sin cambios (decisión 2).

### D7 — Entrenador
- Fix B1 (rutinas).
- `VentaController` reusa el correlativo robusto y la misma lógica de venta; pestañas actualizadas (Productos / Inscripciones / Rutinas) sin tocar su modelo de datos.
- Dashboard del entrenador ya suma `Sale` correctamente (`ventasMes`); mantener.
- `InscripcionController` delega en `MatriculaService`.

### D8 — Sede / multi-sede
- `SedeActivaController::store`: validar `sede_id` contra `sedesDisponibles()` (o `'todas'` con permiso); error 422 si no corresponde. Nunca escribir basura en sesión.
- Eliminar `gym_user` (tabla + relación `User::gyms()`).
- `User::sedesDisponibles()` queda: origen (`gym_id`) + `todas` cuando hay `sedes.ver-todas`. El selector aparece para cualquiera con más de una sede disponible (incluido entrenador). Documentar en `docs/multi-sede.md` que asignar un trabajador a varias sedes es trabajo futuro vía pivot real (fuera de alcance hoy).

### D9 — Reportes y panel del cliente
- `ReporteController`: `pagos.csv`/`pagos.imprimir` → `ventas.csv`/`ventas.imprimir` leyendo `Sale` (con `deTipo` por `sale_type`). `membresias.*` se queda.
- Panel cliente: `Member::payments()` → `Member::sales()`; vista "Últimos pagos" → "Últimas ventas" (`cliente/dashboard.blade.php:80-87`).
- Relaciones a actualizar: `Member::payments()`, `Membership::payments()`, `Gym::payments()` → versiones `sales()`.

### D10 — Web / registro
- `RegisterController` sin cambios (decisión 2).
- `welcome.blade.php`: eliminar o dejar como redirect a `/` (scaffold muerto).

---

## E. Modelo de relaciones objetivo

```
Gym 1─n Member 1─n Membership ──m─1 (sale) Sale
Gym 1─n Sale 1─n SaleItem 1─1 Product (producto)
Gym 1─n Product 1─n StockMovement (morph reference → Sale)
Gym 1─n CashClosing   (breakdown por method desde Sale)
Gym 1─n User ─1─1 Member (user_id único) ─1─n Trainer
Sale ─m─1 Member (opcional) ─m─1 User (sold_by) ─m─1 Membership (nullable, sale_type=membresia)
```

`Payment` (archivada): se deja intacta en BD, **sin código que la lea**. Las relaciones `payments()` se reemplazan por `sales()`.

---

## F. Flujos de negocio

### F1 — Cliente nuevo (recepción o entrenador)
`MatriculaService::nuevaMatricula` → crea `Member` (code generado) + `Membership` (`status=activa`, sin `renewed_from`) + `Sale` (`sale_type='membresia'`, `membership_id`, `total = price − discount`, `method`, `sold_at=now()`, `sold_by`) + opcional `User` cliente (enlace `member.user_id`). Nada de `Payment`.

### F2 — Renovación (cliente existente)
`MembershipController::store` → nueva `Membership` con `renewed_from=anterior.id`; anterior → `vencida`; `Sale` de tipo membresía. Igual que hoy, pero con `Sale`.

### F3 — Venta de mostrador
`SaleController::store` (`sale_type=producto`) → valida stock (`lockForUpdate`), crea `Sale` + `SaleItem` + `StockMovement(salida)`, actualiza `products.stock`. `member_id` opcional, elegible en el formulario.

### F4 — Pago suelto (ex "Pagos")
`SaleController::store` (`sale_type='otro'`) → `concept` obligatorio, `reference` opcional, `member_id` opcional. Sustituye a `PaymentController::store`.

### F5 — Cierre de caja
Recepción abre Ventas → pestaña "Caja del día" → ve totales esperados por método (desde `Sale` del día) → cuenta el efectivo → `CashClosingController::store` (único por día y sede). El `breakdown` se congela por si luego se anula algo.

### F6 — Anulación de venta
`ventas.anular` → `status='anulada'`; si era producto, movimiento de entrada que repone stock. Fuera de los totales del día y del cierre.

---

## G. Permisos objetivo

Grupos renombrados y slugs actualizados (idempotente en `RolePermissionSeeder`; ver tarea 10 para la lógica de migración de slugs):

| Grupo | Permisos |
|-------|----------|
| Clientes | `clientes.ver` · `clientes.crear` · `clientes.editar` · `clientes.eliminar` |
| Membresías | `membresias.ver` · `membresias.gestionar` · `planes.gestionar` |
| Ventas y caja | `ventas.ver` · `ventas.registrar` · `ventas.anular` · `caja.ver` · `caja.cerrar` |
| Asistencia | `asistencia.registrar` · `asistencia.ver` · `asistencia.aprobar` |
| Entrenamiento | `entrenadores.gestionar` · `rutinas.ver` · `rutinas.gestionar` · `ejercicios.gestionar` · `medidas.registrar` |
| Inventario | `inventario.ver` · `inventario.gestionar` |
| Sistema | `reportes.ver` · `reportes.exportar` · `configuracion.editar` · `usuarios.gestionar` · `planilla.gestionar` · `web.editar` · `sedes.ver-todas` · `sedes.gestionar` |

Asignaciones:
- **recepcion:** clientes.ver/crear/editar · membresias.ver/gestionar · ventas.ver/registrar · caja.ver/cerrar · asistencia.registrar/ver · inventario.ver · reportes.ver
- **entrenador:** clientes.ver/crear · ventas.registrar · asistencia.ver/registrar · rutinas.ver/gestionar · ejercicios.gestionar · medidas.registrar · reportes.ver
- **cliente:** rutinas.ver
- **admin:** sin enumerar (todo por definición).

**Eliminados:** `pagos.ver`, `pagos.registrar`, `pagos.anular`. **Añadidos:** `ventas.ver`, `ventas.anular`. `caja.ver`/`caja.cerrar` se quedan tal cual.

---

## H. Migraciones (ordenadas)

1. **`alter_sales_add_tipo_membresia`** — `sales`: añadir `sale_type` (enum, default `producto`), `membership_id` (FK nullable, `nullOnDelete`), `concept`, `reference`, `notes`; índice `(gym_id, sale_type, sold_at)`; unificar orden del enum de `method` con `config/sparta.php`.
2. **`backfill_sales_from_payments`** — copia **una sola vez** de `payments` → `sales`: cada pago → `Sale` con `sale_type = membership_id ? 'membresia' : 'otro'`, `membership_id`, `concept = payments.concept`, `reference`, `sold_at = paid_at`, `sold_by = registered_by`, `subtotal = discount = 0`, `total = amount`, `method`, `status='completada'`, `number` correlativo por gym (reutilizar numerador robusto). Idempotente: ignorar si ya hay `Sale` con el mismo `source_id` (añadir columna `source_id`/`source_type` en la 1 para trazabilidad o marcar en `notes`).
   > `payments` queda **archivada**: no se dropea, no se renombra; ningún código la consulta de aquí en adelante (decisión 1).
3. **`add_unique_index_members_user_id`** — índice único en `members.user_id` (sanear duplicados en la propia migración antes del índice).
4. **`drop_gym_user_table`** — eliminar `gym_user` y la relación `User::gyms()`.

---

## I. Dashboard objetivo

- KPIs: `ingresosHoy` (Sale día), `ingresosMes` (Sale mes), `sociosActivos`/`sociosInactivos`, `membresiasVencidas`, `matriculasMes`, `asistenciaHoy`, `ventasHoy`.
- Series: ingresos 30 días (Sale), asistencia 14 días, métodos del mes (Sale), acumulado mes vs anterior (Sale), altas 6 meses (Member).
- Desglose por sede con ingresos desde `Sale` (sin filtro global, por sede).
- Filtro `?sedes[]`: aplica a KPIs + series + desglose (tarea 9).

---

## J. Sidebar objetivo (`panel-nav.blade.php`)

**Admin/recepción:**
- Dashboard · Mensajes
- **Clientes:** Clientes · Nueva matrícula · Asistencia · Personal
- **Ventas:** Ventas (pestañas: Productos / Membresías y servicios / Caja del día) · Planilla · Inventario
- **Entrenamiento:** Actividad
- Reportes
- Configuración · Contenido web

Se elimina el enlace "Pagos y caja" (grupo Dinero). El resto de bloques (entrenador/cliente/cuenta) se mantiene, con los textos "Socio"→"Cliente" que correspondan.

---

## K. Eliminación de Pagos y Caja (archivo)

| Hoy | Después |
|-----|---------|
| `admin.pagos.index/store/anular` | eliminadas (pagos sueltos = `Sale` tipo `otro`) |
| `admin.caja.cerrar` | se mantiene, lee `Sale`; accesible desde la pestaña "Caja del día" |
| `PaymentController` | eliminado |
| `Payment` model | eliminado del código; la tabla queda archivada en BD |
| `payments.*` permisos | eliminados; `caja.*` se quedan; `ventas.*` nuevos |
| `cash_closings` | sin cambios de esquema; fuente pasa a `Sale` |
| `reportes.pagos.*` | → `reportes.ventas.*` |

---

## L. Terminología

| Antes | Después | Ámbito |
|-------|---------|--------|
| Socio / socios | Cliente / clientes | rutas, permisos, vistas, sidebar, dashboard, wizard, docs |
| Pagos | Ventas (o "Membresías y servicios") | pantallas, reportes |
| Pagos y caja | Ventas → Caja del día | navegación |

**No se renombran:** tablas (`members`, `payments`), modelos (`Member`, `Payment`), columnas (`member_id`). Evitar el rebote interminable de renombres de esquema.

---

## M. Riesgos y mitigaciones

1. **Backfill incorrecto de `payments`→`sales`** (importes, fechas, métodos). → Migración idempotente con trazabilidad (`source_id`), verificación de totales por gym y método antes/después, y datos demo regenrados.
2. **Renombrado `socios.*`→`clientes.*`** toca muchas referencias. → Tarea mecánica con `grep` final obligatorio; rezar a `route:list` para cazar referencias muertas.
3. **Anular una venta de producto** puede dejar el stock mal si no se repone. → Movimiento de entrada obligatorio en el mismo `DB::transaction`.
4. **Correlativo `count()+1`** → colisiones al anular. → Numeración por gym dentro de transacción (MAX + lock).
5. **Permisos viejos huérfanos** en `permission_role` tras renombrar slugs. → El seeder debe reasignar y borrar los slugs legados de forma idempotente.
6. **Clientes con `user_id` duplicado** antes del índice único. → Saneo en la migración (desenlazar duplicados menos el más reciente).
7. **Romper el panel del cliente** al quitar `payments`. → La vista usa `Member::sales()`; verificar el dashboard del cliente tras el refactor.
8. **`php artisan test` roto de base (SQLite).** → Fuera de alcance; verificación manual con datos demo.

---

## N. Orden de implementación (16 tareas)

> Cada tarea termina con su propio `php artisan route:list` o comprobación manual de la ruta tocada. No pasar a la siguiente con una tarea rota.

1. **Fix B1** — `->parameters(['rutinas' => 'routine'])` en `routes/entrenador.php:27`. Verificar que show/edit/update/destroy de una rutina ya no dan 403.
2. **Migraciones de esquema (H1, H3, H4)** — `sales` enriquecida, índice único `members.user_id` (con saneo), drop `gym_user` + relación `User::gyms()`. `php artisan migrate`.
3. **Backfill histórico (H2)** — `payments` → `sales`, idempotente. Verificar totales por gym y método; confirmar `payments` archivada sin lecturas nuevas.
4. **`App\Services\MatriculaService`** — extraer `nuevaMatricula` / `renovarMembresia` / `crearAcceso` de la lógica triplicada (sin cambiar comportamiento todavía: `Payment::create` puede quedarse temporalmente o pasar a `Sale` aquí mismo).
5. **Refactor `Admin\MatriculaController`** — solo nuevos; crea `Sale` (tipo membresía); redirige a `admin.clientes.show`; quita `Payment`.
6. **Refactor `Entrenador\InscripcionController`** — delega en el servicio; solo nuevos; quita `Payment`.
7. **Refactor `Admin\MembershipController`** — renovación con `renewed_from` + `Sale`; `cancelar` igual; quita `Payment`.
8. **Refactor `Admin\SaleController`** — `sale_type`, `concept`/`reference` para tipo `otro`, selector de cliente, correlativo robusto (también en `VentaController`), `ventas.anular` con reposición de stock. Eliminar `PaymentController` y rutas `admin.pagos.*`.
9. **Refactor `CashClosingController` + vistas de Ventas** — fuente `Sale`; pestaña "Caja del día"; eliminar pantalla "Pagos y caja".
10. **Refactor `DashboardController`** — KPIs/series desde `Sale`; `?sedes[]` aplicado a KPIs y series; `ventasHoy`; `ultimasVentas`.
11. **Renombrado Cliente/Socios** — rutas `socios.*`→`clientes.*`, permisos (seeder con migración de slugs + grupo "Clientes"), carpeta de vistas `admin/socios`→`admin/clientes`, textos. `grep` final sin restos.
12. **Usuarios** — `UserController::update` gestiona `member.user_id`; protección último admin en `update`; validación de `member_id` único.
13. **Sede/multi-sede** — validar `SedeActivaController::store`; `sedesDisponibles()` sin pivot; selector visible para entrenador.
14. **Reportes + panel cliente** — `ventas.csv`/`ventas.imprimir` desde `Sale`; dashboard cliente `sales()`; relaciones `Member/Membership/Gym::sales()`.
15. **DemoSeeder + welcome** — histórico demo generando `Sales` (no `payments`); limpiar `welcome.blade.php`.
16. **Documentación y verificación final** — `AGENTS.md`, `docs/multi-sede.md`, `docs/wizard-matricula.md`, `docs/estado-mejoras-panel.md`, `docs/gestion-personal.md`; `migrate:fresh --seed`; recorrer los 3 paneles; `route:list` sin rutas muertas.

---

## O. Criterios de aceptación globales

- [ ] `routes/entrenador.php`: las rutas de rutinas usan `->parameters()` y show/edit/update/destroy funcionan (sin 403).
- [ ] No existe ninguna referencia a `Payment` en `app/` salvo el archivo archivado de migración y `database/seeders` histórico si aplica. `grep -rn "Payment::" app/` = 0.
- [ ] Una venta de mostrador en efectivo aparece en el arqueo del día y en el dashboard.
- [ ] Matrícula (nuevo) y renovación (existente) son flujos separados; renovar un cliente nunca crea un "nuevo".
- [ ] Ventas muestra tres pestañas coherentes (Productos / Membresías y servicios / Caja del día) con una sola fuente: `Sale`.
- [ ] Dashboard y desglose por sede cuadran con la suma de `sales` completadas por sede.
- [ ] Anular una venta de producto repone stock vía `stock_movements`.
- [ ] `members.user_id` es único; `UserController::update` mantiene el enlace y no deja al sistema sin admin.
- [ ] `SedeActivaController` rechaza sedes ajenas; el selector aparece para cualquier rol con varias sedes disponibles.
- [ ] No quedan referencias a `socios` (rutas/permisos/vistas) salvo el modelo/tabla `members`.
- [ ] `migrate:fresh --seed` regenera datos demo con series económicas en `sales` y ningún error.
- [ ] `route:list` no muestra rutas muertas (`admin.pagos.*`, `reportes.pagos.*`, `admin.socios.*`).

---

---

# CONTEXTO PARA EL AGENTE EJECUTOR (minicontexto)

Eres el agente ejecutor de la reestructuración económica y de terminología de **Sparta Gym**. Trabaja en Windows (PowerShell), proyecto Laravel 12 en la ruta indicada. Antes de tocar nada, lee el plan completo.

- **Archivo del plan (léelo entero, es tu fuente de verdad):** `docs/plan-restructuracion.md`
- **Doc base del proyecto (reglas innegociables):** `AGENTS.md`
- **Proyecto:** `C:\Users\roaca\Documents\PROYECTOS LARAVEL\sparta-gym`

**Qué tienes que conseguir (resumen):** `Sale` pasa a ser la única fuente económica (producto, membresía, servicio y pagos sueltos). La tabla `payments` se archiva en BD tras copiar sus datos a `sales` — no dropearla. El arqueo de caja pasa a ser una pestaña dentro de Ventas. Renombrar la terminología "Socio"→"Cliente" en rutas, permisos y vistas (nunca en tablas/modelos). Matrícula = solo clientes nuevos; renovación = clientes existentes (lógica triplicada se extrae a `App\Services\MatriculaService`).

**Arranca por la tarea 1 del plan (sección N):** arreglar el bug crítico `routes/entrenador.php:27` — el `Route::resource('rutinas', ...)` sin `->parameters(['rutinas' => 'routine'])` rompe show/edit/update/destroy de rutinas con 403.

**Reglas que no se rompen:** aislamiento por sede vía `BelongsToGym` (nunca filtrar por gym a mano; cruzar solo con `sinFiltroDeGimnasio()`); importes congelados en ventas/membresías; caja cuadra por `sold_at` (fecha real, no `created_at`); `products.stock` es saldo (verdad en `stock_movements`, incluye reponer al anular); el admin no se enumera en permisos; contraseñas con cast `hashed`; no añadir comentarios de relleno al código.

**Flujo de trabajo:** sigue el orden de las 16 tareas de la sección N. Cada tarea con su propia comprobación (route:list o prueba manual) antes de pasar a la siguiente. `npm run build` si tocas CSS/JS. Los tests (`php artisan test`) están rotos de base por SQLite — fuera de alcance; verifica con datos demo (`php artisan migrate:fresh --seed`) y navegando los 3 paneles.

**Antes de terminar:** `grep -rn "Payment::" app/` debe dar 0; `grep -ri "socios"` en `routes/`, `resources/views/`, `database/seeders/` sin restos (salvo modelo/tabla `members`); `php artisan route:list` sin `admin.pagos.*` ni `admin.socios.*`.
