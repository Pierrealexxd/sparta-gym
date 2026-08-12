# Plan de revisión y reestructuración del módulo de Asistencias — Sparta Gym

> **Fecha:** 2026-08-08 · **Modo:** análisis → plan → ejecución · **Agente destino:** agente ejecutor (lee este archivo completo antes de tocar nada).
>
> Este documento **no implementa nada**: es el resultado de verificar el flujo real
> de asistencias de extremo a extremo (rutas, controladores, modelos, migraciones,
> vistas, permisos, reportes, datos demo) y el plan técnico para que otro agente
> lo ejecute.
>
> **Reglas que no se rompen (de `AGENTS.md` y `docs/plan-restructuracion.md`):**
> aislamiento por sede vía `BelongsToGym` (nunca filtrar `gym_id` a mano; cruzar solo
> con `sinFiltroDeGimnasio()`); `attendances.attended_on` es columna generada por MySQL
> — **nunca escribirla desde PHP**; importes congelados donde aplique; el admin no se
> enumera en permisos; sin comentarios de relleno en el código; `npm run build` al tocar
> CSS/JS; `php artisan test` está roto de base (SQLite) → verificación con datos demo.

---

## A. Estado actual

### A.1 Hay DOS sistemas de asistencia, separados a propósito

| Concepto | Modelo / tabla | Para quién | Quién registra hoy |
|---|---|---|---|
| **Asistencia de clientes** (torno) | `Attendance` / `attendances` | Socios que entran a entrenar | Solo admin/recepción (`Admin\AttendanceController`) |
| **Asistencia laboral del staff** | `StaffAttendance` / `staff_attendances` | Entrada/salida de trabajo del entrenador | El propio entrenador (`Entrenador\AttendanceController`) |
| **Solicitudes de corrección** | `AttendanceEditRequest` / `attendance_edit_requests` | Corrección de horas | Entrenador solicita; admin aprueba/rechaza |

Ambos están documentados en comentarios del propio código como "dos cosas separadas
a propósito" (`app/Models/StaffAttendance.php:11-15`, `app/Models/Attendance.php:20-21`,
`app/Http/Controllers/Admin/AttendanceController.php:13-19`).

### A.2 `Attendance` (torno de clientes)

- **Esquema** (`database/migrations/2026_08_03_000105_create_payments_and_attendances_tables.php:48-66`):
  `gym_id` (FK, cascade), `member_id` (FK, cascade), `registered_by` (FK users, nullable,
  **nunca se filtró por índice**), `checked_in_at`, `checked_out_at` (nullable),
  `method` enum `qr|codigo|busqueda|manual` (default `qr`), `attended_on` (columna
  generada `DATE(checked_in_at)`, fuera de `$fillable`), timestamps. Índices:
  `(gym_id, attended_on)`, `(member_id, checked_in_at)`.
- **Modelo** (`app/Models/Attendance.php`): `BelongsToGym`; relaciones `member()`,
  `registeredBy()`; scopes `deHoy()`, `dentro()`; atributo `duracion_minutos`.
- **Registro** (`app/Http/Controllers/Admin/AttendanceController.php:47-83`): `entrar()`
  acepta **código, número de documento o token QR** indistintamente; valida socio activo
  y membresía vigente (`is_up_to_date`); si el socio ya está "dentro" → marca **salida**;
  si no → crea entrada con `checked_in_at = now()`, `method` `qr|codigo`, `registered_by`.
- **Listado** (`AttendanceController::index`): tabla por fecha + búsqueda por nombre/código,
  paginada; la vista `admin/asistencia/index.blade.php` (formulario de torno + tabla).
- **Rutas** (`routes/admin.php:91-93`): `admin.asistencia.index/entrar/salir`, solo
  `rol:admin,recepcion`, **sin middleware de permiso**.

### A.3 `StaffAttendance` (asistencia laboral del staff)

- **Esquema** (`2026_08_08_185901_create_staff_attendances_table.php`): `gym_id`,
  `user_id` (FK cascade), `clocked_in_at`, `clocked_out_at` (nullable), `turno` enum
  `manana|tarde|doble`, timestamps. Índices `(gym_id, clocked_in_at)`, `(user_id, clocked_in_at)`.
- **Modelo** (`app/Models/StaffAttendance.php`): `BelongsToGym`; `user()`, `editRequests()`;
  scope `dentro()`; `turno_legible`.
- **Registro** (`app/Http/Controllers/Entrenador/AttendanceController.php:40-59`): `marcar()`
  alterna entrada/salida del **usuario autenticado**; entrada exige `turno`.
  `destroy()` borra solo registros propios (403 si no). `solicitarEdicion()` crea la
  solicitud (bloquea si ya hay una pendiente).
- **Vista** `entrenador/asistencia/index.blade.php`: tarjeta de marcar (turno o salida)
  + tabla de las últimas 30 marcaciones con editar/eliminar.
- **Rutas** (`routes/entrenador.php:46-51`): `entrenador.asistencia.*`, todas bajo
  `permiso:asistencia.registrar`.

### A.4 `AttendanceEditRequest` (solicitudes de corrección)

- **Esquema**: `attendance_edit_requests` creada en `183759` apuntando a `attendances`
  (socios) y **corregida el mismo día** en `185902` para apuntar a `staff_attendances`
  (laboral): hoy la tabla solo tiene `staff_attendance_id` (FK cascade), `requested_by`,
  `checked_in_at`, `checked_out_at`, `reason`, `status` enum `pendiente|aprobada|rechazada`,
  `reviewed_by`, `reviewed_at`. Índice `(gym_id, status)`.
- **Modelo** (`app/Models/AttendanceEditRequest.php`): `BelongsToGym`; `staffAttendance()`,
  `requestedBy()`, `reviewedBy()`; scope `pendientes()`.
- **Aprobación** (`app/Http/Controllers/Admin/AttendanceEditRequestController.php`):
  `index()` lista pendientes de la sede activa; `pendientesJson()` alimenta la campanita;
  `aprobar()` aplica las horas al `StaffAttendance` real; `rechazar()` archiva.
- **Vista** `admin/personal/solicitudes.blade.php`: tabla de pendientes (entrenador,
  entrada actual, entrada propuesta, motivo, aprobar/rechazar).
- **Rutas** (`routes/admin.php:99-104`): `admin.personal.solicitudes.*` bajo
  `permiso:asistencia.aprobar`.

### A.5 Las dos referencias de calendario

Ambas son **cuadrícula construida a mano en Blade + Alpine**, sin librería de calendario,
con las mismas clases CSS (`panel.css:853-954`), el mismo modal `.modal__fondo/.modal__caja`,
la misma navegación por `?mes=&anio=` y el mismo patrón `modoTodas` con insignia de sede:

