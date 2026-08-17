# Plan — Sistema unificado de notificaciones (toast + campanita)

> **Estado:** ✅ **Implementado el 17-08-2026** (autorización del usuario). Auditoría de solo lectura + plan técnico primero, luego implementación completa con 12 tests en verde (PHPUnit contra `sparta_gym_test`).
> **Destinatario:** agente que mantenga el sistema o implemente ajustes futuros.
> **Regla de oro:** no romper lo que funciona. El plan reutiliza la campanita, el cajón lateral, los toasts flash, los tokens y el patrón de polling que ya existen. **Sin nuevas dependencias** (sin Pusher, sin Reverb, sin librerías de toast).

---

## 1. Diagnóstico: qué existe hoy

### 1.1 Campanita + cajón lateral — YA EXISTE (3 fuentes mezcladas en el cliente)

- `resources/views/layouts/panel.blade.php`: botón `.panel__campana` con badge `.notificaciones__contador` (animación `notif-pop` + anillo respirando) y un cajón lateral `.notificaciones__panel` (x-teleport al `<body>`, clases `v-notif-entra/...`) que lista ítems y navega al hacer clic. Todo bajo el componente Alpine `notificaciones()`.
- `resources/js/mensajes.js` → `Alpine.data('notificaciones')`: **polling cada 15 s** que suma tres fuentes distintas y las mezcla en una sola lista:
  1. Mensajes sin leer (`mensajes.no-leidas` / `mensajes.lista`) — los tres perfiles.
  2. Alertas de stock (`admin.inventario.alertas`) — solo quien tiene `inventario.ver` (admin/recepción).
  3. Solicitudes de corrección de asistencia pendientes (`admin.asistencia.solicitudes.pendientes-json`) — solo quien tiene `asistencia.aprobar` (admin).
- **Lo que falta:** no hay estado leído/no leído (excepto `read_at` de mensajes), no hay "marcar todas como leídas", no hay vigencia ni expiración, y el contador es la suma de tres respuestas que se recalculan cada vez (cada fuente debe existir y responder para que el total no mienta).

### 1.2 Toasts flash — YA EXISTEN (server-side, solo para acciones propias)

- `resources/views/layouts/panel.blade.php` (~línea 210): `session('exito')` / `session('error')` se pintan como `.toast.toast--exito|error` en `.toasts` (fijo abajo-derecha, `z-index: var(--z-aviso) = 90`, ancho `min(24rem, 100vw - 2·e-5)`, auto-cierre a los 5 s con `x-init` + `setTimeout`, botón de cerrar).
- Estilos en `resources/css/panel.css` (~línea 771). Variantes de color: `--ok` (éxito) y `--alerta/--sangre` (error), sobre `--grafito` con `--s-lg`.
- Se usa en **más de 100 retornos** de controladores (`->with('exito', ...)`), incluyendo matrícula, ventas, inventario, membresías, asistencia, perfil, contenido web. **No existe ningún toast client-side**: no hay función JS que muestre un toast tras una acción AJAX.
- `session('bienvenida')` (login/registro) es un `.aviso` estático arriba del contenido, no un toast — se conserva tal cual.

### 1.3 Mensajería interna — YA EXISTE, con leído/no leído propio

- `conversations` + `conversation_participants` + `messages` (`read_at` por mensaje). Chat 1:1 por sede; admin ve hilos de todas sus sedes (`sinFiltroDeGimnasio`).
- Polling: 4 s dentro de `/mensajes`, 15 s para el badge del sidebar (`data-mensajes-no-leidas` → `mensajes.no-leidas`).
- `Conversation::noLeidasTotales()` ya resuelve el total de un usuario (global para admin).

### 1.4 Alertas de stock — YA EXISTEN, con ciclo de vida propio

- `stock_alerts` (una fila por producto, única por `product_id`), mantenida por `App\Services\StockAlertService` desde el observer `Product::saved` de `AppServiceProvider`. Se crea al entrar en "bajo"/"agotado" y **se borra sola** al volver a "normal".
- Expuesta a la campanita por `StockAlertController::pendientesJson`. **No tiene leído/no leído** (desaparece al resolverse) ni histórico.

### 1.5 Solicitudes de corrección de asistencia — YA EXISTEN, con estados

- `attendance_edit_requests` con `status = pendiente|aprobada|rechazada`. La pendiente alimenta la campanita del admin; aprobar/rechazar archiva. **No tiene leído/no leído**: un admin que ya vio la lista sigue viendo el contador hasta que se resuelve.

