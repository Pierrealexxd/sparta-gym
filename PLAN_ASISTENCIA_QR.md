# PLAN DE IMPLEMENTACIÓN — Marcación de asistencia de entrenadores mediante QR

> **Fecha:** 2026-08-11 · **Modo:** análisis → plan (no implementación) · **Regla:** no rehacer el sistema existente.
>
> Este documento es el resultado de leer el módulo de asistencias de punta a punta
> (rutas, controladores, servicios, modelos, migraciones, vistas, permisos, seeders)
> y la propuesta técnica para que otro agente lo ejecute **después de que este plan sea aprobado**.
> Nada de lo aquí descrito está implementado todavía.

---

## 1. Resumen de la solución

La marcación por QR es una **segunda vía de entrada al fichaje laboral ya existente**,
no un sistema paralelo.

El entrenador sigue marcando entrada/salida en el mismo lugar de siempre
(`entrenador.asistencia.mi-marcacion`) y con la **misma regla de negocio** (`AsistenciaService`
+ toggle de `StaffAttendance`). La única diferencia es *cómo se inicia* la marcación:

| | Marcación manual | Marcación QR |
|---|---|---|
| Quién inicia | Formulario con turno | Botón "Escanear QR" → cámara |
| Qué se envía | `turno` | `token` del QR (+ `turno` opcional) |
| Quién decide entrada/salida | El backend (toggle sobre `StaffAttendance`) | El backend (idéntico) |
| Dónde se registra | `staff_attendances` | `staff_attendances` (la misma tabla) |
| Corrección | `AttendanceEditRequest` | `AttendanceEditRequest` (sin cambios) |

Piezas nuevas mínimas:

1. **`gym_qr_codes`** — tabla de códigos QR por sucursal (token UUID único, rotable e invalidable).
2. **`staff_attendances.method`** — columna `enum('manual','qr')` para auditar el origen (recomendada).
3. **Endpoint de escaneo** `POST entrenador.asistencia.qr` → resuelve token → sucursal → toggle → registra.
4. **Escáner frontend** (modal + cámara) sobre `mi-marcacion`.
5. **Generador de QR del admin** dentro de Sedes (con modal de visualización/impresión).
6. **Pestaña "Personal" en el módulo Asistencia del admin** (para que el admin vea las
   marcaciones laborales — hoy solo ve las de clientes; ver §2.3).

---

## 2. Análisis de la arquitectura actual (verificado en código)

### 2.1 Hay dos asistencias distintas, separadas a propósito

| Concepto | Modelo / tabla | Para quién | Quién registra hoy |
|---|---|---|---|
| **Asistencia de clientes (torno)** | `Attendance` / `attendances` | Socios que entran a entrenar | Admin/recepción (`Admin\AttendanceController`) y entrenador para sus asignados |
| **Asistencia laboral del staff** | `StaffAttendance` / `staff_attendances` | Entrada/salida de trabajo del entrenador | El propio entrenador (`Entrenador\AttendanceController::marcar`) |
| **Solicitudes de corrección** | `AttendanceEditRequest` / `attendance_edit_requests` | Corrección de horas (de ambos tipos) | Entrenador solicita; admin aprueba/rechaza |

Están documentadas en el propio código como "dos cosas separadas a propósito"
(`app/Models/StaffAttendance.php:11-15`). **La marcación QR del entrenador es asistencia
LABORAL → pertenece a `StaffAttendance`, no a `Attendance`.**

### 2.2 `staff_attendances` — el fichaje laboral (lo que el QR tocará)

- **Esquema** (`database/migrations/2026_08_08_185901_create_staff_attendances_table.php`):
  `gym_id` (FK), `user_id` (FK), `clocked_in_at` (dateTime), `clocked_out_at` (nullable),
  `turno` enum `manana|tarde|doble` (default `manana`), timestamps.
  Índices: `(gym_id, clocked_in_at)` y `(user_id, clocked_in_at)`.
  **No tiene columna `method`** (a diferencia de `attendances`, que sí la tiene).
- **Modelo** (`app/Models/StaffAttendance.php`): `BelongsToGym`; relaciones `user()`,
  `editRequests()`; scope `dentro()` (`whereNull('clocked_out_at')`); atributo `turno_legible`.
- **Registro actual** (`app/Http/Controllers/Entrenador/AttendanceController.php:125-144`,
  método `marcar()`): **toggle puro** —
  - si existe una marcación abierta (`dentro()`) → la cierra (`clocked_out_at = now()`);
  - si no → valida `turno` (`required|in:manana,tarde,doble`) y crea la entrada
    (`clocked_in_at = now()`, `user_id` = usuario autenticado).
- **Vista** `entrenador/asistencia/mi-marcacion.blade.php`: tarjeta de marcar (turno o salida,
  según `$abierta`), tabla/calendario del mes, editar (solicitud) y borrar propio (reversible).

**Conclusiones sobre el toggle:**
- Dos entradas consecutivas → **ya imposible** (la segunda cierra la abierta).
- Dos salidas consecutivas → **ya imposible** (sin abierta, la marcación crea entrada).
- Salida sin entrada previa → **ya imposible** (no hay nada que cerrar).
- La regla "sin entrada abierta → ENTRADA; con entrada abierta → SALIDA" **es exactamente
  la lógica actual**. El QR la reutiliza tal cual, sin duplicarla.

**Huecos que el plan sí debe cubrir (no existen hoy):**
- **Marcaciones muy seguidas:** dos escaneos rápidos del mismo QR producen entrada→salida
  casi instantánea (un turno de 1 segundo). Falta un *anti-doble-escaneo* (ver §12 y §8).
- **Abierta de un día anterior:** si el entrenador no cerró ayer, `dentro()` encuentra la de
  ayer y hoy la marcaría cerrando el registro de ayer (escribiendo la hora de hoy en la fila
  de ayer). Conviene tratar las abiertas viejas como "no estoy en turno hoy" (ver §12).

### 2.3 El módulo de Asistencia del admin (dónde "debe aparecer" la marcación QR)