1. **Personal** (`Admin\StaffAttendanceController` → `admin/personal/index.blade.php`):
   calendario mensual de marcaciones del staff; clic en día → modal con la lista
   (nombre, turno, hora, insignia de sede en modo "todas"). Es la referencia que le gusta
   al dueño. **Contiene además el botón "Solicitudes de corrección" en la cabecera.**
2. **Actividad** (`Admin\ActividadController` → `admin/actividad/calendario.blade.php`):
   el mismo patrón pero **más completo**: agrupa por sección (pagos / matrículas /
   rutinas) y cada item se despliega con detalle (`calendario__item-boton` +
   `calendario__detalle`). Es la implementación técnicamente más rica y reutilizable.

### A.6 Permisos, menú, sede y reportes

- **Permisos** (`database/seeders/RolePermissionSeeder.php:35-39`): `asistencia.registrar`,
  `asistencia.ver`, `asistencia.aprobar`. Asignados: recepción → `registrar`+`ver`;
  entrenador → `registrar`+`ver`; admin → todo (no enumerado). **`asistencia.ver` no se
  comprueba en ninguna ruta hoy.**
- **Menú** (`resources/views/layouts/partials/panel-nav.blade.php:40-44`): admin/recepción
  tienen **un solo** enlace "Asistencia" que abre el torno (`admin.asistencia.index`) y
  adentro `_pestanas` navega Socios/Personal (`admin/asistencia/_pestanas.blade.php`).
  Entrenador: enlace "Asistencia" que abre su marcación laboral (`panel-nav:123-127`).
- **Sede**: `BelongsToGym` (scope global + auto-relleno al crear, `app/Models/Concerns/BelongsToGym.php`),
  `GymContext` + middleware `sede.activa` (`EstablecerSedeActiva`). En modo "todas las
  sedes" (`GymContext::id() === null`) cada calendario muestra la sede de cada registro.
  `SedeActivaController::store` **no valida** el `sede_id` (el middleware lo contiene al
  final, pero el controlador escribe cualquier id en sesión) — deuda ya anotada en
  `docs/plan-restructuracion.md` B8.
- **Reportes** (`Admin\ReporteController`): solo pagos, membresías y planilla. **No existe
  ningún reporte de asistencias.**
- **Datos demo** (`DemoSeeder::asistencias`, `database/seeders/DemoSeeder.php:203-229`):
  genera `attendances` de clientes con `method` `qr|codigo|busqueda` y **sin `registered_by`**;
  no genera `staff_attendances` ni `attendance_edit_requests`. Los calendarios de Personal,
  las solicitudes y cualquier vista por entrenador quedan **vacíos** con datos demo.

---

## B. Diagnóstico

### B.1 Qué funciona correctamente (verificado en código)

- **El torno de clientes** (`Admin\AttendanceController::entrar`): registro por código /
  documento / QR, validación de membresía vigente, alternancia entrada/salida, persiste en
  `attendances` con `registered_by`. El listado por fecha y búsqueda funciona.
- **La asistencia laboral del entrenador** (`Entrenador\AttendanceController`): marcar
  entrada/salida con turno, borrar propio, solicitar corrección. Flujo completo y cerrado.
- **Solicitudes de corrección (staff)**: crear (entrenador) → ver pendientes + campanita
  (admin) → aprobar/rechazar con aplicación al registro real. El diseño actual del contenedor
  es el que el dueño quiere conservar conceptualmente.
- **Los dos calendarios** (Personal y Actividad) renderizan y navegan correctamente.

### B.2 El problema real: el flujo "entrenador registra asistencia de clientes" NO EXISTE

El dueño describe un flujo que asume implementado:

> "Entrenador → módulo Asistencias → registrar asistencia del cliente → asistencia almacenada
> → Administrador puede visualizarla y generar reportes."

**No existe ninguna pieza de ese flujo.** Verificado extremo a extremo:

- No hay ruta en `routes/entrenador.php` que cree una `Attendance` de un socio.
- `Entrenador\AttendanceController` solo toca `StaffAttendance` (marcado laboral propio).
- El único registro de asistencia de clientes vive en `routes/admin.php:91-93`, restringido
  a `rol:admin,recepcion`.
- La vista del entrenador (`entrenador/asistencia/index.blade.php`) es la de su marcado
  laboral, no un calendario de asistencias de clientes.
- No hay buscador de cliente en el panel del entrenador para registrar asistencia.

Por eso **"no me está dejando registrar"**: no es un bug de un flujo existente, es que la
función **no está construida**. Si el entrenador entra a "Asistencia" ve su fichaje laboral
(que sí funciona), y en ningún lado tiene una pantalla para registrar la entrada de un socio.

### B.3 Qué está incompleto o roto (oportunidades del plan)

| # | Hallazgo | Evidencia |
|---|---|---|
| 1 | **No existe registro de asistencia de clientes por el entrenador** | `routes/entrenador.php:46-51`; `Entrenador\AttendanceController` |
| 2 | El permiso `asistencia.ver` **no se comprueba en ninguna ruta** | `routes/admin.php:91-108`, `routes/entrenador.php:46-51` |
| 3 | **No hay reportes de asistencia** (CSV/impresión) | `Admin\ReporteController` solo tiene pagos/membresías/planilla |
| 4 | El entrenador no tiene vista de calendario de sus registros | `entrenador/asistencia/index.blade.php` es tabla plana |
| 5 | Las solicitudes de corrección están **atadas a `staff_attendances`**; no pueden corregir una asistencia de cliente | migración `185902`; `AttendanceEditRequest.php:29` |
| 6 | `SedeActivaController::store` **no valida** el `sede_id` | `app/Http/Controllers/SedeActivaController.php:10-25` (deuda B8 del plan de reestructuración) |
| 7 | Dos calendarios casi idénticos duplicados (Personal y Actividad) — riesgo de divergencia | `admin/personal/index.blade.php` vs `admin/actividad/calendario.blade.php` |
| 8 | Sin protección de duplicados para asistencias de cliente más allá del toggle "dentro" del torno | `Admin\AttendanceController::entrar:69-74`; no hay índice/regla `(member_id, attended_on)` |
| 9 | Sin índice para consultas por registrador: el calendario del entrenador (y el filtro por entrenador del admin) escanearía | índices de `attendances`: solo `(gym_id, attended_on)` y `(member_id, checked_in_at)` |
| 10 | Datos demo sin `registered_by`, sin `staff_attendances`, sin solicitudes | `DemoSeeder.php:203-229` |
| 11 | El valor `manual` del enum `method` existe pero nadie lo usa | `000105:56`; `AttendanceController::entrar` solo escribe `qr|codigo` |
| 12 | `Member::scopeBuscar` no busca por email (menor) | `app/Models/Member.php:107-122` (ya anotado en plan de reestructuración B10) |

### B.4 Qué está duplicado

- **La cuadrícula de calendario**: copiada en `admin/personal/index.blade.php` y
  `admin/actividad/calendario.blade.php` (nav, celdas, modal, Alpine). Es el candidato
  natural a extraer en un componente compartido.