### 1.6 Infraestructura aprovechable y huecos

- `User` ya usa `Illuminate\Notifications\Notifiable` (Laravel 12) pero **no existe tabla `notifications`** ni se usa el canal `database` en ninguna parte.
- **No hay broadcasting**: `BROADCAST_CONNECTION=null` (phpunit), sin Pusher/Reverb en `composer.json`. El mecanismo de "tiempo real" disponible y ya probado es el **polling** (15 s global, 4 s en el chat).
- Colas: driver `database` (tabla `jobs` existe; `composer dev` corre `queue:listen`; `DEPLOY_RENDER.md` documenta `QUEUE_CONNECTION=database`). **No hay tareas programadas** (`routes/console.php` solo tiene `inspire`).
- Tokens de z-index en `tokens.css`: `--z-nav: 60; --z-modal: 80; --z-aviso: 90; --z-splash: 100`.

### 1.7 Conclusión

Ya hay: campanita, cajón, contador animado, toasts flash, mensajería con leído, alertas de stock, solicitudes. Falta un **único origen de verdad persistente por usuario** que: una los eventos de todos los módulos, tenga leído/no leído, contador real, "marcar todas como leídas", **vigencia de 24 h** y toasts que lleguen **sin recargar** a los tres perfiles.

---

## 2. Decisiones de arquitectura

| # | Decisión | Por qué |
|---|----------|---------|
| D1 | **Una tabla `notifications` es la única fuente** de campanita, contador y toasts. Las tablas de dominio (`messages`, `stock_alerts`, `attendance_edit_requests`) siguen siendo la verdad de su módulo y **disparan** notificaciones; no se consultan para el badge del panel. | Hoy el badge suma 3 endpoints; cualquier fuente nueva obliga a tocar el JS. Centralizar elimina el problema y el futuro evento solo es una llamada al servicio. |
| D2 | **Sin websockets**: polling unificado (15 s, configurable). | Es el mecanismo ya probado del proyecto, suficiente para la escala, cero dependencias e infraestructura nueva (alineado con AGENTS.md: sin dependencias externas). Reverb/SSE quedan como upgrade futuro documentado, no como requisito. |
| D3 | **Modelo propio `Notification`** (no el canal `database` de Laravel). | El canal de Laravel es polimórfico y no permite dedupe/agrupación por sujeto (clave única imposible con `NULL`), ni prioridad, ni expiración explícita. Un modelo propio encaja con el ethos "propio y pequeño" del proyecto y con `BelongsToGym` (regla de AGENTS.md: todo modelo con datos de un gimnasio usa el trait). |
| D4 | **El actor no recibe su propia notificación**: su feedback inmediato sigue siendo el toast flash (`with('exito')`) que ya funciona sin JS. La notificación es para los **otros** (o para el propio usuario en eventos diferidos: vencimientos, resultados de aprobación). | Evita el doble toast (flash + notificación) para la misma acción. |
| D5 | **Dedupe por (usuario, tipo, sujeto) mientras esté sin leer**: `updateOrCreate` con `read_at = null`; si ya existe una fila sin leer del mismo sujeto, se refresca sin duplicar y **sin re-toastear**. | Stock, solicitudes y vencimientos no deben acumular filas ni toasts repetidos. |
| D6 | **Vigencia 24 h**: borrado programado (comando horario) + filtro defensivo `created_at >= now()-24h` en todas las consultas. | El borrado solo no basta (si el cron no corre, el cajón debe dejar de mostrar lo viejo); el filtro solo deja basura en la tabla. Ambos, y el sistema queda correcto aunque falle el cron. |
| D7 | **Los toasts realtime se pintan en el mismo contenedor `.toasts`** que los flash, con un store Alpine compartido. | Un solo contenedor = un solo z-index y un solo punto de posición; el flash (server) y el realtime (JS) nunca se pisan porque el flash dura 5 s. |

---

## 3. Estructura de datos (migración propuesta)

