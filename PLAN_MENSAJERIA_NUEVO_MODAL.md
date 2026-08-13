# PLAN DE IMPLEMENTACIÓN — Mensajería: el "Nuevo" pasa a modal (unificación de los tres paneles)

> **Fecha:** 2026-08-13 · **Modo:** análisis → plan (no implementación) · **Regla:** no rehacer el chat que ya funciona.
>
> Este documento es el resultado de leer el módulo de mensajería de punta a punta
> (rutas, controlador, vista, JS, CSS, cómo se integra en los tres paneles) y la
> propuesta técnica para que **otro agente lo ejecute después de que este plan sea aprobado**.
> Nada de lo aquí descrito está implementado todavía. **Es solo frontend: no se toca
> backend, rutas, endpoints, modelos ni esquema.**

---

## 1. Resumen de la solución

La sección **"Nuevo"** (el directorio de usuarios para iniciar un chat) se ve mal en
los tres paneles porque vive como **segunda pestaña** de la columna de hilos: comparte
ese carril angosto (340 px en escritorio, columna completa en móvil) con filtros de rol
(5 chips), buscador y lista de contactos, y al pulsarla **desaparece la lista de chats**
(no se ve ni a quién se está escribiendo). La propuesta aprobada en la conversación:

1. **Quitar la pestaña "Nuevo"** del listado. La columna de la izquierda queda
   **solo con la lista de conversaciones** (un solo panel, sin estados).
2. Añadir un **botón "+" en la cabecera de esa lista** ("Conversaciones"), que abre un
   **modal emergente** con el directorio: filtros de rol + buscador + contactos, exactamente
   el contenido que hoy vive en la pestaña. El modal usa el patrón `.modal__fondo/.modal__caja`
   ya existente y la **misma temática** (grafito, acero, sangre/brasa, mono).
3. Al pulsar **"Hablar"** (o el contacto) se crea/reutiliza la conversación con el endpoint
   existente `mensajes.conversar` y se abre el hilo; el modal se cierra.

El resultado es el **mismo módulo unificado** que ya comparten los tres roles (un solo
controlador + una sola vista), pero con la acción de "nuevo" desacoplada del listado: el
espacio del directorio deja de competir con los chats y se adapta mejor en móvil, tablet
y PC sin cambiar nada del tema.

---

## 2. Análisis de la arquitectura actual (verificado en código)

### 2.1 Ya está unificado: un solo módulo para los tres paneles

| Pieza | Dónde | Nota |
|---|---|---|
| Rutas | `routes/web.php:76-82` | Fuera de los grupos `rol:`; la usa cualquier cuenta activa de la sede (admin, recepción, entrenador, cliente) |
| Controlador | `app/Http/Controllers/MensajeController.php` | Compartido; sin diferencias por rol salvo que el **admin ve hilos de todas sus sedes** (`listaConversaciones`, líneas 166-175) y el **directorio filtra por la sede activa** (`GymContext::id()`, líneas 118-146) |
| Vista | `resources/views/mensajes/index.blade.php` | **Una sola vista** para los tres paneles (extiende `layouts.panel`) |
| JS | `resources/js/mensajes.js` | Componente Alpine `chat()` + badge de no leídos global (`iniciarContadorMensajes`) |
| CSS | `resources/css/panel.css:1250-1555` | Sección "MENSAJERÍA" |
| Enlace lateral | `resources/views/layouts/partials/enlace-mensajes.blade.php` | Incluido por `panel-nav.blade.php` en los **tres** paneles (líneas 49, 108, 140) |

**Conclusión de unificación:** la mensajería **ya** es un único módulo compartido. Este
plan no crea rutas/vistas por rol: **reordena la única vista** y eso se propaga solo a los
tres apartados. Es la "perfecta unificación": un cambio, tres paneles.

### 2.2 El problema de la pestaña "Nuevo"

La vista hoy tiene una barra de pestañas (`chat__pestanas`) con **Chats | Nuevo**
(`mensajes/index.blade.php:32-37`):

- **Chats** (`:39-64`): lista de conversaciones.
- **Nuevo** (`:66-107`): directorio con filtros de rol (Todos/Clientes/Entrenadores/Admin),
  buscador y lista de contactos con enlace WhatsApp + botón "Hablar".

Problemas observados (los mismos en admin, entrenador y cliente, pues es la misma vista):