- **El patrón de pestañas**: `admin/asistencia/_pestanas.blade.php` ya comparte la
  navegación Socios/Personal; se reutiliza, no se duplica.

---

## C. Flujo actual de asistencia (lo que existe de verdad)

### C.1 Torno de clientes (admin/recepción)

1. Recepción abre **Asistencia** → formulario "Código, documento o QR" (`admin/asistencia/index.blade.php:9-19`).
2. `POST admin.asistencia.entrar` (`AttendanceController::entrar`): busca socio por
   `code | qr_token | document`, exige `status=activo` y membresía vigente.
3. Si el socio ya tiene una `Attendance` abierta (`dentro()`) → marca `checked_out_at` (salida).
4. Si no → crea la entrada con `checked_in_at=now()`, `method=qr|codigo`, `registered_by=usuario`.
5. El listado de ese día (tabla) se actualiza al recargar; el dashboard suma `asistenciaHoy`
   y la serie de 14 días (`Admin\DashboardController`).

### C.2 Asistencia laboral del entrenador

1. Entrenador abre **Asistencia** → elige turno → "Marcar entrada" (`Entrenador\AttendanceController::marcar`).
2. Crea `StaffAttendance` con `user_id=él`, `clocked_in_at=now()`, `turno`.
3. Al volver, el botón cambia a "Marcar salida" (cierra la sesión abierta).
4. Puede borrar su marcación (`destroy`, 403 si no es suya) o pedir corrección de horas
   (`solicitarEdicion` → crea `AttendanceEditRequest` pendiente).
5. Admin lo ve en el **calendario de Personal** (`admin.personal.index`) y aprueba/rechaza
   desde **Solicitudes** o la campanita.

### C.3 Asistencia de clientes **no tiene** representación ni en el panel del entrenador ni en reportes.

---

## D. Flujo objetivo

### D.1 Principio

> `attendances` (asistencia de clientes) es el contenido principal del módulo **Asistencias**,
> para admin y para entrenador. El entrenador **registra** entradas de los clientes que tiene
> a cargo; el admin **supervisa** con calendario + filtros y **gestiona** correcciones.
> `staff_attendances` (fichaje laboral) sigue existiendo como pestaña secundaria "Mi trabajo".

### D.2 Flujo del entrenador (nuevo)

1. Abre **Asistencias** → ve su **calendario** del mes con las asistencias que ha registrado
   (`registered_by = él`).
2. Pulsa **"Registrar asistencia"** → modal con buscador de **sus clientes** (solo
   `Trainer::activeMembers()`).
3. Selecciona cliente → `POST entrenador.asistencia.registrar` → se crea `Attendance`
   con `method=manual`, `registered_by=él`, `checked_in_at=now()`, sede = la suya.
4. Protecciones: cliente activo + membresía vigente; si ya está "dentro" → error
   ("ya tiene una asistencia abierta"); sin manipulación de fechas (siempre `now()` del servidor).
5. La celda del día se marca al instante y la asistencia aparece en el modal del día.
6. Desde el modal puede: **marcar salida** de una abierta o **solicitar corrección** de horas
   de un registro suyo (queda pendiente de aprobación del admin).

### D.3 Flujo del administrador (nuevo)

1. Abre **Asistencias** → aterriza en el **Calendario** de asistencias de clientes del mes
   (pestañas: Calendario / Torno / Personal; botón **"Solicitudes de corrección"** en cabecera).
2. Filtros: navegación mes/anio + **entrenador** (quién registró) + **cliente** (buscar por
   nombre/código/documento). La **sede** la controla el selector global de la cabecera del
   panel; en modo "todas las sedes" cada registro lleva insignia de sede.
3. Clic en un día con asistencias → modal con la lista (cliente, hora entrada/salida,
   método, entrenador que registró, sede si corresponde, estado abierta/cerrada).
4. Gestiona **Solicitudes de corrección** desde su botón: aprueba (aplica horas) o rechaza;
   ve historial (pendientes/aprobadas/rechazadas).
5. Genera **reportes** de asistencia por período (CSV/impresión) con filtros de sede,
   entrenador y cliente.

### D.4 Correcciones (nuevo)

- El entrenador puede pedir corrección de **horas de una asistencia de cliente** que él
  registró, y de su **marcación laboral** (ya existe). Una sola cola, un solo contenedor.
- El admin nunca edita directo: aprueba o rechaza la solicitud.

---

## E. Diseño objetivo

Estructura visual del módulo, respetando tokens (`tokens.css`), `.tarjeta`, `.btn`,
`.pestanas__nav`, `.calendario__*` y el layout actual. **No se crea un diseño nuevo.**

### E.1 Admin/recepción — "Asistencias"

```
Asistencias  (membrete: "X clientes dentro ahora")
├─ [Pestañas]  Calendario · Torno · Personal
└─ [Botón cabecera]  Solicitudes de corrección (con contador)
    └─ (solo si tiene permiso asistencia.aprobar)

Contenido principal → CALENDARIO (default)
├─ Navegación mes anterior / siguiente (patrón calendario__nav)
├─ Filtros:  Entrenador [select] · Cliente [buscador]   (sede = selector global)
├─ Cuadrícula mensual: días con asistencias marcados con contador
└─ Modal del día: lista de asistencias (cliente · hora · método ·
     entrenador · sede en modo "todas" · botones salida / solicitar corrección)
```

- **Torno**: la pantalla actual (`admin/asistencia/index.blade.php`) pasa a ser la pestaña
  "Torno" (formulario + tabla del día). No se pierde nada.
- **Personal**: el calendario de `staff_attendances` actual (`admin.personal.index`) pasa a
  ser la pestaña "Personal" (el botón de Solicitudes se traslada a la cabecera de Asistencias).

### E.2 Entrenador — "Asistencias"

```
Asistencias  (membrete: "X registros este mes")
├─ [Pestañas]  Calendario · Mi trabajo
└─ [Botón cabecera]  Registrar asistencia
    └─ Modal: buscador de mis clientes → confirmar

Contenido principal → CALENDARIO (default)
├─ Navegación mes anterior / siguiente
├─ Cuadrícula mensual: días con asistencias registradas por mí
└─ Modal del día: cliente · hora entrada/salida · acciones
     (marcar salida / solicitar corrección, solo registros propios)
```

- **Mi trabajo**: la pantalla actual de marcación laboral (`entrenador/asistencia/index.blade.php`)
  se conserva intacta como pestaña, para no perder la funcionalidad de fichaje.
- Se muestra lo mínimo: cliente, hora, estado. Nada de información administrativa superflua.

### E.3 Solicitudes de corrección (admin)