**Tabla `notifications`** (nueva):

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `gym_id` | FK gyms, nullable | Sede **de origen** del evento. `BelongsToGym` lo rellena solo y aísla por sede; admin usa `sinFiltroDeGimnasio()` (mismo patrón que mensajes/solicitudes). |
| `user_id` | FK users | **Destinatario** (único receptor por fila). |
| `type` | string(60) | Clave del evento: `stock.agotado`, `venta.nueva`, `mensaje.nuevo`, `asistencia.solicitud`, `asistencia.resuelta`, `matricula.nueva`, `membresia.por-vencer`, `membresia.vencida`, `asistencia.registrada`, `medida.registrada`, `rutina.asignada`, `resena.nueva`, `resena.aprobada`, `contacto.nuevo`, `registro.nuevo`, `cuenta.nueva`. |
| `subject_id` | bigint nullable | Id del recurso para dedupe/agrupación (`product_id`, `conversation_id`, `attendance_edit_request_id`, `sale_id`, `member_id`, `contact_message_id`, ...). Sin índice único (MySQL no permite múltiples NULLs); el dedupe vive en el servicio. |
| `title` | string(120) | Título corto del toast/ítem ("Stock agotado", "Nuevo mensaje de Kevin"). |
| `body` | string(255) | Mensaje ("Creatina 300 g: quedan 0 · min 4"). |
| `icon` | string(40) | Nombre del icono de `components/icono.blade.php` (caja, chat, entrada, reloj, billetera, estrella, correo, usuarios, check, escudo...). |
| `priority` | enum('alta','media','baja') | Ver §8. |
| `action_url` | string(255) nullable | A dónde navegar al hacer clic (ruta por nombre resuelta al crear). |
| `data` | json nullable | Datos extra (p. ej. `member_id` para construir la URL del entrenador). |
| `read_at` | timestamp nullable | Estado leído/no leído. |
| `created_at` / `updated_at` | timestamps | `created_at` es el cursor del polling y la base de la vigencia. |

**Índices:** `(user_id, read_at)`, `(user_id, type, subject_id)`, `(created_at)` para el prune. `gym_id` lo cubre el scope de `BelongsToGym`.

**Config en `config/sparta.php`:**

```php
// Notificaciones unificadas (campanita + toasts).
'notificaciones' => [
    'vigencia_horas'     => 24,          // horas de vida de una notificación
    'polling_segundos'   => 15,          // ciclo del badge + toasts realtime
    'toast_duracion'     => [            // segundos visibles por prioridad
        'baja'  => 4,
        'media' => 5,
        'alta'  => 8,
    ],
    'toast_max_visibles' => 4,           // toasts simultáneos antes de agrupar
],
```

---

## 4. Flujo de generación y almacenamiento

### 4.1 Servicio central

`App\Services\NotificationService` (nuevo):

```php
// Crea o refresca (dedupe) una notificación para un destinatario.
public function disparar(
    User $destinatario,
    string $type,
    string $title,
    string $body,
    string $icon,
    string $prioridad = 'media',
    ?int $subjectId = null,
    ?string $actionUrl = null,
    array $data = [],
): Notification;

// Variante para varios destinatarios (p. ej. todos los admins del gym).
public function dispararA(
    iterable $destinatarios, ...$args
): void;

// Marcar una como leída; marcar todas las del usuario; contar sin leer.
public function marcarLeida(Notification $n, User $u): void;
public function marcarTodasLeidas(User $u): void;
public function noLeidas(User $u): int;   // solo vigentes (< 24 h)

// Limpieza programada.
public function limpiarVencidas(): int;   // DELETE where created_at < now()-24h
```

- El dedupe: `Notification::updateOrCreate(['user_id' => ..., 'type' => ..., 'subject_id' => ..., 'read_at' => null], [...])`. Si la fila **ya existía** (`! wasRecentlyCreated`), no se genera toast (solo se actualiza título/cuerpo/prioridad y se mantiene viva la fila); si es **nueva**, sí.
- `gym_id` lo rellena `BelongsToGym` (sed de origen); al listar, el scope filtra por la sede activa y el admin usa `sinFiltroDeGimnasio()`.

### 4.2 Dónde se dispara cada evento

**Estrategia:** *observers de modelo* para eventos puros (Message, Sale, ContactMessage, Testimonial) y *llamadas en servicios* para los flujos que ya están centralizados (StockAlertService, AsistenciaService, MatriculaService). Esto evita tocar ~15 controladores.