1. **Compiten dos interfaces por el mismo carril.** El directorio necesita filtros que
   envuelven en varias filas (`chat__filtros-roles` con `flex-wrap`, `panel.css:1381`), un
   buscador y filas de contacto con dos acciones (WhatsApp + Hablar). En la columna de
   340 px queda apretado; en móvil, aunque la columna sea ancha, el conjunto filtros+
   buscador+lista sigue robando el espacio donde antes se veían los chats.
2. **Cambio de contexto brusco.** Al pulsar "Nuevo" desaparece la lista de chats; para
   volver hay que acertar en la pestaña "Chats". Es un sub-flujo oculto, no una acción.
3. **El estado vive en el componente** (`pestaña: 'chats'`, `mensajes.js:20`) y hay que
   recordar volver a él en `nuevaConversacion` (`mensajes.js:124`). Al mover el directorio
   a un modal, ese estado (y su reasignación) desaparece.

### 2.3 Lo que se reutiliza tal cual (no se toca)

- **Backend completo:** `MensajeController` y sus 7 rutas (`mensajes`, `lista`, `no-leidas`,
  `directorio`, `conversar`, `listaMensajes`, `enviar`). El modal consume exactamente los
  mismos endpoints JSON.
- **Lógica del hilo:** apertura, polling (`latido`, 4 s), envío, marcado de leído.
- **Badge de la barra lateral** y campanita de notificaciones (usan `lista` y `no-leidas`).
- **Temática visual:** todos los tokens, clases `.chat__*`, `.btn`, `.modal__*`, `.tarjeta`.
- **Iconos:** `agregar` (`+`) ya existe (`icono.blade.php:33`); no hace falta ninguno nuevo.

---

## 3. Propuesta de diseño (cómo queda)

### 3.1 Estructura nueva de la vista

```
.chat  (x-data="chat(...)")
├── .chat__panel                         ← columna izquierda
│   ├── .chat__cabecera-lista            ← NUEVA (sustituye a .chat__pestanas)
│   │   ├── "Conversaciones"             ← título del panel
│   │   └── botón "+" (icono "agregar")  ← abre el modal (aria-label "Nuevo mensaje")
│   └── .chat__cuerpo-lista
│       └── .chat__lista                 ← solo conversaciones (sin x-show de pestaña)
│
├── .chat__hilo                          ← columna derecha (sin cambios)
│   └── (cabecera / mensajes / escribir) ← intacto
│
└── (teleport a <body>) modal del directorio
    ├── .modal__fondo  (x-show="nuevoAbierto", @click cierra)
    └── .tarjeta.modal__caja[--ancho]
        ├── .modal__cabecera → "Nuevo mensaje" + .modal__cerrar
        ├── .chat__filtros   → chips de rol + buscador (contenido actual de :68-80)
        └── .chat__lista     → contactos (contenido actual de :82-106)
```

- El botón "+" se coloca **en la cabecera del panel de la lista**, no en el membrete de la
  página: así el "nuevo" vive donde ocurre el chat, es idéntico en los tres roles (misma
  vista) y queda a un dedo en móvil sin tener que subir hasta el membrete.
- El modal reutiliza el **patrón ya establecido** de modales Alpine del proyecto
  (`.modal__fondo` + `.tarjeta .modal__caja` + `x-show` + `x-cloak`, igual que el wizard
  de inscripciones en `entrenador/inscripciones/index.blade.php:47-56`).
- El contenido del directorio dentro del modal es **el mismo HTML** de hoy
  (`mensajes/index.blade.php:68-106`), solo reubicado y sin la clase `.chat__cuerpo-lista`
  que le daba `flex:1` (dentro del modal el scroll lo maneja `.modal__caja`).

### 3.2 Estado del componente Alpine (`mensajes.js`)

| Hoy | Propuesta |
|---|---|
| `pestaña: 'chats'` (`:20`) | **Se elimina** (ya no hay pestañas) |
| `nuevoAbierto: false` | **Se añade** — abre/cierra el modal |
| `cargarDirectorio()` (`:108`) | Se conserva; se llama al **abrir** el modal (y en el primer abierto se reusa; al cerrar se resetea `filtroRol`/`busqueda`) |
| `nuevaConversacion(usuarioId)` (`:120`) | Cambia `this.pestaña = 'chats'` (`:124`) por `this.nuevoAbierto = false`. El resto intacto (POST `conversar` → refresca lista → `abrir(id)`) |
| `init()` (`:34`) | Sin cambios |

### 3.3 Foco y cierre

- Al abrir el modal: `$nextTick(() => $refs.busqueda?.focus())` para teclear el nombre
  directo (mismo patrón de `$nextTick` que ya usa `mensajes.js:65`).