`Admin\AttendanceController::calendario()` (`app/Http/Controllers/Admin/AttendanceController.php:32-91`)
consulta **`Attendance`** (asistencias de CLIENTES): calendario del mes, filtros por entrenador
(quien registró) y por cliente, modal de torno (`entrar()` acepta `code | qr_token | document`),
con `_pestanas` de Calendario / Solicitudes de corrección.

**Dato clave que hay que corregir en el entendimiento:** el admin **hoy NO tiene vista de las
marcaciones laborales del staff** (`staff_attendances`). Solo las alcanza indirectamente a
través de las *solicitudes de corrección*. Por tanto, "la marcación QR aparece automáticamente
en el calendario del admin" **no se cumple hoy** para el fichaje laboral: el calendario actual
es de clientes.

Para satisfacer el requisito, el plan añade la **pestaña "Personal"** al módulo Asistencia del
admin (calendario de `staff_attendances`, reutilizando `<x-calendario>`; era un componente ya
esbozado en `docs/plan-modulo-asistencias.md`, sección E.1). Con eso, cada marcación QR aparece
en el mismo módulo, con su método visible.

### 2.4 Correcciones — flujo existente (se reutiliza sin tocar esquema)

`AttendanceEditRequest` ya apunta a `staff_attendances` vía `staff_attendance_id`
(restricción `CHECK` garantiza `attendance_id` XOR `staff_attendance_id`).
`AsistenciaService::solicitarCorreccion()` bloquea si ya hay una pendiente del mismo registro,
y `aprobar()`/`rechazar()` la procesan. **El entrenador no puede editar horas directo** — es
exactamente la regla pedida. Una marcación QR es un `StaffAttendance` más: **todo funciona sin
cambios** (solo se recomienda mostrar el método en la vista de solicitudes, §7).

### 2.5 Multi-sucursal: la regla que condiciona todo

- `BelongsToGym` (`app/Models/Concerns/BelongsToGym.php`) aplica un *global scope* por `gym_id`
  y **rellena `gym_id` solo al crear** (si viene vacío).
- El gym activo lo resuelve `GymContext` (hoy por `config('sparta.gym_slug')`; en el panel, por
  el selector de sede de la cabecera vía `EstablecerSedeActiva` → `sede_activa_id` en sesión).
- `Gym` y `Exercise` **no** usan el trait a propósito (raíz del aislamiento y biblioteca compartida).

**Implicación crítica para el QR:** la sucursal la decide el **QR** (el token), no la sesión.
Si un entrenador escanea el QR de la sucursal B con su sede activa puesta en A:
1. la búsqueda del token NO puede usar el global scope (filtraría por A y no encontraría el QR de B);
2. al crear la `StaffAttendance` hay que **forzar `gym_id = B`** explícitamente (el hook de
   `BelongsToGym` respeta un `gym_id` ya presente, así que el registro queda en B y no en A).

En el caso de una sola sucursal (el actual), ambos coinciden y no hay diferencia visible.

### 2.6 Generación de QR ya existente (se reutiliza)

- Dependencia npm **`qrcode`** ya instalada (`package.json`).
- `resources/js/qr.js` pinta `canvas[data-qr]` con el valor y colores del token CSS; se usa en el
  carnet (`admin/clientes/carnet.blade.php`). **El QR de sucursal se genera con el mismo mecanismo**
  (canvas + `iniciarQr()`), sin nuevas dependencias para *generar*.
- `Member.qr_token` es un UUID (v4) generado al crear — el mismo patrón de "token = capacidad"
  que se propone para la sucursal (ver §6).

### 2.7 Permisos y middleware (todo comprobado en backend, no solo en la UI)

- `rol` (`EnsureRole`) y `permiso` (`EnsurePermission`) como middleware de ruta
  (`bootstrap/app.php:14-18`).
- Permisos de asistencia (`database/seeders/RolePermissionSeeder.php:35-39`):
  `asistencia.ver`, `asistencia.registrar`, `asistencia.aprobar`. Entrenador tiene `ver`+`registrar`.
- Permiso de sedes: `sedes.gestionar` (administrar sedes), `sedes.ver-todas` (selector global).
  El admin no se enumera (puede todo).
- `attendance_edit_requests` y `staff_attendances` llevan `gym_id` (aislamiento por sede).

### 2.8 Hora y zona horaria

- `config/app.php` `timezone = 'UTC'`. El sistema entero marca con `now()` (servidor) y no hay
  override en `AppServiceProvider`. `gyms.timezone` existe (default `America/Lima`) pero hoy solo
  se usa para `Gym::horarioDeHoy()`.
- **Decisión del plan:** la marcación QR usa `now()` igual que el flujo manual (misma fuente de
  verdad, sin riesgo de desincronizar). La zona horaria de *visualización* es un tema aparte que
  afecta a todo el sistema y **queda fuera de alcance** (se anota como riesgo, §18).

### 2.9 Estado de las pruebas

`php artisan test` está roto de base (SQLite sin tablas) según `docs/plan-modulo-asistencias.md:735`.
La verificación se hace con datos demo y recorrido manual (`migrate:fresh --seed`, `npm run build`).

---

## 3. Componentes existentes que se reutilizan (sin cambios o con cambios menores)