| Evento | Dónde se engancha | Destinatarios | Prioridad | Dedupe (subject) |
|---|---|---|---|---|
| Nuevo mensaje | observer `Message::created` | el otro participante | media | `conversation_id` |
| Nueva venta de mostrador | observer `Sale::created` (solo `sale_type = producto`) | admin+recepción del gym, **excepto el actor** | media | `sale_id` (sin dedupe) |
| Stock bajo / agotado | `StockAlertService::evaluar` (al crear la alerta) | admin+recepción | **alta** si `agotado`, media si `bajo` | `product_id` |
| Stock resuelto | `StockAlertService::evaluar` (al borrar) | — | — | marca leídas/borra las filas del producto |
| Solicitud de corrección/eliminación | `AsistenciaService::solicitarCorreccion/Eliminacion` | quienes tengan `asistencia.aprobar` | **alta** | `attendance_edit_request_id` |
| Solicitud aprobada/rechazada | `AsistenciaService::aprobar/rechazar` | el solicitante (`requested_by`) | media | `attendance_edit_request_id` |
| Matrícula / renovación | `MatriculaService::nuevaMatricula/renovarMembresia` | admin+recepción del gym, excepto el actor | baja | `member_id` (solo si el actor no es admin/recepción) |
| Nuevo registro público | `RegisterController::registrar` | admin+recepción del gym | media | `member_id` |
| Membresía próxima a vencer / vencida | **comando programado diario** `notificaciones:vencimientos` | el cliente (`member->user`) + admin (agrupado) | media / **alta** si vencida | `member_id` (por día: se reemplaza la del día) |
| Asistencia de cliente registrada | `AsistenciaService::registrarEntrada` | el cliente (`member->user`) si tiene cuenta | baja | `attendance_id` |
| Medida registrada por staff | `MemberController::guardarMedida` / `Entrenador\MemberController` | el cliente | baja | `member_measurement_id` |
| Medida registrada por el cliente | `Cliente\ProgressController::guardar` | su entrenador asignado (opcional, ver §13) | baja | `member_measurement_id` |
| Rutina/programa asignado | `Cliente\ProgramController::asignar` y `MatriculaService` (si asigna rutina) | el cliente | media | `routine_id` |
| Nueva reseña | observer `Testimonial::created` (estado no publicado) | admin | baja | `testimonial_id` |
| Reseña aprobada/publicada | `Admin\TestimonialController::publicar` | el cliente autor | baja | `testimonial_id` |
| Nuevo mensaje de contacto web | observer `ContactMessage::created` | admin | media | `contact_message_id` |

**Regla transversal:** si `auth()->user()` (el actor) está entre los destinatarios, se **excluye** (D4). Para los eventos de modelo sin request (contacto web), no hay actor: notificar a todos los admins.

### 4.3 Baja de notificaciones (resolución)

Cuando un evento se resuelve, su fila deja de mostrarse aunque no hayan pasado 24 h:

- **Stock repuesto** → `StockAlertService` borra las filas `type = stock.*`, `subject_id = product_id` del producto.
- **Solicitud revisada** → al aprobar/rechazar, la fila `asistencia.solicitud` se marca leída (y la nueva `asistencia.resuelta` avisa al solicitante).
- **Conversación abierta** → `MensajeController::listaMensajes` marca leídas las filas `mensaje.nuevo` de esa conversación (además del `read_at` de mensajes que ya existe).
- El resto vive sus 24 h naturales y muere con el prune.

---

## 5. Casos de uso por perfil

### Administrador (y recepción)
1. **Nuevo registro público** — "Un nuevo cliente se registró en la web" → media, clic abre la ficha.
2. **Matrícula/renovación hecha por un entrenador** — "Kevin matriculó a María Pérez" → baja.
3. **Venta de mostrador de un entrenador** — "Venta 0042 · S/ 18.50 (2 productos)" → media.
4. **Stock bajo / agotado** — "Creatina 300 g: quedan 0 · min 4" → alta si agotado, media si bajo. **Agrupado**: si en el mismo ciclo hay varios productos, un toast "3 productos por agotarse" y el detalle en el cajón (una fila por producto).
5. **Solicitud de corrección/eliminación pendiente** — "Kevin pide corregir la entrada de María del 15 ago" → **alta** (requiere decisión), clic abre la cola de aprobación.
6. **Nueva reseña pendiente** — "Nueva reseña a la espera de aprobación" → baja.
7. **Nuevo mensaje de contacto web** — "Mensaje de contacto: '¿Horarios?'" → media.
8. **Membresías por vencer / vencidas** — agrupado por día: "5 membresías vencen esta semana" / "3 membresías vencidas hoy" → media/alta. (Reemplaza/enriquece la tabla "Vencen esta semana" del dashboard.)
9. **Nuevo mensaje** → media, clic abre la conversación.