```
Solicitudes de corrección
├─ Pestañas:  Pendientes · Historial
└─ Tabla (reusa el diseño de admin/personal/solicitudes.blade.php):
     Estado · Fecha · Cliente (si es asistencia de cliente) o Entrenador (si es laboral) ·
     Solicitante · Sede · Entrada actual · Entrada propuesta · Motivo · Aprobar / Rechazar
```
Es una **función complementaria**, accesible por botón desde Asistencias, no el contenido principal.

---

## F. Calendario — componente a reutilizar

**Decisión:** reutilizar el patrón de cuadrícula existente y extraerlo en un **componente
Blade compartido `<x-calendario>`** (no una librería nueva — coherencia con el proyecto).

- **Base:** la implementación de **Actividad** (`admin/actividad/calendario.blade.php`) es
  técnicamente la más completa (secciones, detalle desplegable, `modoTodas`). La de
  **Personal** (`admin/personal/index.blade.php`) es la más simple y la que el dueño quiere
  como referencia visual. Ambas comparten las mismas clases CSS (`panel.css:853-954`),
  el mismo modal y la misma navegación: **el componente debe salir de su intersección.**
- **Qué encapsula `<x-calendario>`:**
  - Navegación mes anterior/siguiente (`calendario__nav`) con `?mes=&anio=`.
  - Cabecera de días (Lun…Dom) + cuadrícula con celdas vacías de offset y contador por día.
  - `x-data="{ diaAbierto: null }"` y el `@click` por celda.
  - El **esqueleto del modal** (`modal__fondo`/`modal__caja`/cabecera con fecha + cerrar).
  - Props: `mes`, `anio`, `nombreMes`, `anterior`, `siguiente`, `ruta` (ruta de navegación),
    `celdas` (mapa `fecha => count`).
  - Un slot `#dia` que el padre rellena con su lista de items (los datos agrupados del padre
    quedan en scope dentro del slot, igual que ya hace Actividad con `x-show="diaAbierto === '...'"`).
- **Refactor:** `admin/personal/index.blade.php` y `admin/actividad/calendario.blade.php`
  pasan a usarlo (mismo resultado visual, cero regresión). Las nuevas vistas
  `admin/asistencia/calendario` y `entrenador/asistencia/index` lo usan también.
  Resultado: **un solo calendario** en vez de cuatro copias.
- **Lógica del calendario** (mes/anio, offset lunes-primero, `modoTodas`) vive en los
  controladores, igual que hoy (Actividad y StaffAttendance ya la tienen idéntica).

---

## G. Solicitudes de corrección — integración en el módulo

- **Generalizar el objetivo**: `AttendanceEditRequest` pasa a poder apuntar a una
  **asistencia de cliente** (`attendances.id`) o a una **marcación laboral**
  (`staff_attendances.id`) — ver sección I. Un solo contenedor, un solo flujo de aprobación.
- **Desde Asistencias:** botón "Solicitudes de corrección" en la cabecera (solo con
  `asistencia.aprobar`), con contador de pendientes (reusa `pendientesJson` y la campanita).
- **El calendario deja de ser sustituido por la lista de solicitudes**: la lista vive en su
  propia página/`<div>` alcanzable por el botón.
- **Vista:** se conserva el diseño actual de `admin/personal/solicitudes.blade.php`
  (tabla + botones Aprobar/Rechazar) y se le añade: columna **Estado** (pendiente/aprobada/
  rechazada), **Cliente o Entrenador** según el tipo, **Sede** (insignia en modo "todas"),
  y pestañas **Pendientes / Historial**.
- **Reglas:** no se aprueba una solicitud ya revisada (existe); no hay dos solicitudes
  pendientes para el mismo registro (existe para staff, se replica para clientes);
  aprobar aplica las horas al registro real; el entrenador **no puede aprobar** (no tiene el
  permiso) ni pedir corrección de un registro que no le pertenece.

---

## H. Backend

### H.1 Modelos

- **`Attendance`** (`app/Models/Attendance.php`): añadir scopes
  - `registradasPor(int $userId)` → `where('registered_by', $userId)`
  - `deCliente(int $memberId)` → `where('member_id', $memberId)`
  - `entreFechas(Carbon $desde, Carbon $hasta)` → `whereBetween('checked_in_at', [$desde, $hasta])`
  - (el filtro por sede ya lo aporta `BelongsToGym`; en "todas", el controlador usa el patrón
    `when(GymContext::id(), ...)` de Personal/Actividad)
- **`AttendanceEditRequest`**: añadir relación `attendance()` (BelongsTo `Attendance`),
  scope `historial()` (= no `pendientes`), acceso `objetivo()` (devuelve `attendance` o
  `staffAttendance` según cuál esté cargada) y `tipo()` (`'cliente'|'staff'`).
- **`Member`**: `scopeBuscar` gana búsqueda por email (menor, ya anotado en el plan de
  reestructuración). `is_up_to_date` y `scopeActivos` se reutilizan tal cual.

### H.2 Servicio

Nuevo **`App\Services\AsistenciaService`** — la regla de negocio en un solo lugar
(mismo argumento que el plan de reestructuración para `MatriculaService`):

- `registrarEntrada(Member $member, User $registrador, string $method = 'manual'): Attendance`
  — valida: `member.status === 'activo'`, `member.is_up_to_date`, no existe `Attendance`
  abierta del cliente (`dentro()`); crea con `checked_in_at = now()`, `method`, `registered_by`.
  La sede la rellena `BelongsToGym` al crear (nada de `gym_id` desde el formulario).
- `marcarSalida(Attendance $attendance, User $usuario): Attendance` — si `checked_out_at`
  es nulo, lo pone `now()`.
- `solicitarCorreccion(Attendance|StaffAttendance $registro, User $solicitante, array $datos): AttendanceEditRequest`
  — bloquea si ya hay `pendiente`; crea la solicitud con el FK correcto (`attendance_id` o
  `staff_attendance_id`); `checked_in_at` requerido, `checked_out_at` nullable `after:checked_in_at`,
  `reason` max 255.
- `aprobar(AttendanceEditRequest $solicitud, User $revisor): void` — aborta si no está
  `pendiente`; aplica horas al `objetivo()`; marca `aprobada` + `reviewed_by/at`.
- `rechazar(AttendanceEditRequest $solicitud, User $revisor): void`.

### H.3 Controladores

- **`Admin\AttendanceController`**: + `calendario(Request)` (mes/anio, filtros
  `entrenador` y `cliente`, `Attendance` agrupada por día, `modoTodas`); `entrar()` y `salir()`
  delegan en el servicio (mismo comportamiento del torno).
- **`Entrenador\AttendanceController`**:
  - `calendario()` — asistencias de clientes **registradas por el usuario** agrupadas por día;
  - `registrarEntrada()` — valida que el `member` esté entre `Trainer::activeMembers()` del
    usuario (403 si no) y delega en el servicio;
  - `marcarSalida()` — solo sobre registros `registered_by = yo`;
  - `solicitarEdicionCliente()` — solo sobre registros `registered_by = yo`;
  - `miMarcacion()`, `marcar()`, `destroy()`, `solicitarEdicion()` — el flujo laboral actual,
    intacto.