- Cierre: `@keydown.escape.window="nuevoAbierto = false"` en la raíz `.chat` (patrón de
  `entrenador/inscripciones/index.blade.php:49`) + `@click.outside` en la caja del modal
  + clic en el fondo (`.modal__fondo`) + botón `.modal__cerrar`.
- Se recomienda `x-teleport="body"` para el modal, igual que el cajón de notificaciones
  (`layouts/panel.blade.php:117-157`): aunque el contenedor actual del chat no crea un
  *containing block* para `position:fixed`, el teleport es el patrón probado del proyecto
  y evita sorpresas si algún día `.chat` o un ancestro gana `backdrop-filter`/`transform`.

---

## 4. Adaptación responsive (móvil / tablet / PC)

El chat mantiene su comportamiento actual; **solo cambia dónde vive el directorio**.

| Pantalla | Comportamiento |
|---|---|
| **PC (> 900 px)** | Dos columnas (`chat` grid 340px + 1fr, `panel.css:1254-1260`). "+" visible en la cabecera de la lista; el modal abre centrado (`.modal__caja`, 34–48 rem) |
| **Tablet / móvil apaisado (≤ 900 px)** | El chat pasa a una columna; el hilo se muestra al abrirlo (`.chat.is-hilo-abierto`, `panel.css:1261-1266`). El "+" queda en la cabecera de la lista; al abrir un hilo, "Volver" regresa a la lista donde sigue el "+". El modal se adapta solo (`.modal__caja` es `width:min(48rem,100%)`) |
| **Móvil vertical (≤ 480 px)** | El modal baja a `max-height:82dvh` (`panel.css:859-861`) y el contenido (filtros que envuelven + buscador + lista de contactos) hace scroll **dentro del modal**. El botón "+" debe tener blanco táctil ≥ 40 px |

Puntos de detalle para el ejecutor:

- **Filtros en el modal:** en ancho de modal (≥ ~480 px) los chips de rol y el buscador
  pueden ir **en la misma fila** (filtros izquierda, buscador derecha con `flex-wrap`); en
  móvil se apilan (comportamiento actual de `.chat__filtros`, `panel.css:1375-1398`). No
  requiere breakpoints nuevos si se reutilizan las clases.
- **Lista dentro del modal:** reutilizar `.chat__lista` (scroll propio) y `.chat__contacto`.
  La fila de contacto (avatar + nombre/rol + WhatsApp + "Hablar") ya funciona angosta;
  asegurar `min-height` táctil en "Hablar" (`.chat__hablar`), consistente con el estándar
  de 40 px que ya exige el proyecto para botones en tablas (`panel.css:488-492`).
- **El "+" nunca se pierde:** en móvil, si hay un hilo abierto se llega a él volviendo a la
  lista; no se propone un botón flotante adicional (menos ruido, el flujo "Volver → +" es
  el estándar de los chats).

---

## 5. Cambios por archivo

> Todos los cambios son **frontend**. `npm run build` obligatorio tras tocar CSS/JS.

### 5.1 `resources/views/mensajes/index.blade.php` (EDITAR)

1. Sustituir el bloque `.chat__pestanas` (`:32-37`) por:
   ```html
   <div class="chat__cabecera-lista">
       <span class="chat__titulo-lista">Conversaciones</span>
       <button type="button" class="btn btn--fuego chat__nuevo"
               @click="nuevoAbierto = true; cargarDirectorio()"
               aria-label="Nuevo mensaje" title="Nuevo mensaje">
           <x-icono nombre="agregar" />
       </button>
   </div>
   ```
2. Quitar el `x-show="pestaña === 'chats'"` de la lista (`:40`) y el envoltorio
   `x-show="pestaña === 'directorio'"` (`:67`) junto con todo su contenido
   (`:66-107`) → pasa al modal.
3. Actualizar el vacío de la lista (`:60-62`): *"Sin conversaciones todavía. Toca + para
   escribir a alguien."*
4. En la raíz `.chat` (`:27`), añadir `@keydown.escape.window="nuevoAbierto = false"`.
5. Añadir el modal al final del `.chat` (o con `x-teleport="body"`), copiando el
   contenido del directorio actual (`:68-106`) dentro de:
   ```html
   <div class="modal__fondo" x-show="nuevoAbierto" x-cloak @click="nuevoAbierto = false">
       <div class="tarjeta modal__caja modal__caja--ancho chat-directorio"
            role="dialog" aria-modal="true" aria-label="Nuevo mensaje"
            @click.outside="nuevoAbierto = false">
           <div class="modal__cabecera">
               <h3>Nuevo mensaje</h3>
               <button class="modal__cerrar" type="button" @click="nuevoAbierto = false" aria-label="Cerrar">
                   <x-icono nombre="cerrar" />
               </button>
           </div>
           {{-- filtros + buscador + lista de contactos (contenido actual de :68-106) --}}
       </div>
   </div>
   ```
   - En el buscador del modal: `x-ref="busqueda"` para el foco inicial.
   - El `@click="nuevaConversacion(u.id)"` de "Hablar" se mantiene igual.