| Componente | Dónde | Uso en el plan |
|---|---|---|
| `StaffAttendance` + `staff_attendances` | Modelo/tabla | **Tabla destino del QR** (misma fila, mismo toggle) |
| `Entrenador\AttendanceController::marcar()` y `miMarcacion()` | Controlador/vista | Base del flujo; el QR delega en la **misma lógica** (extraída al servicio, ver §5) |
| `AsistenciaService` | `app/Services/AsistenciaService.php` | Regla de negocio única. Se le **añade** `marcarStaff()` (o equivalente) para no duplicar el toggle |
| `AttendanceEditRequest` + `AttendanceEditRequestController` | Modelo/controlador | Correcciones de marcaciones QR **sin cambios de esquema** |
| `AttendanceEditRequestController::pendientesJson` | Campanita | Sin cambios |
| `<x-calendario>` | `resources/views/components/calendario.blade.php` | Nueva pestaña "Personal" del admin |
| `qrcode` + `resources/js/qr.js` | npm / JS | Genera el QR de sucursal (canvas `data-qr`) |
| Patrón de modal `.modal__fondo/.modal__caja` + `x-cloak` | CSS/Blade | Modal de escaneo y modal de QR del admin |
| Patrón de pestañas `_pestanas` | Admin y entrenador | Nueva pestaña "Personal" (admin) |
| Patrón de vista de impresión autónoma (carnet) | `admin/clientes/carnet.blade.php` | Impresión del QR de sucursal |
| `GymContext` + `BelongsToGym` | Multi-sede | Para forzar `gym_id` del QR y explicitar `sinFiltroDeGimnasio()` en la búsqueda del token |
| Tokens CSS + `components.css` (`.btn`, `.tarjeta`, `.estado`) | Frontend | Sin tocar |
| `@vite` + `iniciar()` por módulo | `resources/js/app.js` | Registro del nuevo módulo de escaneo |

## 4. Componentes nuevos necesarios

### Backend
- `app/Models/GymQrCode.php` — token de QR por sucursal (ver §5.1).
- `app/Http/Controllers/Admin/GymQrCodeController.php` — generar/regenerar/mostrar el QR.
- (Opcional) `App\Support\AsistenciaQrService` o métodos nuevos en `AsistenciaService` — resolución
  del token y toggle con las guardas nuevas.

### Frontend
- `resources/js/escaneo-qr.js` — módulo de cámara: `getUserMedia` + bucle de detección
  (`BarcodeDetector` nativo → fallback `jsQR`) + estados de permisos/errores.
- `resources/views/entrenador/asistencia/_escaneo-qr.blade.php` — modal de escaneo (Alpine).
- `resources/views/admin/sedes/qr.blade.php` — modal con el QR de la sucursal + regenerar.
- `resources/views/admin/sedes/qr-imprimir.blade.php` — vista autónoma de impresión (como el carnet).
- `resources/views/admin/asistencia/personal.blade.php` — calendario de marcaciones laborales
  (pestaña "Personal").
- `resources/js/carnet.js` se deja intacto; el QR de sucursal se pinta con el `iniciarQr()` de `app.js`.

### Dependencia npm
- **`jsqr`** (lectura/decodificación) — única dependencia nueva obligatoria (ver §16).

---

## 5. Cambios de backend

### 5.1 Modelo `GymQrCode` + tabla `gym_qr_codes` (NUEVO)

Identifica de forma segura una sucursal para el escaneo. Decisiones (ver §6 para el "por qué"):

```
gym_qr_codes
├─ id            PK
├─ gym_id        FK gyms (cascade)      → la sucursal que representa
├─ token         string(36) UNIQUE      → UUID v4, generado en el servidor; es el payload del QR
├─ label         string(120) nullable   → ej. "QR de la recepción"
├─ is_active     bool default true      → un QR revocado se apaga sin borrar (auditoría)
├─ created_by    FK users nullable      → quién lo generó
├─ revoked_at    timestamp nullable     → para rotar sin perder el histórico
└─ timestamps
Índices: UNIQUE(token), index(gym_id, is_active)
```

- **No usa `BelongsToGym`** (igual que `Gym` y `Exercise`): la búsqueda del token es **global por
  diseño** (un entrenador con sede activa A debe poder escanear el QR de la sede B). El aislamiento
  en listados del admin se hace con `where('gym_id', $id)` explícito, visible en el código.
- Relación `Gym::qrCodes(): HasMany` y `GymQrCode::gym(): BelongsTo`.
- Hook `creating` (como `Member::qr_token`): si no hay token, se genera `Str::uuid()`.

### 5.2 Migraciones (ver §7)

1. `create_gym_qr_codes_table` (nueva).
2. `add_method_to_staff_attendances` — `enum('manual','qr')` NOT NULL default `'manual'`.

### 5.3 `AsistenciaService` — nuevo método de fichaje laboral

Nuevo método (la regla única que usan manual **y** QR):

```
marcarStaff(User $usuario, string $turno, ?int $gymId = null, bool $porQr = false): array
  # 1. Guarda anti-doble-escaneo (solo por QR, ver §8/§12):
  #    si la última marcación del usuario fue hace < 30s → ValidationException
  #    "Marcación demasiado reciente. Espera unos segundos."
  # 2. Abierta de hoy (dentro() y clocked_in_at es de hoy):
  #    → SALIDA: cierra con now() y devuelve ['tipo' => 'salida', 'marcacion' => $abierta]
  # 3. Abierta de otro día (dentro() pero no de hoy):
  #    → NO se cierra (quedaría para corrección/borrado). Se trata como "no estoy en turno".
  # 4. Sin abierta utilizable:
  #    → ENTRADA: valida turno, crea con clocked_in_at = now(),
  #      gym_id = $gymId ?? (contexto), method = $porQr ? 'qr' : 'manual',
  #      y devuelve ['tipo' => 'entrada', 'marcacion' => $marcacion]
```

- `gym_id` explícito cuando viene del QR (la sucursal la manda el token, no la sesión); cuando es
  manual se deja vacío y `BelongsToGym` lo rellena con el contexto (comportamiento actual intacto).
- `marcar()` (manual) pasa a delegar aquí → **manual y QR comparten 100 % la validación y el registro**.
- `turno` se valida en este método (`in:manana,tarde,doble`), igual que hoy.

### 5.4 Controladores

**`Entrenador\AttendanceController`** — se le añaden:
- `estado(Request): JsonResponse` — devuelve `{ abierta: bool, horaEntrada: 'H:i'|null }` para que
  el modal muestre "Escanear para salir" o "Escanear para entrar (+ turno)". **Es solo UX: el tipo
  final lo decide el backend en el POST.**
- `marcarPorQr(Request): JsonResponse` — valida `token` (formato UUID), resuelve el QR
  (`GymQrCode::where('token', ...)` **sin filtro de sede**, `is_active` y `revoked_at IS NULL`,
  gym activo), toma `turno` opcional del body, llama a `AsistenciaService::marcarStaff()` con
  `gymId` del QR, devuelve JSON `{ ok, tipo, hora, sede, turno }` o un error 422 con mensaje legible.