- **`Admin\AttendanceEditRequestController`**: `index()` (+ pestañas Pendientes/Historial),
  `pendientesJson()` (detalle con cliente cuando sea asistencia de cliente), `aprobar()` /
  `rechazar()` vía servicio. La vista pasa a `admin/asistencia/solicitudes.blade.php`.
- **`Admin\StaffAttendanceController`**: sin cambios (sigue siendo la pestaña "Personal").
- **`Admin\ActividadController`**: sin cambios de lógica (solo refactor visual al componente).
- **`Admin\ReporteController`**: + `asistenciaCsv()` y `asistenciaImprimir()` con filtros
  `desde/hasta` (+ sede por contexto, `entrenador`, `cliente`); columnas: fecha, hora
  entrada/salida, cliente, entrenador, sede, método. Reusa el helper `csv()` existente.
- **`SedeActivaController::store`**: validar el `sede_id` contra `sedesDisponibles()`
  (422 si no corresponde; `'todas'` solo con `sedes.ver-todas`) — cierra la deuda B8 del
  plan de reestructuración, necesaria para que el filtro por sede del calendario sea confiable.

### H.4 Endpoints (objetivo)

`routes/admin.php` (todo bajo `rol:admin,recepcion`):
```
# Asistencias de clientes (torno + calendario + solicitudes)
GET  panel/asistencia/calendario          → admin.asistencia.calendario   [permiso:asistencia.ver]
GET  panel/asistencia                     → admin.asistencia.index        [permiso:asistencia.ver]   (pestaña Torno)
POST panel/asistencia                     → admin.asistencia.entrar       [permiso:asistencia.registrar]
POST panel/asistencia/{attendance}/salida → admin.asistencia.salir        [permiso:asistencia.registrar]
GET  panel/asistencia/solicitudes         → admin.asistencia.solicitudes.index  [permiso:asistencia.aprobar]
GET  panel/asistencia/solicitudes/pendientes.json → …pendientes-json            [permiso:asistencia.aprobar]
POST panel/asistencia/solicitudes/{s}/aprobar  → …aprobar                       [permiso:asistencia.aprobar]
POST panel/asistencia/solicitudes/{s}/rechazar → …rechazar                      [permiso:asistencia.aprobar]
# Personal (staff) — sin cambios de nombres
GET  panel/personal → admin.personal.index  [permiso:asistencia.ver]
# Reportes
GET  panel/reportes/asistencia.csv       → admin.reportes.asistencia.csv   [permiso:reportes.exportar]
GET  panel/reportes/asistencia/imprimir  → admin.reportes.asistencia.imprimir [permiso:reportes.exportar]
```
> Al mover `admin.personal.solicitudes.*` → `admin.asistencia.solicitudes.*` hay que actualizar
> el único consumidor de la campanita: `layouts/panel.blade.php:26-29`.

`routes/entrenador.php` (bajo `permiso:asistencia.registrar`):
```
GET  entrenador/asistencia                → entrenador.asistencia.index      (calendario de mis registros)
POST entrenador/asistencia/registrar      → entrenador.asistencia.registrar
POST entrenador/asistencia/{attendance}/salida            → …salida
POST entrenador/asistencia/{attendance}/solicitar-edicion → …solicitar-edicion
GET  entrenador/asistencia/mi-marcacion   → …mi-marcacion   (fichaje laboral, contenido actual)
POST entrenador/asistencia/marcar         → …marcar         (sin cambios)
DELETE entrenador/asistencia/marcacion/{marcacion} → …destroy   (sin cambios)
POST entrenador/asistencia/marcacion/{marcacion}/solicitar-edicion → …marcacion.solicitar-edicion
```
> Ojo con el orden: las rutas de texto (`mi-marcacion`) antes de `{attendance}` (mismo patrón
> que `routes/web.php:68-76`). `DELETE` y `POST` sobre `{marcacion}` / `{attendance}` no
> colisionan entre sí por método.

### H.5 Validaciones y permisos

- **Registrar entrada (entrenador):** `member_id` requerido y **verificado en backend** contra
  `Trainer::activeMembers()` del usuario (nunca confiar en ocultar el buscador).
- **Registrar entrada (torno):** se mantiene la validación actual (`code|qr_token|document`,
  activo, `is_up_to_date`).
- **Permisos aplicados de verdad (backend):**
  - `asistencia.ver` → calendarios (admin y entrenador) y listado del torno.
  - `asistencia.registrar` → `entrar`, `salir`, `registrar`, `marcar` (ya existe), `salida`.
  - `asistencia.aprobar` → solicitudes (ya existe).
  - `reportes.exportar` → reportes de asistencia.
- **Sin permisos nuevos**; no hace falta tocar el seeder.

---

## I. Base de datos

### I.1 Tablas involucradas

| Tabla | Uso | Estado |
|---|---|---|
| `attendances` | Asistencia de clientes (contenido principal) | Existe; + índices |
| `staff_attendances` | Fichaje laboral del staff | Existe; sin cambios |
| `attendance_edit_requests` | Correcciones (clientes + staff) | Existe; + `attendance_id` |
| `users` / `members` / `gyms` | Relaciones `registered_by` / `member` / `gym` | Existen |

### I.2 Migraciones necesarias (2)

**M1 — `add_attendance_id_to_attendance_edit_requests`**
- `$table->foreignId('attendance_id')->nullable()->after('gym_id')->constrained()->cascadeOnDelete();`
- Restricción de integridad: **exactamente uno** de `attendance_id` / `staff_attendance_id`
  debe estar presente (`CHECK (attendance_id IS NOT NULL) <> (staff_attendance_id IS NOT NULL)`
  — MySQL 8 soporta `CHECK`).
- Índice `(gym_id, status, attendance_id)` para la cola por tipo.
- La tabla no tiene datos reales todavía (se creó el mismo día), no hay backfill.

**M2 — `add_indexes_to_attendances`**
- `(gym_id, registered_by, attended_on)` — calendario del entrenador y filtro por entrenador.
- `(member_id, attended_on)` — historial por cliente (la consulta "¿ya registró hoy?").

### I.3 Restricciones y decisiones

- **No** se toca el enum `method`: `manual` ya existe y es el valor para el registro del
  entrenador (evita churn de migración y un valor nuevo redundante). El `registered_by`
  distingue quién registró, no hace falta un método "entrenador".
- **No** se añade columna de "tipo": `attendances` es asistencia de clientes y punto; el
  fichaje laboral ya vive separado (`staff_attendances`), que es el diseño correcto.