### Entrenador
1. **Resultado de su solicitud** — "Tu corrección de asistencia fue aprobada/rechazada" → media.
2. **Nuevo cliente asignado** — "Ahora tienes a Luis Torres a tu cargo" → baja (al crearse el `TrainerAssignment`).
3. **Nuevo mensaje** → media.
4. *(Opcional)* **El cliente registró su peso** — baja.

### Cliente
1. **Membresía próxima a vencer** — "Tu membresía vence en 3 días" → media. **Vencida** — "Tu membresía venció ayer" → alta.
2. **Nuevo mensaje del staff** — media.
3. **Asistencia registrada** — "Entrada registrada · 07:42" → baja (confirmación de que el staff lo marcó).
4. **Medida registrada por recepción/entrenador** — baja.
5. **Rutina/programa asignado** — "Tu entrenador te asignó 'Fuerza Fullbody'" → media.
6. **Reseña aprobada** — "Tu reseña ya está publicada en la web" → baja.
7. **Renovación de membresía** — "Tu membresía fue renovada hasta el 15 sep" → baja.

---

## 6. Leído/no leído, contador y "Marcar todas como leídas"

- **Leído/no leído** = `read_at` nulo o no, con `data-revelar` visual: ítem sin leer con punto/negrita y fondo sutil (`.notificaciones__item.is-no-leida`); al hacer clic en un ítem → `POST /notificaciones/{id}/leida` + navegar a `action_url`. Abrir el cajón **no** marca todo (el usuario decide qué es nuevo); para eso está el botón.
- **Contador** = `GET /notificaciones/total` → `COUNT(user_id = yo, read_at IS NULL, created_at >= now()-24h)`. Reemplaza la suma de 3 endpoints del componente actual; mantiene el badge `.notificaciones__contador` y su animación sin cambios.
- **"Marcar todas como leídas"**: botón **sutil** en la cabecera del cajón (texto mono pequeño, mismo registro que las etiquetas del panel, sin estilo de botón llamativo — un `button` con `font-family: var(--f-mono); font-size: var(--t-xs); color: var(--humo)`, hover a `--hueso`), a la izquierda del botón de cerrar existente. `POST /notificaciones/leidas` → `UPDATE read_at = now() WHERE user_id = yo AND read_at IS NULL`. El badge y la lista se limpian al instante en el store.
- Los **badges del sidebar** (`data-mensajes-no-leidas`, `data-stock-alertas`) se conservan: son atajos de módulo, no el contador global. El contador de la campanita pasa a leer exclusivamente de `/notificaciones/total` (D1).

---

## 7. Expiración de 24 horas

- **Borrado**: comando `php artisan notificaciones:limpiar` (nuevo, en `app/Console/Commands/` o closure en `routes/console.php`) → `DELETE FROM notifications WHERE created_at < now()->subHours(config('sparta.notificaciones.vigencia_horas'))`, programado **cada hora** con `Schedule::command(...)->hourly()`.
- **Filtro defensivo**: toda consulta del servicio (`noLeidas`, lista, toasts) lleva `where('created_at', '>=', now()->subHours(...))`. Así, aunque el cron no haya corrido, nada de más de 24 h se muestra ni cuenta.
- **Efecto sobre lo nuevo:** las notificaciones que siguen llegando no se ven afectadas; el cursor del polling (`desde = ultimo_id`) es monotónico, así que los toasts nuevos siguen entrando aunque se borren filas viejas.
- Coste: la tabla se mantiene en ~"notificaciones generadas en 24 h", siempre acotado.

---

## 8. Diseño de los toasts emergentes

### 8.1 Composición y jerarquía

```
┌─────────────────────────────────┐
│ [icono] Título (bold, t-sm)   ✕ │
│         Mensaje (t-xs, ceniza)  │
│         ─ borde lateral por prioridad
└─────────────────────────────────┘
```

- **Icono** (24px, `stroke-width 1.6`, catálogo existente de `components/icono.blade.php`): `caja` (stock), `chat` (mensaje), `entrada`/`reloj` (asistencia), `billetera` (venta), `escudo`/`reloj` (membresía), `estrella` (reseña), `correo` (contacto), `usuarios` (registro), `check` (confirmación).
- **Título** con `--f-texto` semibold `--t-sm`, **cuerpo** con `--t-xs` y `--ceniza`. Sin relleno (copy lacónico, AGENTS.md).
- **Prioridad** expresada en el **borde y el icono**, no en tamaño:
  - `alta` → borde/icono `--sangre-viva` (mismo tinte que el contador), rol `alert`.
  - `media` → `--brasa` (como `.kpi__icono--brasa`), rol `status`.
  - `baja` → `--acero-claro`/`--humo`, rol `status`.
  - Éxito/error propios (flash) conservan `--ok` / `--alerta` (variantes `.toast--exito` / `.toast--error` existentes).