- `marcar()` (manual) → delega en `marcarStaff()` (mismo comportamiento, sin `gymId` y sin QR).

**`Admin\GymQrCodeController`** (nuevo), bajo `rol:admin,recepcion` + `permiso:sedes.gestionar`:
- `mostrar(Gym $sede): View` — renderiza el modal con el QR activo (o "generar" si no existe).
- `generar(Gym $sede): RedirectResponse` — revoca el QR activo anterior (`is_active=false`,
  `revoked_at=now()`) y crea uno nuevo. Confirmación explícita al regenerar (cambio de token).
- El QR se pinta con `canvas[data-qr="<token>"]` (reutiliza `qr.js`).

**`Admin\AttendanceController`** — se le añade:
- `personal(Request): View` — calendario de `staff_attendances` del mes (reutiliza el patrón
  `calendario()`: mes/anio, `modoTodas`, insignia de sede, método visible). Nueva pestaña "Personal".

### 5.5 No cambian
- `AttendanceEditRequestController`, `AsistenciaService::solicitarCorreccion/aprobar/rechazar`,
  el torno de clientes (`entrar`/`salir`), `GymController`, permisos, `RolePermissionSeeder`.

---

## 6. QR de la sucursal — estrategia de identificación segura

**Recomendación: token UUID v4 por sucursal, no el `gym_id`.**

Por qué:
- **Un `gym_id` incremental es adivinable y manipulable.** Cualquiera podría probar ids y simular
  estar en otra sucursal, y un id en el QR no prueba nada por sí mismo.
- **Un UUID (122 bits de entropía) es una *capability***: el QR vale porque es materialmente
  imposible de adivinar. Es el mismo modelo que ya usa el proyecto para `Member.qr_token`
  (`app/Models/Member.php:40-42`) y que `AttendanceController::entrar()` reconoce.
- El **payload del QR es solo el token** (un string corto). No lleva nombre de sucursal, datos
  de contacto, ni nada sensible: el QR impreso no filtra información si se pierde o fotografía.
- La sucursal se resuelve **siempre en el backend** por `token → gym_qr_codes → gym`. El frontend
  jamás manda `gym_id` ni el nombre de la sede.
- **Rotación:** regenerar = revocar el token activo (`is_active=false`, `revoked_at`) y emitir uno
  nuevo. El QR físico anterior deja de valer al instante. El histórico queda auditado (`created_by`,
  fechas) sin borrar nada.

Formato del payload: el token desnudo (`4f3a…-…`). Se valida con `Str::isUuid()` en el backend
antes de consultar (filtro barato contra ruido).

---

## 7. Cambios de base de datos

### 7.1 Migraciones (2 nuevas)

**M1 — `create_gym_qr_codes_table`** (ver esquema en §5.1). Sin backfill (tabla nueva).

**M2 — `add_method_to_staff_attendances`**
```php
$table->enum('method', ['manual', 'qr'])->default('manual')->after('turno');
```
- Las filas existentes quedan como `manual` (que es lo que eran).
- Índice nuevo: ninguno imprescindible; si el reporte por método se vuelve frecuente, se añade
  `(gym_id, method)` más adelante.

### 7.2 Tablas y campos existentes que se aprovechan (sin cambios)

| Tabla | Uso | Estado |
|---|---|---|
| `staff_attendances` | Registro de la marcación (manual y QR) | Existe; + `method` |
| `attendance_edit_requests` | Correcciones de ambas | Existe; sin cambios |
| `gyms` | Sucursal resuelta por el token | Existe; sin cambios |
| `users` | `user_id` / `created_by` / `requested_by` | Existe; sin cambios |

### 7.3 Soporte del esquema para múltiples períodos diarios (07:00→13:00→14:00→18:00)

`staff_attendances` **sí lo soporta tal cual**: cada entrada es una fila con
`clocked_in_at` + `clocked_out_at`, y nada impide varias filas el mismo día (los índices no son
únicos). El toggle actual produce exactamente esa secuencia: 07:00 crea fila A abierta → 13:00
cierra A → 14:00 crea fila B abierta → 18:00 cierra B. **No hace falta cambiar nada.**

Única mejora opcional de consulta: el índice existente `(user_id, clocked_in_at)` ya cubre
"última marcación del usuario" y "marcaciones del mes".

---

## 8. Endpoints necesarios

### Admin (todas bajo `rol:admin,recepcion`)
```
GET  admin/sedes/{sede}/qr                    → admin.sedes.qr            [permiso:sedes.gestionar]
POST admin/sedes/{sede}/qr/regenerar          → admin.sedes.qr.regenerar  [permiso:sedes.gestionar]
GET  admin/sedes/{sede}/qr/imprimir           → admin.sedes.qr.imprimir   [permiso:sedes.gestionar]
GET  admin/asistencia/personal                → admin.asistencia.personal [permiso:asistencia.ver]
```
> Las rutas de QR viven dentro del `Route::resource('sedes', ...)` existente (el recurso ya está
> acotado a `permiso:sedes.gestionar`), como acciones adicionales del mismo controlador
> `GymQrCodeController` o métodos del `GymController`. Se recomienda un controlador propio
> `GymQrCodeController` para no engordar `GymController`.

### Entrenador (bajo `permiso:asistencia.registrar`)
```
GET  entrenador/asistencia/estado   → entrenador.asistencia.estado  [permiso:asistencia.ver]
POST entrenador/asistencia/qr       → entrenador.asistencia.qr      [permiso:asistencia.registrar]
```
- El POST de escaneo lleva `throttle` (p. ej. `throttle:20,1`) además de la guarda de 30 s del
  servicio, contra abuso.
- **Orden de rutas:** `estado` y `qr` son rutas de texto → deben ir **antes** de las rutas
  `{attendance}`/`{marcacion}` del mismo prefijo (patrón ya usado en `routes/entrenador.php`).

---

## 9. Flujo del administrador