- `attended_on` **nunca** se escribe desde PHP (columna generada).
- **Protección de duplicados por regla de negocio** (en `AsistenciaService`): no crear
  entrada si existe `Attendance` abierta del cliente. No se impone índice único
  `(member_id, attended_on)`: el torno permite re-entrar el mismo día tras marcar salida
  (visita doble); una restricción así cambiaría el comportamiento del torno. Si el dueño
  quiere "una asistencia por día máximo", es una decisión aparte (se anota, no se decide aquí).

---

## J. Frontend

### J.1 Páginas y componentes

| Archivo | Qué es |
|---|---|
| `resources/views/components/calendario.blade.php` | **NUEVO** — componente compartido (nav + cuadrícula + esqueleto de modal + Alpine) |
| `resources/views/admin/asistencia/calendario.blade.php` | **NUEVO** — calendario admin con filtros y botón Solicitudes |
| `resources/views/admin/asistencia/solicitudes.blade.php` | **NUEVO** — contenedor de solicitudes (desde `admin/personal/solicitudes.blade.php`) |
| `resources/views/admin/asistencia/_pestanas.blade.php` | **EDITAR** — pestañas: Calendario · Torno · Personal |
| `resources/views/admin/asistencia/index.blade.php` | **EDITAR** — sigue siendo el torno (pestaña "Torno"); quitar `_pestanas` del doble uso si aplica |
| `resources/views/admin/personal/index.blade.php` | **EDITAR** — usa `<x-calendario>`; quitar el botón de Solicitudes (se muda a Asistencias) |
| `resources/views/admin/actividad/calendario.blade.php` | **EDITAR** — refactor a `<x-calendario>` (mismo resultado) |
| `resources/views/entrenador/asistencia/index.blade.php` | **EDITAR** — calendario de mis registros + modal "Registrar asistencia" |
| `resources/views/entrenador/asistencia/mi-marcacion.blade.php` | **NUEVO** — contenido actual del fichaje laboral |
| `resources/views/entrenador/asistencia/_pestanas.blade.php` | **NUEVO** — pestañas: Calendario · Mi trabajo |
| `resources/views/admin/reportes/imprimir-asistencias.blade.php` | **NUEVO** — impresión de reporte (mismo patrón que `imprimir-pagos`) |
| `resources/views/layouts/partials/panel-nav.blade.php` | **EDITAR** — enlace admin → `admin.asistencia.calendario` |
| `resources/views/layouts/panel.blade.php` | **EDITAR** — campanita con las rutas nuevas de solicitudes |

### J.2 Estado (Alpine) y filtros

- **Calendario:** `x-data="{ diaAbierto: null }"` (dentro del componente); el slot del día
  usa `x-show="diaAbierto === '{{ $fecha }}'"`.
- **Modal registrar (entrenador):** `x-data="{ abierto: false, buscando: false, resultados: [] }"`
  con búsqueda AJAX a `GET entrenador.asistencia.buscar-clientes` (endpoint nuevo, devuelve
  `id | full_name | code` de `Trainer::activeMembers()` con `scopeBuscar`). Seleccionar →
  `POST registrar` → toast de éxito → recarga.
- **Filtros admin:** navegación mes/anio (query params) + `<select>` de entrenadores que
  registraron en el período + `<input>` de cliente (submit GET). La sede es el **selector
  global** de la cabecera (`panel-nav.blade.php:4-25`), que ya filtra vía `GymContext`.
- **Solicitudes:** pestañas Pendientes/Historial vía `?estado=` o dos vistas.

### J.3 Modal / detalle del día (admin)

Item mínimo del día: **cliente · hora entrada/salida · método · entrenador que registró ·
estado (abierta/cerrada) · insignia de sede en modo "todas"**. Acciones: "Marcar salida"
si está abierta. Sin campos administrativos superfluos.

---

## K. Sedes

- **Filtro por sede = selector global del panel** (`panel-nav` + `GymContext` +
  `EstablecerSedeActiva`). Es el patrón que ya usan Personal y Actividad y evita duplicar
  un selector local que podría divergir de la sede activa real.
- El controlador filtra con `when(GymContext::id(), fn ($q, $gymId) => $q->where('gym_id', $gymId))`
  (idéntico a `StaffAttendanceController::index:41`).
- En **modo "todas las sedes"** (`GymContext::id() === null`), cada item del modal y cada
  fila de solicitudes muestra la **insignia de sede** (`gym?->name`), igual que Personal/Actividad.
- La **sede que se asigna al registrar** la rellena `BelongsToGym` al crear: el entrenador
  nunca envía `gym_id`, así que no puede falsearlo (y al estar su `User.gym_id` fijado, sus
  clientes son de su misma sede).
- **Requiere cerrar la deuda B8:** validar `SedeActivaController::store` para que el selector
  global nunca escriba una sede ajena (de lo contrario el calendario podría quedar vacío por
  una sede inválida en sesión).

---

## L. Roles

| Acción | Administrador / Recepción | Entrenador |
|---|---|---|
| Registrar asistencia de cliente (torno, QR/código) | ✅ `asistencia.registrar` | ❌ |
| Registrar asistencia de cliente (sus asignados, `manual`) | ✅ | ✅ `asistencia.registrar` |
| Ver calendario de asistencias de clientes | ✅ **todas** (filtros entrenador/cliente) | ✅ **solo las registradas por él** (`registered_by = yo`) |
| Ver torno / listado por fecha | ✅ | ❌ |
| Ver fichaje laboral del staff (calendario Personal) | ✅ `asistencia.ver` | ✅ solo el suyo ("Mi trabajo") |
| Marcar entrada/salida laboral propia | ❌ | ✅ `asistencia.registrar` |
| Solicitar corrección de un registro | ❌ | ✅ solo registros suyos (`registered_by = yo`) |
| Ver solicitudes de corrección | ✅ `asistencia.aprobar` | ❌ |
| Aprobar / rechazar solicitudes | ✅ `asistencia.aprobar` | ❌ (no puede aprobarse a sí mismo) |
| Editar horas directamente | ❌ (solo vía aprobación de solicitud) | ❌ |
| Borrar registros | — (torno: no hay borrado; anulación no pedida) | ✅ solo su fichaje laboral (`destroy`) |
| Reportes de asistencia | ✅ `reportes.ver` / `reportes.exportar` | ❌ (reportes son de admin; el dashboard del entrenador ya suma lo suyo) |
| Seleccionar sede | ✅ todas (según `sedes.ver-todas`) | ✅ solo su sede (selector aparece si hay >1 disponible) |

> Nota de seguridad: la restricción "solo sus registros / sus clientes" se valida **en el
> controlador/servicio** (403), no solo ocultando botones.

---

## M. Reutilización

### M.1 Reutilizar tal cual

- Clases CSS `calendario__*` (`panel.css:853-954`), `.pestanas__nav`, `.modal__*`, `.tabla`,
  `.btn`, `.estado`, tokens de color — **no se tocan**.