- Nuevas clases: `.toast--alta`, `.toast--media`, `.toast--baja` en `panel.css`, con `color-mix` sobre tokens (patrón ya usado en `.toast--exito/error` y en `--whatsapp`).

### 8.2 Posición, duración y comportamiento

- **Posición:** abajo-derecha, igual que `.toasts` actual (`right: var(--e-5); bottom: var(--e-5)`), pila con `gap`. **Máximo 4 visibles**; el excedente entra en cola FIFO (se muestra al cerrarse uno) o, si llegan más de 3 en un mismo ciclo de polling, se **agrupan** en un solo toast "N nuevas notificaciones" (ver §9).
- **Duración:** `alta` 8 s, `media` 5 s, `baja` 4 s (config `sparta.notificaciones.toast_duracion`). **Pasar el cursor pausa** la cuenta; salir reanuda. `prefers-reduced-motion: reduce` → sin animación de entrada/salida (patrón ya usado en el contador).
- **No bloquean nada:** el contenedor es `pointer-events: none` y cada toast `pointer-events: auto` (ya es así). Se propone un **nuevo token `--z-toast: 85`** (entre `--z-modal: 80` y `--z-aviso: 90`) para que un toast **nunca tape los botones de un modal abierto** (los modales cierran abajo-derecha), manteniendo el flash de confirmación por encima del contenido normal.
- **Responsive:**
  - **PC/tablet:** ancho fijo `min(24rem, calc(100vw - 2·var(--e-5)))` (igual que hoy), pila abajo-derecha.
  - **Móvil (≤560 px):** ancho casi completo (`calc(100vw - 2·var(--e-4))`), mismo `bottom`, con `padding-bottom: env(safe-area-inset-bottom)` (iPhone). No tapa la campanita ni el botón de tema (arriba-derecha) ni el menú móvil (izquierda).
  - Los toasts realtime comparten contenedor con los flash (D7): el HTML server sigue pintando el flash y el store Alpine agrega/retira los realtime.
- **Accesibilidad:** contenedor `aria-live="polite"` (o `assertive` solo para `alta`); `role="alert"` en alta/error; botón de cerrar enfocable (`:focus-visible` global de `base.css` ya aplica). El store expone `aria-label` en cada cierre.

### 8.3 Store Alpine (JS)

`resources/js/notificaciones.js` (nuevo), un solo módulo con tres piezas, todas bajo `document.addEventListener('alpine:init')`:

- `Alpine.store('toasts')`: cola de toasts; `mostrar({titulo, cuerpo, icono, prioridad, url})`; gestión de timers (duración por prioridad, pausa en hover), máximo visibles y agrupación por ráfaga; entrada/salida con las transiciones de Alpine (`x-transition.duration.300ms` como el flash actual).
- `Alpine.data('campanita')`: reemplaza al `notificaciones()` actual de `mensajes.js`; contador desde `GET /notificaciones/total` (15 s), cajón desde `GET /notificaciones`, "marcar todas", navegación con marca de leída.
- `iniciarPollingToasts()`: cada ciclo pide `GET /notificaciones/nuevas?desde={ultimoId}`; por cada fila **no leída** nueva → `toasts.mostrar(...)` (si `!wasRecentlyCreated` el servidor no la devuelve como nueva, así el dedupe no re-toastea); actualiza `ultimoId` y el contador.

`mensajes.js` conserva el componente `chat` y `iniciarContadorMensajes` (badges de módulo); se elimina solo el `Alpine.data('notificaciones')`.

---

## 9. Anti-duplicados y agrupación