1. Admin abre **Configuración → Sedes** (`admin.sedes.index`).
2. En la fila de una sucursal pulsa el botón **"QR de asistencia"** (icono/acción nueva por fila).
3. Se abre el modal (`admin.sedes.qr`):
   - si la sucursal **no tiene** QR activo → "Generar QR de asistencia";
   - si **ya tiene** → se muestra el QR (canvas pintado con `qr.js`), el nombre de la sucursal,
     el token legible bajo el código, y el botón **"Imprimir / Guardar como PDF"**.
4. "Regenerar" pide confirmación (modal de confirmación existente) porque **invalida el QR físico
   anterior**. Al confirmar: revoca el anterior y emite el nuevo.
5. Impresión: vista autónoma `admin.sedes.qr.imprimir` (blanco sobre negro, `window.print()`,
   mismo patrón que el carnet) para imprimir y colocar físicamente en la sucursal.

## 10. Flujo del entrenador

1. Entra a **Asistencia → Mi marcación** (`entrenador.asistencia.mi-marcacion`).
2. En la tarjeta de marcar hay **dos botones**: "Marcar entrada/salida" (manual, existente) y
   **"Escanear QR"** (nuevo).
3. "Escanear QR" abre el modal (`_escaneo-qr.blade.php`):
   - primero consulta `GET estado` → si está en turno muestra "Escanea para marcar tu **salida**"
     (sin selector de turno); si no, "Escanea para marcar tu **entrada**" + selector de turno
     (mañana/tarde/doble, 3 chips);
   - pide permiso de cámara (`getUserMedia`); con permiso, arranca el stream en un `<video>`;
   - al detectar el QR: corta la cámara, envía `POST entrenador.asistencia.qr` con `{ token, turno }`;
   - el backend resuelve token → sucursal → valida → determina entrada/salida → registra;
   - el modal muestra el resultado (hora, sede, entrada/salida) y la página recarga con el toast
     de éxito (`session('exito')`, mecanismo existente).
4. La marcación aparece en su lista/calendario de `mi-marcacion` y en la nueva pestaña "Personal"
   del admin, con su método ("QR").
5. Si se equivoca: **no edita directo** — usa los botones "Editar" (solicitud) o "Eliminar"
   (reversible) que ya existen.

## 11. Flujo de corrección (se reutiliza, sin paralelos)

1. Entrenador detecta un error en una marcación QR → pulsa "Editar" en `mi-marcacion`
   (o "Solicitar corrección").
2. `solicitarEdicion()` → `AsistenciaService::solicitarCorreccion()` → `AttendanceEditRequest`
   pendiente con `staff_attendance_id` (el objetivo es un `StaffAttendance`, sea manual o QR).
3. Admin lo ve en **Asistencia → Solicitudes de corrección** (o la campanita) → aprueba (aplica
   horas al registro real) o rechaza.
4. Ningún cambio de esquema. Única adaptación cosmética opcional: mostrar el **método** de la
   marcación (Manual/QR) en la cola, útil para auditar.

---

## 12. Validaciones

### Reglas que YA existen (el QR las hereda, no se reimplementan)
| Regla | Dónde vive hoy | ¿Qué pasa si se viola? |
|---|---|---|
| Sin entrada abierta → ENTRADA; con abierta → SALIDA | Toggle de `marcar()` + scope `dentro()` | — |
| Dos entradas / dos salidas consecutivas imposibles | Toggle | Imposible por construcción |
| Salida sin entrada previa imposible | Toggle | Imposible por construcción |
| `turno` requerido y válido (`manana|tarde|doble`) | `marcar():135` | Validación 422 |
| No hay dos solicitudes pendientes por registro | `AsistenciaService::solicitarCorreccion:59` | 422 "ya hay una solicitud pendiente" |
| Solo registros propios | `abort_unless($marcacion->user_id === auth()->id(), 403)` | 403 |
| No se aprueba una solicitud ya revisada | `aprobar():82` | 422 |
| Horas siempre del servidor (`now()`) | `AsistenciaService` | — (el frontend nunca manda horas) |
| Sucursal del registro = contexto (auto `gym_id`) | `BelongsToGym` | — |
| Rol/permiso del panel | Middleware `rol` / `permiso` | 403 |

### Reglas NUEVAS que el plan introduce
| Regla | Dónde | Comportamiento |
|---|---|---|
| **Anti-doble-escaneo:** no hay una marcación del usuario en los últimos ~30 s | `AsistenciaService::marcarStaff()` (solo al registrar por QR) | 422 "Marcación demasiado reciente. Espera unos segundos." (evita que dos escaneos rápidos creen entrada+salida en 1 s) |
| **Abierta de otro día no se cierra hoy** | `marcarStaff()` | Si `dentro()` tiene `clocked_in_at` de fecha ≠ hoy, se trata como "sin turno": se crea entrada nueva; la abierta vieja queda para corrección/borrado |
| **Token válido y activo** | `marcarPorQr()` | `GymQrCode` existe, `is_active=true`, `revoked_at IS NULL`; si no → 422 "Código QR no válido" |
| **Sucursal activa** | `marcarPorQr()` | `gym.is_active`; si no → 422 "La sucursal está desactivada" |
| **Token con formato UUID** | `marcarPorQr()` | `Str::isUuid()` antes de consultar; si no → 422 "Código QR no válido" |
| **QR de otra sucursal** | Servicio | No se "rechaza por sede ajena": el QR **define** la sede de la marcación. Si el token no está registrado, cae en "QR no válido" (mismo mensaje; no revela qué sedes existen) |
| **Sin permiso para marcar** | Middleware `permiso:asistencia.registrar` | 403 (el rol entrenador lo tiene) |
| **Cámara no disponible / sin soporte** | Frontend | Mensaje claro + la marcación manual sigue disponible (ver §14) |
| **Throttle del endpoint** | `throttle:20,1` en la ruta | 429 si se abusa |

---

## 13. Seguridad