- Patrón de pestañas `_pestanas` (Asistencia / Configuración / Contenido web).
- Contenedor de solicitudes: `admin/personal/solicitudes.blade.php` (diseño y acciones).
- Torno y fichaje laboral: `Admin\AttendanceController`, `Entrenador\AttendanceController`
  (marcar/destroy/solicitarEdicion), `StaffAttendanceController`.
- `ReporteController::csv()` para el CSV de asistencias.
- `DemoSeeder` (patrón de generación), `GymContext`, `BelongsToGym`.

### M.2 Refactorizar

- **Extraer `<x-calendario>`** de la duplicación Personal/Actividad (único calendario de verdad).
- **`AttendanceEditRequest`** generalizada (soporta cliente + staff).
- **`AsistenciaService`** para la regla de negocio de registro/corrección (evita duplicar
  la validación entre torno y entrenador).
- **`SedeActivaController::store`** validado.

### M.3 No duplicar / evitar

- No crear un tercer tipo de calendario ni una librería (FullCalendar, etc.) — fuera de la
  filosofía del proyecto.
- No añadir columna "tipo" a `attendances` (el tipo se deriva de la tabla).
- No crear rutas paralelas "de entrenador" para ver todas las asistencias (fuga de datos):
  el alcance del entrenador es `registered_by = yo`.

---

## N. Orden de implementación (10 tareas)

> Cada tarea termina con su comprobación (`php artisan route:list`, tinker o navegación con
> datos demo) antes de pasar a la siguiente. `npm run build` al tocar CSS/JS.

### T1 — Migraciones de base
- **Objetivo:** habilitar correcciones de clientes y consultas por registrador.
- **Archivos:** `add_attendance_id_to_attendance_edit_requests`, `add_indexes_to_attendances`.
- **Cambio:** ver sección I.2.
- **Dependencias:** ninguna.
- **Criterio:** `php artisan migrate` sin error; en tinker `attendance_edit_requests` acepta
  `attendance_id` y rechaza ambos nulos (constraint).

### T2 — Servicio `AsistenciaService`
- **Objetivo:** regla de negocio única de registro/corrección.
- **Archivos:** `app/Services/AsistenciaService.php` (nuevo).
- **Cambio:** ver H.2. Sin tocar controladores todavía (solo el servicio + sus métodos).
- **Dependencias:** T1 (por `solicitarCorreccion` con `attendance_id`).
- **Criterio:** en tinker, `registrarEntrada` de un socio válido crea la fila con
  `method=manual`, `registered_by` correcto y `gym_id` del contexto; duplicado abierto → excepción.

### T3 — Componente `<x-calendario>` + refactor de Personal y Actividad
- **Objetivo:** un solo calendario.
- **Archivos:** `resources/views/components/calendario.blade.php` (nuevo),
  `admin/personal/index.blade.php`, `admin/actividad/calendario.blade.php`.
- **Cambio:** extraer nav+cuadrícula+modal al componente con slot `#dia`; refactor sin
  cambios de lógica en los controladores.
- **Dependencias:** ninguna.
- **Criterio:** Personal y Actividad se ven **idénticas** antes/después (comparar en navegador);
  navegar mes y abrir modal de día funciona en ambas.

### T4 — Admin: calendario de asistencias de clientes
- **Objetivo:** el contenido principal del módulo.
- **Archivos:** `Admin\AttendanceController` (+`calendario`), `routes/admin.php`,
  `admin/asistencia/calendario.blade.php` (nuevo), `admin/asistencia/_pestanas.blade.php`
  (Calendario·Torno·Personal), `admin/asistencia/index.blade.php` (pasa a pestaña Torno,
  sin cambiar), `panel-nav.blade.php` (enlace → `admin.asistencia.calendario`).
- **Cambio:** ver D.3/E.1/H.4. Añadir `permiso:asistencia.ver` a los GET de asistencias y
  `permiso:asistencia.registrar` al POST del torno.
- **Dependencias:** T3 (componente), T2 (para entrar/salir si se delega).
- **Criterio:** el calendario muestra el mes con asistencias; filtros entrenador/cliente
  funcionan; en "todas las sedes" sale la insignia de sede; el torno sigue igual en su pestaña.

### T5 — Entrenador: calendario + registrar asistencia de cliente
- **Objetivo:** el flujo que falta.
- **Archivos:** `Entrenador\AttendanceController` (`calendario`, `registrarEntrada`,
  `marcarSalida`, `solicitarEdicionCliente`, `buscarClientes`), `routes/entrenador.php`,
  `entrenador/asistencia/index.blade.php`, `entrenador/asistencia/mi-marcacion.blade.php`,
  `entrenador/asistencia/_pestanas.blade.php`.
- **Cambio:** ver D.2/E.2/H.3/H.4. Protección 403 con `Trainer::activeMembers()`.
- **Dependencias:** T2, T3.
- **Criterio:** el entrenador registra la entrada de un socio suyo → aparece en su calendario
  del día y en el calendario del admin; si el socio no está a su cargo → 403; socio ya dentro → error.

### T6 — Solicitudes de corrección unificadas
- **Objetivo:** correcciones de clientes + staff en un solo contenedor bajo Asistencias.
- **Archivos:** `AttendanceEditRequest` (+`attendance`, `historial`, `objetivo`),
  `AttendanceEditRequestController`, `admin/asistencia/solicitudes.blade.php` (nuevo),
  `admin/asistencia/calendario.blade.php` (botón con contador), `layouts/panel.blade.php`
  (campanita → rutas nuevas), rutas `admin.asistencia.solicitudes.*`.
- **Cambio:** ver D.4/G/H. Reusar diseño actual + Estado/Fecha/Sede/pestañas.
- **Dependencias:** T1, T4, T5.
- **Criterio:** una solicitud de corrección de asistencia de cliente llega pendiente, se
  aprueba y aplica horas al `Attendance`; el historial muestra aprobadas/rechazadas; la
  campanita sigue funcionando.

### T7 — `SedeActivaController::store` validado
- **Objetivo:** cerrar B8 (deuda del plan de reestructuración).
- **Archivos:** `app/Http/Controllers/SedeActivaController.php`.
- **Cambio:** validar `sede_id` contra `sedesDisponibles()`; `'todas'` solo con permiso; 422/redirect con error si no corresponde.
- **Dependencias:** ninguna (independiente).
- **Criterio:** una sede ajena no se escribe en sesión; en tinker, el calendario con una
  sede inválida queda imposible.

### T8 — Reportes de asistencia
- **Objetivo:** el admin exporta/consulta asistencias.
- **Archivos:** `Admin\ReporteController` (+`asistenciaCsv`, `asistenciaImprimir`),
  `admin/reportes/imprimir-asistencias.blade.php` (nuevo), rutas `reportes.asistencia.*`,
  enlace en `admin/reportes/index.blade.php`.