- **Dedupe por sujeto** (D5): `stock` → por producto; `mensaje.nuevo` → por conversación; `asistencia.solicitud` → por solicitud (el servicio ya impide dos pendientes del mismo registro); `membresia.por-vencer` → por miembro y por día (el comando diario reemplaza la del día anterior). Re-emitir el mismo evento no duplica fila ni toast.
- **Sin dedupe** (cada ocurrencia cuenta): `venta.nueva`, `contacto.nuevo`, `registro.nuevo`, `resena.nueva` (cada una es un recurso distinto con su `subject_id`).
- **Agrupación en toasts:** si el polling trae más de 3 filas nuevas en un ciclo → **un solo toast** "N nuevas notificaciones" (icono `campana`, prioridad de la más alta del lote) + badge con el total. El detalle queda en el cajón, que lista ítem por ítem.
- **Ráfagas:** el cajón ya separa por tipo; el toast agrupa por ciclo de polling. No se implementan ventanas de 60 s ni throttling por tipo en v1 (documentado como posible refinamiento).
- **Límite del cajón:** máx. 50 ítems, orden `created_at DESC`, solo vigentes (<24 h).

---

## 10. Endpoints (nuevos, en `routes/web.php`, grupo `auth` + `sede.activa`)

| Método | Ruta | Controlador | Respuesta |
|---|---|---|---|
| GET | `/notificaciones/total` | `NotificationController::total` | `{ total }` |
| GET | `/notificaciones` | `NotificationController::index` | `{ items: [{id, type, title, body, icon, priority, leida, creada, url}] }` |
| GET | `/notificaciones/nuevas` | `NotificationController::nuevas` | `{ ultimo_id, toasts: [...] }` (solo no leídas, `id > desde`) |
| POST | `/notificaciones/{notification}/leida` | `NotificationController::marcarLeida` | `{ ok }` |
| POST | `/notificaciones/leidas` | `NotificationController::marcarTodas` | `{ ok, total }` |

`NotificationController` (nuevo) usa el `NotificationService`, valida que `notification.user_id === auth()->id()` (o `abort 403`) y respeta la sede vía el scope + `sinFiltroDeGimnasio()` para admin.

---

## 11. Archivos

### Crear
| Archivo | Contenido |
|---|---|
| `database/migrations/2026_08_XX_create_notifications_table.php` | Tabla §3 + índices. |
| `app/Models/Notification.php` | `BelongsToGym`, `$fillable`, casts (`read_at`, `data`), relación `user()`. |
| `app/Services/NotificationService.php` | §4.1 (disparar, dedupe, leídas, contador, prune). |
| `app/Http/Controllers/NotificationController.php` | §10. |
| `app/Console/Commands/LimpiarNotificaciones.php` (o closure en `routes/console.php`) | Borrado de vencidas. |
| `app/Console/Commands/NotificarVencimientos.php` (o closure) | Revisión diaria de membresías por vencer/vencidas → clientes + admin. |
| `resources/js/notificaciones.js` | Store `toasts` + `campanita` + polling (§8.3). |
| `tests/Feature/NotificationsTest.php`, `tests/Feature/NotificationServiceTest.php` | §12. |

### Modificar
| Archivo | Cambio |
|---|---|
| `routes/web.php` | 5 rutas nuevas en el grupo `auth` (sin middleware de rol: los tres perfiles reciben notificaciones). |
| `resources/views/layouts/panel.blade.php` | Reemplazar `window.spartaNotificaciones` por `window.spartaNotificaciones = { total, lista, nuevas, leida, leidasTodas }` (3 bloques @if de permisos desaparecen: ya no se suman fuentes). Botón "Marcar todas" en la cabecera del cajón. Contenedor `.toasts` compartido (se mantiene el bloque flash). |
| `resources/js/app-panel.js` | `import './notificaciones'` (estático, como `mensajes`). |
| `resources/js/mensajes.js` | Quitar `Alpine.data('notificaciones')`; conservar `chat` y `iniciarContadorMensajes`. |
| `resources/css/tokens.css` | `--z-toast: 85`. |
| `resources/css/panel.css` | `.toast--alta/--media/--baja`, `.toasts` con `z-index: var(--z-toast)`, `.notificaciones__marcar-todas`, `.notificaciones__item.is-no-leida`, `padding-bottom: env(safe-area-inset-bottom)` en móvil. |
| `config/sparta.php` | Bloque `notificaciones` (§3). |
| `routes/console.php` | `Schedule::command('notificaciones:limpiar')->hourly();` + `->dailyAt('08:00')` para vencimientos. |
| `app/Services/StockAlertService.php` | Disparar notificación al crear la alerta; limpiar filas al resolver. |
| `app/Services/AsistenciaService.php` | Notificar en `solicitarCorreccion/Eliminacion` (→ admin) y en `aprobar/rechazar` (→ solicitante). |
| `app/Services/MatriculaService.php` | Notificar matrícula/renovación (→ admin/recepción, salvo actor). |
| `app/Http/Controllers/MensajeController.php` | Marcar leídas las filas de la conversación al abrirla. |
| `app/Http/Controllers/Auth/RegisterController.php` | Notificar nuevo registro. |
| `app/Http/Controllers/Cliente/ProgramController.php`, `Admin/TestimonialController.php`, `Cliente/TestimonialController.php`, `Admin/MemberController.php`, `Entrenador/MemberController.php`, `Cliente/ProgressController.php` | Disparos de rutina asignada, reseña aprobada, medidas. |
| `app/Providers/AppServiceProvider.php` | Observers: `Message::created`, `Sale::created`, `ContactMessage::created`, `Testimonial::created` (al lado del `Product::saved` existente). |