### 5.2 `resources/js/mensajes.js` (EDITAR)

1. `pestaña: 'chats'` (`:20`) → eliminar; añadir `nuevoAbierto: false`.
2. `cargarDirectorio()` (`:108-118`): conservar. En el manejador de apertura del botón
   "+" se resetea `filtroRol = ''`, `busqueda = ''` y se llama `cargarDirectorio()` +
   `$nextTick` para enfocar `$refs.busqueda`.
3. `nuevaConversacion` (`:120-130`): `this.pestaña = 'chats'` (`:124`) →
   `this.nuevoAbierto = false`. El resto (POST → refrescar → abrir hilo) sin cambios.

### 5.3 `resources/css/panel.css` (EDITAR)

1. Sustituir el bloque `.chat__pestanas` (`:1278-1296`) por la cabecera de lista:
   ```css
   .chat__cabecera-lista {
       display: flex; align-items: center; justify-content: space-between; gap: var(--e-3);
       padding: var(--e-3);
       border-bottom: 1px solid var(--acero);
   }
   .chat__titulo-lista {
       font-family: var(--f-display); font-size: var(--t-base); text-transform: uppercase;
       letter-spacing: .04em; color: var(--hueso);
   }
   .chat__nuevo {
       flex: none; width: 40px; height: 40px; padding: 0;
       display: grid; place-items: center; border-radius: var(--r-md);
   }
   .chat__nuevo svg { width: 1.1em; height: 1.1em; }
   ```
   (Todo de los tokens: sin literales de color/radio nuevos.)
2. Reutilizar sin cambios `.chat__filtros`, `.chat__filtros-roles`, `.chat__busqueda`,
   `.chat__lista`, `.chat__contacto`, `.chat__avatar`, `.chat__wa`, `.chat__hablar`.
3. Solo si hace falta, un ajuste menor para el directorio dentro del modal (p. ej.
   separar la lista del filtro con `gap` y asegurar el scroll interno de `.chat__lista`),
   bajo un nombre de contenedor nuevo (`.chat-directorio`) para no afectar la vista del
   listado.

---

## 6. Flujos resultantes (los tres paneles)

### Admin / Recepción
1. Abre **Mensajes** (enlace lateral, `enlace-mensajes.blade.php`). Ve "Conversaciones" + "+".
2. Pulsa **"+"** → modal "Nuevo mensaje" con filtros (Todos/Clientes/Entrenadores/Admin) y
   buscador. *Nota actual (sin cambios): el directorio lista usuarios de la **sede activa**;
   sus hilos en la columna izquierda siguen mostrando los de todas sus sedes con la etiqueta
   de sede* (`MensajeController::serializarLista`, líneas 182-204).
3. Pulsa "Hablar" (o el contacto) → se abre/reutiliza el hilo, el modal se cierra y el chat
   queda en el hilo. La conversación aparece en "Conversaciones".

### Entrenador
Igual: **Mensajes → "+" → filtro/búsqueda → Hablar**. Sin diferencias de código respecto al
admin (misma vista); solo el directorio no le ofrece roles que no existan en su caso.

### Cliente
Igual: **Mensajes → "+" → filtro/búsqueda → Hablar**. El atajo WhatsApp de cada contacto
se conserva dentro del modal.

---

## 7. Mejoras menores (opcionales, recomendadas)

- **Prefetch del directorio** en `init()` (una sola carga al entrar a /mensajes), para que
  el modal se abra al instante. Coste: una petición extra al entrar; se puede omitir.
- **Contador de "no leídos" en la cabecera de lista** — no es necesario: ya vive en la
  barra lateral y en la campanita. Se deja como está.
- **Vaciar/resetear el directorio al cerrar el modal** para que la próxima apertura empiece
  limpia (filtro "Todos", sin búsqueda). Recomendado; una línea en el manejador de cierre.
- **Accesibilidad:** `role="dialog" aria-modal="true" aria-label="Nuevo mensaje"` en el
  modal y `aria-expanded`/`aria-controls` en el botón "+" (opcional).