- **Cambio:** ver H.3. Columnas: fecha, hora entrada/salida, cliente, entrenador, sede, método.
- **Dependencias:** ninguna (lee `Attendance`).
- **Criterio:** CSV descarga con BOM UTF-8 y columnas correctas; impresión renderiza; filtros
  `desde/hasta`, entrenador, cliente y sede funcionan.

### T9 — Datos demo
- **Objetivo:** los calendarios y solicitudes no queden vacíos.
- **Archivos:** `database/seeders/DemoSeeder.php`.
- **Cambio:** generar `staff_attendances` de entrenadores (semanas recientes), algunas
  `attendances` de clientes con `registered_by` de un entrenador y `method=manual`, y 2-3
  `attendance_edit_requests` (1 pendiente, 1 aprobada) sobre ambos tipos.
- **Dependencias:** T1.
- **Criterio:** `php artisan migrate:fresh --seed` termina; con la cuenta `kevin@spartagym.pe`
  el calendario del entrenador y las solicitudes del admin muestran datos.

### T10 — Documentación y verificación final
- **Objetivo:** coherencia de docs y cierre.
- **Archivos:** `docs/gestion-personal.md`, `docs/estado-mejoras-panel.md`, `AGENTS.md`
  (tabla de estado), este documento.
- **Cambio:** actualizar el estado (Asistencia de clientes: completa; fichaje laboral: pestaña
  "Mi trabajo").
- **Dependencias:** T1–T9.
- **Criterio:** `route:list` sin rutas muertas; recorrer los 3 paneles; `npm run build` limpio.

---

## O. Pruebas

> `php artisan test` está roto de base (SQLite `no such table: gyms`) — verificación manual
> con datos demo. Si algún día se arregla `phpunit.xml`, las pruebas de esta sección se
> pueden portar a Feature tests.

Preparación: `php artisan migrate:fresh --seed` y `npm run dev` (o `build`).

1. **Registro de asistencia (torno).** Entrar a Asistencia→Torno como admin, registrar a un
   socio con código → aparece en la tabla del día; repetir → marca salida; socio sin membresía → error.
2. **Registro por entrenador.** Como `kevin@spartagym.pe`: Asistencias → Registrar → buscar
   un socio suyo → registrar → toast + celda marcada en su calendario.
3. **Persistencia y sede.** La fila creada en tinker tiene `gym_id` del contexto,
   `registered_by` del entrenador y `method=manual`. Cambiar a "Todas las sedes" → el item
   del día del admin muestra la insignia de sede.
4. **Calendario.** Mes anterior/siguiente navega sin romper; un día sin asistencias se ve
   vacío sin error; modal del día lista items.
5. **Filtros admin.** Por entrenador → solo sus registros; por cliente → solo el suyo;
   combinados; en "todas las sedes" → consolidado con insignias.
6. **Permisos.** Un entrenador no puede abrir `/panel/asistencia/calendario` ni las
   solicitudes (403); recepción no puede aprobar (no tiene `asistencia.aprobar`).
7. **Correcciones.** Entrenador pide corrección de una asistencia suya → pendiente → admin
   aprueba → las horas cambian en el registro real y la solicitud pasa a aprobada (historial).
   Aprobar una ya revisada → 422. Pedir corrección sobre un registro ajeno → 403.
8. **Aprobación/rechazo de fichaje laboral (regresión).** El flujo actual de `mi-marcacion`
   (marcar/eliminar/solicitar edición de horas) sigue funcionando desde su pestaña.
9. **Reportes.** Descargar `asistencia.csv` con filtros → abre en Excel sin tildes rotas;
   impresión renderiza.
10. **Responsive.** Calendario en móvil (media query `panel.css:908-910`) sin romper celdas;
    menú lateral comprimido no oculta el botón de Solicitudes.

---

## P. Criterios de aceptación finales

- [ ] El entrenador **puede registrar la asistencia de un cliente suyo** en ≤3 clics y verla
      al instante en su calendario (`registered_by = él`).
- [ ] **Asistencias = calendario** como contenido principal, tanto para admin como entrenador;
      las solicitudes de corrección son un botón/complemento, no el contenido.
- [ ] El admin ve **todas** las asistencias con filtros (mes, entrenador, cliente) y el filtro
      de sede por el selector global; en "todas las sedes" cada registro muestra su sede.
- [ ] No hay dos calendarios duplicados: Personal, Actividad y Asistencias usan `<x-calendario>`.
- [ ] `asistencia.ver` / `asistencia.registrar` / `asistencia.aprobar` se comprueban en
      **backend** en todas las rutas de asistencias (no solo ocultando botones).
- [ ] Protecciones: no registrar cliente inactivo / sin membresía / ya dentro; no registrar
      fuera de la sede (auto-relleno `gym_id`); no pedir corrección de registros ajenos (403);
      no aprobar solicitudes ya revisadas (422); fechas siempre `now()` del servidor.
- [ ] Las correcciones funcionan para **asistencia de clientes** y **fichaje laboral** en un
      solo contenedor, con historial (pendiente/aprobada/rechazada) y campanita operativa.
- [ ] Existen reportes de asistencia (CSV + impresión) con filtros de período, sede, entrenador
      y cliente, y reusan `ReporteController::csv()`.
- [ ] `SedeActivaController::store` rechaza sedes ajenas (deuda B8 cerrada).
- [ ] `migrate:fresh --seed` regenera datos demo con asistencias del entrenador y solicitudes
      (el módulo no se ve vacío).
- [ ] Sin regresiones: el torno, el fichaje laboral, Personal y Actividad siguen funcionando;
      `php artisan route:list` sin rutas muertas; `npm run build` limpio.

---

## Referencias clave verificadas

- `app/Models/Attendance.php` · `app/Models/StaffAttendance.php` · `app/Models/AttendanceEditRequest.php`
- `app/Models/Concerns/BelongsToGym.php` · `app/Models/Member.php` · `app/Models/Trainer.php` · `app/Models/User.php`
- `app/Http/Controllers/Admin/AttendanceController.php` · `app/Http/Controllers/Admin/StaffAttendanceController.php`
- `app/Http/Controllers/Admin/AttendanceEditRequestController.php` · `app/Http/Controllers/Admin/ActividadController.php`
- `app/Http/Controllers/Entrenador/AttendanceController.php` · `app/Http/Controllers/SedeActivaController.php`
- `routes/admin.php` · `routes/entrenador.php` · `resources/views/admin/asistencia/*` ·
  `resources/views/admin/personal/*` · `resources/views/admin/actividad/calendario.blade.php` ·
  `resources/views/entrenador/asistencia/index.blade.php`
- `database/migrations/2026_08_03_000105…` · `…185901_create_staff_attendances…` ·
  `…185902_alter_attendance_edit_requests_to_staff…`
- `database/seeders/RolePermissionSeeder.php` · `database/seeders/DemoSeeder.php`