- **El backend es la autoridad:** el frontend solo manda `{ token, turno }`. El **entrenador** sale
  de `auth()->user()`; la **sucursal** sale del token resuelto en el servidor; la **fecha/hora** es
  `now()` del servidor; la **entrada/salida** la decide el toggle del servicio. Nada de esto es
  editable desde el navegador.
- **El token es una capability** (UUID v4, inimpugnable); `gym_id` nunca viaja en el payload ni
  como parámetro.
- **Sin revelar datos sensibles:** el QR impreso solo contiene el token; los mensajes de error no
  distinguen entre "token inexistente", "revocado" o "sucursal inactiva" (mismo 422 genérico) para
  no filtrar qué sedes existen.
- **Resolución del token sin global scope** (`GymQrCode::query()` plano) — deliberado y documentado
  (§2.5): el aislamiento por sede lo aplica la propia naturaleza del modelo, no el trait.
- **CSRF** en todos los POST (los formularios del proyecto ya lo llevan).
- **El registro se escribe con `gym_id` del QR**, nunca de la sesión; si coinciden (caso normal),
  no hay diferencia observable.
- Permisos comprobados en **backend** (`permiso:asistencia.registrar` en el POST de escaneo;
  `permiso:sedes.gestionar` en la generación del QR).
- El `turno` enviado por QR se valida contra el enum; una salida no lo usa (se ignora).

---

## 14. Manejo de errores y experiencia de usuario

El modal de escaneo y el POST devuelven mensajes **legibles, en español**, sin tecnicismos:

| Situación | Mensaje propuesto | Origen |
|---|---|---|
| Cámara no soportada por el navegador | "Tu navegador no permite usar la cámara. Usa la marcación manual." | `escaneo-qr.js` |
| Permiso de cámara denegado | "No se puede acceder a la cámara. Concede el permiso en tu navegador e inténtalo de nuevo." | `getUserMedia` catch |
| Cámara bloqueada por el SO (iOS/Android) | "La cámara está bloqueada. Actívala desde los ajustes del dispositivo." | `getUserMedia` `NotAllowedError` |
| Error genérico de cámara | "No se pudo iniciar la cámara. Vuelve a intentarlo." | `getUserMedia` catch |
| QR no válido / no reconocido | "Código QR no válido." | Backend 422 (genérico, no revela nada) |
| QR deshabilitado / revocado | "Código QR no válido." (mismo mensaje genérico) | Backend |
| Sucursal inactiva | "La sucursal está desactivada." | Backend |
| Marcación demasiado reciente | "Marcación demasiado reciente. Espera unos segundos." | Backend 422 |
| Usuario sin permiso | 403 estándar del panel | Middleware |
| Error de conexión | "No se pudo conectar con el servidor. Revisa tu conexión." | `fetch` catch |
| Error del servidor | "Ocurrió un error. Inténtalo de nuevo." (5xx → mensaje genérico, se loguea) | Backend |
| Éxito (entrada) | Toast: "Entrada marcada. Buen trabajo." | `session('exito')` |
| Éxito (salida) | Toast: "Salida marcada. Buen trabajo." | `session('exito')` |

Principio UX: el modal nunca deja al usuario en blanco — siempre hay un estado visible
(pidiendo permiso → escaneando → procesando → éxito/error), y en cualquier error la **marcación
manual sigue disponible** (la cámara nunca es un cuello de botella).

---

## 15. Compatibilidad con dispositivos y navegadores