**Fuera de alcance:** sonido, Push API, pantalla propia de notificaciones, Reverb/websockets.

---

## 12. Estrategia de pruebas

### Unit / Feature (PHPUnit, `RefreshDatabase`)
- **Servicio:** `disparar` crea fila correcta (gym, destinatario, prioridad, URL); `disparar` con mismo `(type, subject_id)` sin leer → **no duplica** y no re-toastea (`wasRecentlyCreated` falso); `marcarLeida` / `marcarTodasLeidas`; `noLeidas` solo cuenta vigentes; **el actor no recibe su propia notificación**; destinatario por permiso/rol correcto.
- **Expiración:** fila de 25 h no cuenta ni aparece; `limpiarVencidas()` borra solo lo viejo; filas nuevas intactas.
- **Emisores:** crear venta → admin con `inventario.ver` recibe 1, el vendedor 0; solicitud de corrección → admin recibe (alta); aprobar → el entrenador solicitante recibe `asistencia.resuelta`; enviar mensaje → el otro participante recibe 1; producto en `bajo`/`agotado` → admin recibe y al reponer la fila desaparece; registro público → admin/recepción reciben.
- **Endpoints:** `total`, `index` (solo vigentes, orden, máx 50), `nuevas` con cursor, `marcarLeida` con 403 si el id no es del usuario, `marcarTodas` limpia el contador.

### Manual / navegador (gstack `/qa` o `/browse`, tras implementar)
- Toast realtime aparece **sin recargar** al recibir un mensaje/venta desde otra sesión (dos navegadores: admin + entrenador).
- Pila: máx 4 visibles, hover pausa, cierre manual, `prefers-reduced-motion` sin animación.
- Responsive: 320 / 375 / 768 / 1280 px — abajo-derecha, ancho completo en móvil, sin tapar campanita, menú móvil ni botones de modal (`--z-toast < --z-modal`).
- Contador: sube sin recarga, baja al abrir el cajón y al marcar todas; los badges del sidebar (mensajes, stock) siguen funcionando.
- Expiración: con `vigencia_horas = 1` temporal en local, un ítem viejo desaparece del cajón y del contador.

---

## 13. Riesgos y límites

1. **No duplicar contadores**: al migrar la campanita a `/notificaciones/total`, quitar la suma de las 3 fuentes de `mensajes.js` en el mismo commit (o el badge contará doble durante la transición).
2. **Dedupe con `NULL`**: MySQL no permite claves únicas con NULLs múltiples; el dedupe es del servicio (`updateOrCreate` + `whereNull('read_at')`), no de la BD. No intentar un índice único sobre `subject_id`.
3. **Notificar sin recargar = polling**: el toast de otro usuario puede tardar hasta 15 s. Aceptado y documentado; es el mecanismo actual. No introducir websockets sin pedirlo.
4. **El `member->user` puede no existir** (no todo socio tiene cuenta): siempre `optional()` antes de notificar a un cliente.
5. **Observers con contexto**: los observers corren en el ciclo de request; `auth()->user()` está disponible. Para el prune (CLI) no hay actor — el comando no notifica, solo borra.
6. **Vencimientos agrupados**: el comando diario debe ser idempotente (dedupe por miembro+día) para no re-toastear al correr dos veces el mismo día.
7. **No romper el cajón actual**: la migración del componente Alpine debe mantener `x-teleport`, transiciones (`v-notif-*`) y el estado vacío; solo cambia la fuente de datos y se agrega el botón "Marcar todas".
8. **Contacto web y reseñas** notifican a "todos los admins" sin actor: acotar siempre por `is_active` y por sede (evitar notificar admins de otra sucursal).