---

## 8. Lo que NO cambia (reglas del proyecto)

- **Sin cambios de backend, rutas, controlador, modelos ni migraciones.** El plan es 100 %
  frontend y no altera el contrato de ningún endpoint JSON.
- **Sin Tailwind ni dependencias nuevas:** se reutilizan `.btn`, `.modal__*`, `.tarjeta`,
  `.chat__*` y el icono `agregar` existente.
- **El chat y su estructura interna (hilo, burbujas, polling, WhatsApp) no se tocan.**
- **El aislamiento multi-sede se respeta:** `listaConversaciones` (admin, todas sus sedes)
  y `directorio` (sede activa) siguen exactamente iguales.
- **Ningún color, tamaño ni duración literal:** todo desde los tokens (`tokens.css`).
- Tras los cambios: **`npm run build`** (o `npm run dev` corriendo) para reconstruir.

---

## 9. Riesgos y puntos de atención

1. **Posicionamiento `fixed` del modal.** Si se elige no usar `x-teleport`, verificar que
   ningún ancestro tenga `backdrop-filter`/`transform` que actúe de *containing block*
   (hoy `.panel__cabecera` sí tiene `backdrop-filter`, pero es hermana, no ancestro de
   `.chat`). Por eso se recomienda teleport (precedente: campanita, `panel.blade.php:117`).
2. **Foco del buscador en móvil:** enfocar al abrir dispara el teclado virtual; es
   deseable, pero si molesta en móvil, enfocar solo cuando el modal se abra sin tap directo
   al buscador. Decisión menor del ejecutor.
3. **Regresión en el vacío de la lista:** el texto del estado vacío ahora apunta al botón
   "+"; asegurarse de que el "+" está visible aunque no haya conversaciones (sí, vive en la
   cabecera, fuera de la lista).
4. **`pestaña` removida:** comprobar que nada más en el código referencia `pestaña`
   (grep: solo `mensajes/index.blade.php` y `mensajes.js` — verificado).
5. **Verificación:** `php artisan test` está roto de base (SQLite); la comprobación es con
   datos demo y recorrido manual (`migrate:fresh --seed`, `npm run build`), patrón del proyecto.

---

## 10. Checklist final de implementación

- [ ] La pestaña "Nuevo" desaparece; la columna izquierda muestra solo "Conversaciones" + botón "+".
- [ ] El "+" abre el modal "Nuevo mensaje" con los filtros de rol, el buscador y los contactos actuales.
- [ ] "Hablar"/contacto crea o reutiliza la conversación (`mensajes.conversar`), cierra el modal y abre el hilo.
- [ ] El hilo (abrir, polling, enviar, leído, WhatsApp) funciona igual que antes.
- [ ] El modal se cierra con Esc, clic fuera, clic en el fondo y el botón cerrar.
- [ ] El estado `pestaña` desapareció de `mensajes.js` y de la vista (sin referencias huérfanas).
- [ ] Móvil (≤ 480 px), tablet (≤ 900 px) y PC: el "+" es alcanzable, el modal no desborda y la lista de contactos scrollea dentro.
- [ ] Vacío de la lista: apunta al botón "+".
- [ ] `npm run build` limpio; los tres paneles (admin, entrenador, cliente) muestran la misma mensajería.
- [ ] Sin cambios en backend/rutas; `php artisan route:list` idéntico.

---

## Referencias clave verificadas

- `resources/views/mensajes/index.blade.php` (pestañas `:32-37`, chats `:39-64`, directorio `:66-107`, hilo `:111-162`)
- `resources/js/mensajes.js` (`pestaña` `:20`, `nuevaConversacion` `:120-130`, `cargarDirectorio` `:108-118`)
- `resources/css/panel.css` (mensajería `:1250-1555`, pestañas `:1278-1296`, filtros `:1374-1398`, responsive `:1261-1266`, modales `:839-864`)
- `app/Http/Controllers/MensajeController.php` (lista multi-sede `:166-204`, directorio `:118-146`)
- `routes/web.php:76-82` · `resources/views/layouts/panel.blade.php` (teleport de notificaciones `:117-157`)
- `resources/views/layouts/partials/enlace-mensajes.blade.php` · `resources/views/layouts/partials/panel-nav.blade.php` (49, 108, 140)
- `resources/views/components/icono.blade.php` (icono `agregar` `:33`) · `resources/css/tokens.css`
- Precedente de modal Alpine: `resources/views/entrenador/inscripciones/index.blade.php:47-56`