- **HTTPS es obligatorio en producción:** `getUserMedia` exige *secure context*
  (HTTPS o `localhost`). El desarrollo local con `php artisan serve` (http://localhost:8000) vale;
  cualquier despliegue real debe ir por HTTPS (ya es requisito para desplegar Laravel).
- **Android / Chrome:** `BarcodeDetector` está disponible en la mayoría de dispositivos → se usa
  como vía rápida nativa; `jsQR` como fallback si falta.
- **iPhone / Safari:** `BarcodeDetector` **no** está disponible → `jsQR` decodifica desde el
  `getUserMedia` normal. Safari pide el permiso una vez por sitio; si se rechaza, hay que
  re-habilitarlo desde Ajustes (la app no puede re-preguntar).
- **Escritorio:** funciona con cámara integrada/webcam; el flujo es idéntico.
- **Comportamiento en segundo plano:** al cerrar el modal o salir de la página se detiene el
  stream (`video.srcObject.getTracks().forEach(t => t.stop())`) para no dejar la luz de la cámara
  encendida.
- **Navegadores sin `getUserMedia`:** se detecta (`navigator.mediaDevices?.getUserMedia`) y se
  ofrece la marcación manual.
- El panel ya es responsive (menú comprimido + tarjetas); el modal de escaneo usa las clases
  `.modal__*` existentes.

---

## 16. Dependencias y librerías recomendadas

### Generar el QR (ya instalada, no tocar)
- **`qrcode`** (^1.5.4) — se reutiliza vía `resources/js/qr.js`.

### Leer el QR (nueva, solo una)
- **Recomendación: `jsqr`** (~50 KB, pura JS, sin UI, sin dependencias).
  - Por qué: encaja con la filosofía del proyecto (control total, sin librerías que inyectan HTML,
    sin Tailwind/Bootstrap), es pequeña y no acopla el modal a un widget ajeno. El bucle de cámara
    (video + `requestAnimationFrame` + `jsQR` sobre `getImageData`) es código estándar y queda
    dentro de `escaneo-qr.js`.
  - Con **`BarcodeDetector` nativo** como primera vía donde exista (más rápido y exacto) y `jsQR`
    como fallback para iOS/Safari y navegadores sin él.
- **Alternativa turnkey (si se prefiere menos código propio): `html5-qrcode`** — gestiona cámara,
  permisos y decode con una sola llamada, pero pesa ~10×, trae su propia UI y estilos, y casa peor
  con el sistema de tokens del proyecto. Se descarta salvo que se priorice velocidad de desarrollo.

> No se instala nada en esta etapa.

---

## 17. Plan de implementación por fases

> Cada fase termina con su comprobación. `npm run build` al tocar CSS/JS.

### Fase 0 — Preparación (docs)
- Confirmar este plan; actualizar `AGENTS.md` (estado) y `docs/` si aplica.
- **Criterio:** plan aprobado.

### Fase 1 — Base de datos
- Migraciones `create_gym_qr_codes_table` y `add_method_to_staff_attendances`.
- `GymQrCode` (modelo + relación en `Gym`); `StaffAttendance` gana `method` en `$fillable`.
- **Criterio:** `php artisan migrate` limpio; en tinker `gym_qr_codes` acepta filas y
  `staff_attendances.method` devuelve `manual` para las filas viejas.

### Fase 2 — Servicio y backend del fichaje
- `AsistenciaService::marcarStaff()` (anti-doble-escaneo + abierta de otro día + toggle + `method`).
- Refactor de `Entrenador\AttendanceController::marcar()` para delegar (cero regresión).
- `estado()` y `marcarPorQr()` (resolución de token sin scope de sede + validaciones + JSON).
- Rutas del entrenador (antes de `{attendance}`) + `throttle`.
- **Criterio:** en tinker, `marcarStaff` produce entrada/salida igual que antes; con token inválido
  → 422; dos llamadas en <30 s → 422.

### Fase 3 — Generación del QR (admin)
- `GymQrCodeController` (`mostrar`, `generar`, `imprimir`), rutas bajo `sedes.*`, botón por fila en
  `admin/sedes/index.blade.php`, modal `admin/sedes/qr.blade.php`, vista de impresión
  `admin/sedes/qr-imprimir.blade.php` (patrón carnet).
- **Criterio:** generar muestra el QR; imprimir renderiza; regenerar revoca el anterior (el token
  viejo deja de validar en `marcarPorQr`).

### Fase 4 — Escáner del entrenador
- Instalar `jsqr`; `resources/js/escaneo-qr.js` (permisos, stream, `BarcodeDetector`→`jsQR`,
  detener al cerrar, estados de error).
- Modal `entrenador/asistencia/_escaneo-qr.blade.php` + botón "Escanear QR" en `mi-marcacion`.
- Registro del módulo en `app.js` (patrón `iniciar()`).
- **Criterio:** con una webcam/el QR impreso en pantalla, escanear registra y el modal muestra el
  resultado; con cámara denegada, mensaje claro y marcación manual intacta.

### Fase 5 — Integración admin (pestaña "Personal")
- `Admin\AttendanceController::personal()` + ruta + `admin/asistencia/personal.blade.php` +
  pestaña en `admin/asistencia/_pestanas.blade.php` (con `permiso:asistencia.ver`).
- Mostrar `method` (Manual/QR) en el item del día y en la cola de solicitudes.
- **Criterio:** las marcaciones QR y manuales del mes se ven en el calendario del admin con su
  método y sede.

### Fase 6 — Correcciones y demo
- Verificar `solicitarEdicion`/`aprobar/rechazar` sobre marcaciones QR (sin cambios de código).
- `DemoSeeder`: algunas `staff_attendances` con `method='qr'` y una fila de `gym_qr_codes`.
- **Criterio:** `migrate:fresh --seed` termina; la cola de solicitudes y el calendario Personal
  muestran datos.

### Fase 7 — Seguridad y validaciones finales
- Revisar: token genérico en errores, throttle, `Str::isUuid`, `gym_id` forzado del QR,
  permisos en todas las rutas nuevas, CSRF, cámara detenida al cerrar.
- **Criterio:** checklist de la sección 20.

### Fase 8 — Revisión final
- `php artisan route:list` sin rutas muertas; `npm run build` limpio; recorrido manual de los
  tres paneles (torno, fichaje manual, fichaje QR, correcciones, sedes); actualizar docs y AGENTS.md.
- **Criterio:** checklist completa y sin regresiones.

---

## 18. Riesgos y puntos de atención

1. **`gym_id` del registro ≠ sede activa de la sesión** (multi-sucursal). El QR define la sede y
   hay que forzarla explícitamente; los listados del entrenador muestran solo lo de su sede activa
   (global scope). Comportamiento correcto, pero hay que documentarlo y probarlo con 2 sedes.
2. **Zona horaria.** `config('app.timezone')` es UTC; los horarios se guardan y muestran en UTC.
   La marcación QR usa `now()` igual que el resto (coherente), pero si "la hora correcta" de la
   sucursal importa (America/Lima, UTC-5), es un tema de **todo el sistema**, no solo del QR:
   decidir aparte (renderizar en `gyms.timezone` o cambiar `app.timezone`). Fuera de alcance aquí.
3. **Abierta de un día anterior.** Tratarla como "sin turno" hoy es un cambio de comportamiento
   respecto al toggle actual (que cerraba el registro de ayer). Verificar que nadie dependía de ese
   atajo; el flujo de corrección/borrado cubre la limpieza.
4. **Doble escaneo accidental.** Cubierto con guarda de 30 s + throttle; calibrar el umbral (si es
   demasiado corto, dos taps seguidos rompen; si es largo, molesta).
5. **iOS: permiso de cámara.** Una negativa previa requiere ir a Ajustes. Dejar la marcación manual
   siempre visible y mensajes claros.
6. **`BarcodeDetector`** no está en Safari/iOS ni en todos los navegadores de escritorio → el
   fallback `jsQR` es obligatorio, no un extra.
7. **El admin hoy no ve las marcaciones laborales** (solo las de clientes). La pestaña "Personal"
   es necesaria para cumplir "la marcación aparece automáticamente en el módulo del admin".
8. **`php artisan test` roto (SQLite)** → verificación manual con datos demo (patrón del proyecto).
9. **Regenerar el QR invalida el físico.** Confirmación explícita en la UI y mensaje que lo advierta.
10. **`GymQrCode` sin `BelongsToGym`**: decisión deliberada (búsqueda global por token); un futuro
    mantenedor podría "arreglarlo" y romper el escaneo entre sedes → dejar el comentario en el modelo.

---

## 19. Archivos que probablemente habría que modificar/crear

### Backend
| Archivo | Acción |
|---|---|
| `database/migrations/2026_08_11_..._create_gym_qr_codes_table.php` | **NUEVO** |
| `database/migrations/2026_08_11_..._add_method_to_staff_attendances.php` | **NUEVO** |
| `app/Models/GymQrCode.php` | **NUEVO** |
| `app/Models/Gym.php` | EDITAR — relación `qrCodes()` |
| `app/Models/StaffAttendance.php` | EDITAR — `method` en `$fillable` + `method_legible` |
| `app/Services/AsistenciaService.php` | EDITAR — `marcarStaff()` |
| `app/Http/Controllers/Entrenador/AttendanceController.php` | EDITAR — `marcar()` delega; + `estado()`, `marcarPorQr()` |
| `app/Http/Controllers/Admin/GymQrCodeController.php` | **NUEVO** |
| `app/Http/Controllers/Admin/AttendanceController.php` | EDITAR — + `personal()` |
| `routes/admin.php` | EDITAR — rutas QR + `admin.asistencia.personal` |
| `routes/entrenador.php` | EDITAR — `estado` y `qr` (antes de `{attendance}`) |
| `database/seeders/DemoSeeder.php` | EDITAR — demo con `method='qr'` y `gym_qr_codes` |

### Frontend
| Archivo | Acción |
|---|---|
| `resources/views/entrenador/asistencia/mi-marcacion.blade.php` | EDITAR — botón "Escanear QR" |
| `resources/views/entrenador/asistencia/_escaneo-qr.blade.php` | **NUEVO** — modal de escaneo |
| `resources/js/escaneo-qr.js` | **NUEVO** — cámara + decode |
| `resources/js/app.js` | EDITAR — registrar módulo `escaneo-qr` |
| `resources/views/admin/sedes/index.blade.php` | EDITAR — botón "QR de asistencia" por fila |
| `resources/views/admin/sedes/qr.blade.php` | **NUEVO** — modal del QR |
| `resources/views/admin/sedes/qr-imprimir.blade.php` | **NUEVO** — impresión (patrón carnet) |
| `resources/views/admin/asistencia/personal.blade.php` | **NUEVO** — calendario Personal |
| `resources/views/admin/asistencia/_pestanas.blade.php` | EDITAR — pestaña "Personal" |
| `resources/views/admin/asistencia/solicitudes.blade.php` | EDITAR (cosmético) — columna método |

### Docs
| Archivo | Acción |
|---|---|
| `AGENTS.md` | EDITAR — estado del módulo tras implementar |
| `docs/plan-modulo-asistencias.md` / `docs/estado-mejoras-panel.md` | EDITAR — referencias al nuevo flujo |

---

## 20. Checklist final de implementación

- [ ] Dos migraciones nuevas aplican sin errores y sin tocar tablas ajenas.
- [ ] La marcación manual sigue funcionando idéntica (misma regla, mismo `staff_attendances`).
- [ ] La marcación QR escribe en `staff_attendances` con `method='qr'` y `gym_id` de la sucursal
      del token.
- [ ] Entrada/salida las decide el backend (toggle); el frontend nunca manda horas ni tipo ni sede.
- [ ] Anti-doble-escaneo: dos POST en <30 s → 422 con mensaje claro.
- [ ] Una "abierta" de otro día no se cierra al marcar hoy.
- [ ] Token inválido / revocado / sucursal inactiva → 422 genérico sin filtrar información.
- [ ] El admin genera, ve, imprime y regenera el QR por sucursal (con confirmación de regeneración);
      al regenerar, el token anterior deja de validar.
- [ ] El entrenador escanea desde el móvil y recibe toast de éxito con entrada/salida; la marcación
      aparece en su `mi-marcacion` y en la pestaña "Personal" del admin con su método.
- [ ] Cámara denegada / no soportada → mensaje claro y marcación manual disponible.
- [ ] El stream de cámara se detiene al cerrar el modal.
- [ ] Correcciones: la solicitud de una marcación QR pasa por el flujo existente sin cambios.
- [ ] Permisos en backend: `asistencia.registrar` en el POST de escaneo; `asistencia.ver` en
      "Personal"; `sedes.gestionar` en el QR del admin.
- [ ] CSRF en todos los POST; `throttle` en el endpoint de escaneo.
- [ ] `npm run build` limpio; `php artisan route:list` sin rutas muertas; `migrate:fresh --seed` OK.
- [ ] Sin regresiones: torno de clientes, fichaje manual, solicitudes, sedes y campanita intactos.
- [ ] Docs y AGENTS.md actualizados.

---

## Referencias clave verificadas (no exhaustivas)

- `app/Models/StaffAttendance.php` · `app/Models/Attendance.php` · `app/Models/AttendanceEditRequest.php`
- `app/Models/Gym.php` · `app/Models/Member.php` · `app/Models/Concerns/BelongsToGym.php`
- `app/Http/Controllers/Entrenador/AttendanceController.php` · `app/Http/Controllers/Admin/AttendanceController.php`
- `app/Http/Controllers/Admin/AttendanceEditRequestController.php` · `app/Http/Controllers/Admin/GymController.php`
- `app/Services/AsistenciaService.php` · `app/Support/GymContext.php` · `app/Http/Middleware/*`
- `routes/admin.php` · `routes/entrenador.php` · `routes/web.php`
- `database/migrations/2026_08_08_185901_create_staff_attendances_table.php` ·
  `…183759_create_attendance_edit_requests…` · `…190001_add_attendance_id…` ·
  `2026_08_03_000105_create_payments_and_attendances_tables.php` ·
  `2026_08_03_000101_create_gyms_table.php`
- `database/seeders/RolePermissionSeeder.php` · `database/seeders/DemoSeeder.php`
- `resources/views/entrenador/asistencia/*` · `resources/views/admin/asistencia/*` ·
  `resources/views/admin/sedes/index.blade.php` · `resources/views/admin/clientes/carnet.blade.php`
- `resources/js/qr.js` · `resources/js/carnet.js` · `resources/js/app.js` · `package.json`
- `config/app.php` · `config/sparta.php` · `bootstrap/app.php` · `docs/plan-modulo-asistencias.md`
