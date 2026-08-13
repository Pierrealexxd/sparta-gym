# PLAN DE IMPLEMENTACIÓN — Landing: modales móviles + sección "Contacto" editable desde el admin

> **Fecha:** 2026-08-13 · **Modo:** análisis → plan (no implementación) · **Regla:** no rehacer lo que ya funciona.
>
> Este documento es el resultado de leer las secciones públicas de la landing y el módulo
> de contenido web del admin (rutas, controlador, vistas, CSS, modelo `Gym`) y la propuesta
> técnica para que **otro agente lo ejecute después de que este plan sea aprobado**.
> Nada de lo aquí descrito está implementado todavía.

---

## 0. Alcance (dos trabajos independientes)

1. **Parte A — Modales informativos de la landing en móvil.** Las ventanas emergentes de las
   secciones **"Por qué aquí / Lo que sí hacemos"** (beneficios) y **"Para empezar / Tres
   semanas"** (guías) se ven "comprimidas" en la vista de móvil. Se reorganiza el modal para
   que la información respire y se lea bien.
2. **Parte B — Sección "Contacto" editable en el módulo "Contenido web" del admin.** Se agrega
   una pestaña **"Contacto"** al módulo que el proyecto llama "Contenido web" (el nav dice
   *"páginas públicas"*), para que el admin edite la sección **"Contacto / Ven a verlo"** de la
   landing tal cual se muestra.

> Todo lo demás de la landing y del panel está bien y **no se toca**.

---

# PARTE A — Modales informativos en móvil

## A.1 Estado actual (verificado en código)

Dos secciones de la landing usan el mismo componente `.modal-info`, que **solo se abre en
móvil** (tap con `window.innerWidth <= 740`):

| Sección | Vista | Modal | Referencia |
|---|---|---|---|
| **Beneficios** — "Por qué aquí / Lo que sí hacemos" | `resources/views/landing/sections/beneficios.blade.php` | `modal-info` | tarjetas `:29-42`, click `:35`, modal `:45-66` |
| **Guías** — "Para empezar / Tres semanas" | `resources/views/landing/sections/guias.blade.php` | `modal-info` | tarjetas `:49-62`, click `:51`, modal `:77-102` |

En el **bento de beneficios**, en móvil las 4 piezas se aplastan a una rejilla 2×2 de
solo icono + título (`landing.css:575-598`) y el texto se lee en el modal. En **guías**,
cada tarjeta se comprime a número + título (`landing.css:1298-1301`) y la lista completa
se lee en el modal.

**El modal hoy** (`landing.css:600-651`):

```css
.modal-info { position: fixed; inset: 0; z-index: var(--z-modal);
              display: grid; place-items: center; padding: var(--e-5); }
.modal-info__caja { width: min(420px, 100%); max-height: 80vh; overflow-y: auto;
                    display: grid; gap: var(--e-4);
                    padding: var(--e-6) var(--e-5) var(--e-5);
                    background: var(--grafito); border: 1px solid var(--acero-claro);
                    border-radius: var(--r-lg); box-shadow: var(--s-xl); }
.modal-info__cuerpo h3 { font-family: var(--f-display); font-size: var(--t-xl);
                         text-transform: uppercase; padding-right: 2rem; }
```

### A.1.1 Por qué se ve "comprimido"

- **La tarjeta flota centrada con poco aire:** `padding: var(--e-5)` (24 px) en el contenedor
  y `width: min(420px, 100%)` hacen que en un teléfono de 360 px el modal casi toque los
  bordes; el texto interno queda apretado contra el marco.
- **Titular apretado contra el botón de cerrar:** `h3` con `padding-right: 2rem` cede espacio
  al botón flotante de 40 px, pero el párrafo/listado de abajo no tiene el mismo respiro.
- **Jerarquía plana:** en beneficios, icono (46 px) → h3 → p en un bloque sin separación
  clara; en guías, número → h3 → lista de 3 ítems con `gap: var(--e-3)` (12 px) que parece
  un texto corrido en pantallas angostas.
- **Ritmo vertical corto:** `max-height: 80vh` + `gap: var(--e-4)` (16 px) entre las piezas
  del contenido, suficiente para una tarjeta pequeña pero pobre para leer un bloque.

### A.1.2 El modal de video de la biblioteca NO se toca

`ejercicios.blade.php` usa su propio componente `.video` (`:80-93`), distinto de
`.modal-info`. Queda intacto.

## A.2 Propuesta de solución — bottom sheet mobile-first

Como el modal **solo se abre en móvil** (≤ 740 px), lo mejor para leer un bloque de texto
es el patrón **bottom sheet**: una hoja que sale desde abajo, ocupa el ancho completo,
redondea solo las esquinas superiores, respeta el *home indicator* de iOS y deja que el
contenido haga scroll cómodo. En > 740 px (fallback defensivo, hoy inalcanzable) se
mantiene la tarjeta centrada actual.

### A.2.1 Cambios de CSS (`resources/css/landing.css`, bloque `:600-651`)

Reemplazar el bloque de `.modal-info` por:

```css
.modal-info {
    position: fixed; inset: 0; z-index: var(--z-modal);
    display: grid; place-items: center; padding: var(--e-5);
}
.modal-info__fondo { /* sin cambios */ }
.modal-info__caja {
    position: relative;
    width: min(420px, 100%);
    max-height: 80vh;
    overflow-y: auto;
    display: grid;
    gap: var(--e-5);
    padding: var(--e-6) var(--e-5) var(--e-5);
    background: var(--grafito);
    border: 1px solid var(--acero-claro);
    border-radius: var(--r-lg);
    box-shadow: var(--s-xl);
}

/* Bottom sheet: solo aplica por debajo de 740px (cuando el modal de verdad abre). */
@media (max-width: 740px) {
    .modal-info { padding: 0; align-items: end; justify-items: stretch; }
    .modal-info__caja {
        width: 100%; max-width: none; max-height: 88dvh;
        border-radius: var(--r-lg) var(--r-lg) 0 0;
        border-bottom: 0;
        padding: var(--e-6) var(--e-5) var(--e-7);
        padding-bottom: calc(var(--e-7) + env(safe-area-inset-bottom));
    }
}
```

**Ajustes de contenido** (para que la información "respire"):

```css
.modal-info__cuerpo { display: grid; gap: var(--e-5); }
.modal-info__cuerpo .beneficio__icono { margin-bottom: 0; } /* el gap de arriba ya separa */
.modal-info__cuerpo h3 {
    font-size: var(--t-2xl);        /* sube de t-xl a t-2xl en el sheet */
    padding-right: var(--e-7);      /* más margen frente al botón de cerrar */
    line-height: 1.05;
}
.modal-info__cuerpo p { font-size: var(--t-base); line-height: 1.65; max-width: 44ch; }
.modal-info__cuerpo .guia__lista { gap: var(--e-4); }   /* 16 px entre ítems */
.modal-info__cuerpo .guia__lista li { font-size: var(--t-base); line-height: 1.6; }
```

**Transición:** mantener las clases existentes (`modal-info__entra…`), pero en el breakpoint
móvil cambiar el desplazamiento de `translateY(14px) scale(.97)` a un deslizamiento real de
hoja `translateY(100%)` (se puede reutilizar el mismo nombre de clase con otra declaración
dentro del `@media`, como ya hace el proyecto con otras animaciones).

### A.2.2 Cambios opcionales en Blade

- **Beneficios** (`beneficios.blade.php`): sin cambios estructurales; el cuerpo del modal
  (`:56-64`) ya es icono + h3 + p. El `gap: var(--e-5)` del `.modal-info__cuerpo` lo ordena.
- **Guías** (`guias.blade.php`): sin cambios; el cuerpo (`:88-100`) ya es número + h3 + lista.
  Solo se beneficia del CSS anterior.
- *(Opcional, si se quiere que el usuario entienda que las tarjetas son tocables)*: una
  flecha/chevron "Ver más" discreta en las tarjetas del bento y de guías en móvil. No es
  necesario para arreglar lo reportado.

### A.2.3 Criterios de aceptación (Parte A)

- [ ] En móvil (≤ 740 px) los modales de beneficios y guías salen como hoja desde abajo,
      ancho completo, esquinas superiores redondeadas y sin bordes laterales/inferior.
- [ ] El contenido no toca los bordes: padding generoso + respiro del *home indicator* (iOS).
- [ ] El titular no choca con el botón de cerrar; párrafos y listas con interlineado cómodo.
- [ ] El sheet hace scroll interno cuando el contenido es más alto que `88dvh`.
- [ ] Escape y clic en el fondo siguen cerrando; `prefers-reduced-motion` respetado
      (la transición se desactiva en el bloque existente de `landing.css`).
- [ ] En > 740 px nada cambia (fallback centrado) y el modal de video (`.video`) intacto.

---

# PARTE B — Sección "Contacto" editable en "Contenido web"

## B.1 Estado actual (verificado en código)

### B.1.1 La sección de contacto de la landing

`resources/views/landing/sections/contacto.blade.php` dibuja toda la información de la sección
**a partir del `Gym` activo** (el que resuelve `GymContext::current()`), no de texto incrustado:

| Elemento visible | Fuente | Blade |
|---|---|---|
| Cabecera: eyebrow "Contacto", h2 "Ven a verlo", lead "Pásate cuando quieras…" | **Texto fijo** hardcodeado | `contacto.blade.php:4-6` |
| Dirección + ciudad | `$gym->address`, `$gym->city` | `:15` |
| Teléfono (link `tel:`) | `$gym->phone` | `:19-27` |
| Correo (link `mailto:`) | `$gym->email` | `:29-37` |
| Horario (filas día–abre–cierra) | `$gym->schedule` (JSON `[{dia,abre,cierra}]`) | `:39-52` |
| Mapa (OpenStreetMap) | `$gym->latitude`, `$gym->longitude` | `:121-129` |

El formulario de la derecha (nombre/teléfono/correo/plan/mensaje) ya funciona y **no se toca**.

### B.1.2 El modelo `Gym` ya guarda todo esto

`app/Models/Gym.php` (`$fillable :18-23`, casts `:25-35`):
`email`, `phone`, `whatsapp`, `address`, `city`, `latitude`, `longitude`, `schedule` y un
**`settings` JSON (array) que hoy nadie usa** — el lugar natural para la cabecera editable
(eyebrow/título/lead), sin migración nueva.

### B.1.3 El módulo "Contenido web" del admin

- Nav compartido por pestañas: `resources/views/admin/contenido/_pestanas.blade.php`
  (FAQs · Testimonios · Biblioteca · Recetas).
- Las rutas de contenido viven en el grupo `permiso:web.editar`
  (`routes/admin.php:133-144`, que hoy agrupa faqs y testimonios); el permiso está
  definido en `RolePermissionSeeder:61` y lo tiene el admin (no se enumera; puede todo).
- Cada pestaña tiene su propio controlador/vista CRUD (patrón `Admin\FaqController`).
- **Hoy la dirección/teléfono/correo/horario solo se editan en "Configuraciones → Sedes"**
  (`admin/sedes/form.blade.php`, repeater de horario en `:49-71`). Pero ahí se editan datos de
  **la sede (alta/edición de sucursales)**, con campos que no incluyen lat/long ni la cabecera
  de la sección — y no es el lugar que el dueño buscaría para "lo que se ve en la web pública".
  La pestaña **"Contacto"** los reúne en el módulo correcto.

## B.2 Propuesta de solución

Nueva pestaña **"Contacto"** dentro de "Contenido web", que edita el **gym activo**
(`GymContext::current()`) y se refleja tal cual en la landing.

### B.2.1 Ruta (`routes/admin.php`, dentro del grupo `permiso:web.editar`)

```php
Route::get('contenido/contacto', [ContactoController::class, 'editar'])->name('contenido.contacto');
Route::post('contenido/contacto', [ContactoController::class, 'guardar'])->name('contenido.contacto.guardar');
```

> Prefijo `contenido/` (mismo espacio de nombres que `admin.faqs.*`), dentro del grupo
> `permiso:web.editar` (`routes/admin.php:133-144`), sin recurso CRUD porque es un
> formulario de una sola instancia (el gym activo). Sin nuevas migraciones.

### B.2.2 Controlador nuevo `App\Http\Controllers\Admin\ContactoController`

```php
public function editar(): View
{
    return view('admin.contenido.contacto.form', ['gym' => GymContext::current()]);
}

public function guardar(Request $request): RedirectResponse
{
    $gym = GymContext::current();
    abort_unless($gym, 404);

    $datos = $request->validate([/* igual a GymController::validarDatos() */]);
    // schedule: filtrar filas vacías (mismo collect()->filter() de GymController:113-116).
    // settings.contacto: fusionar eyebrow/titulo/lead en el JSON existente.
    $gym->update([...$datos, 'settings' => [...$gym->settings, 'contacto' => [...]]]);

    return redirect()->route('admin.contenido.contacto')->with('exito', 'Contacto actualizado.');
}
```

- Reutiliza exactamente la validación de `Admin\GymController::validarDatos()` (`:87-123`):
  `address`, `city`, `phone`, `email`, `schedule.*` + nuevo `latitude`/`longitude`
  (`numeric` entre `-90..90` / `-180..180`) y `contacto.eyebrow|titulo|lead` (`string`, máx).
- **No toca `GymController` ni el módulo Sedes.** Los cambios quedan sobre la misma fila del
  gym; ambas puertas de edición conviven.

### B.2.3 Vista nueva `resources/views/admin/contenido/contacto/form.blade.php`

Mismo lenguaje visual que el resto del panel (`extends layouts.panel`, `tarjeta formulario-panel`,
`.campo`, `.fila-borrable`):

```
@section('titulo', 'Contacto')

<form class="tarjeta formulario-panel" method="POST" action="{{ route('admin.contenido.contacto.guardar') }}">
    {{-- Cabecera de la sección (se guarda en settings.contacto) --}}
    fila:  [ Eyebrow ]  [ Título ]
    campo: [ Lead (textarea) ]

    {{-- Datos --}}
    fila:  [ Dirección ]  [ Ciudad ]
    fila:  [ Teléfono ]   [ Correo ]
    fila:  [ WhatsApp ]   [ — (opcional: link wa.me en la landing) ]

    {{-- Horario: mismo repeater Alpine que admin/sedes/form.blade.php:49-71 --}}
    [ día | abre | cierra ] ×N  + "Añadir franja"

    {{-- Mapa --}}
    fila:  [ Latitud ]  [ Longitud ]
    (pista: la landing muestra el mapa solo si ambos tienen valor)

    acciones: Cancelar → admin.contenido.contacto · Guardar
</form>
```

- Los valores iniciales salen de `old(..., $gym->...)` (patrón de `sedes/form.blade.php`).
- La cabecera sale de `old('contacto.titulo', $gym->settings['contacto']['titulo'] ?? '')`.

### B.2.4 Pestaña nueva en el nav (`resources/views/admin/contenido/_pestanas.blade.php`)

Añadir al final del `<nav>` (todo el nav ya exige `web.editar`):

```blade
<a class="pestanas__enlace" href="{{ route('admin.contenido.contacto') }}"
   aria-current="{{ request()->routeIs('admin.contenido.contacto') ? 'true' : 'false' }}">Contacto</a>
```

**Detalle del sidebar** (`resources/views/layouts/partials/panel-nav.blade.php:95`): el enlace
lateral "Contenido web" resalta con `aria-current` si la ruta está en
`admin.faqs.*`, `admin.testimonios.*`, `admin.ejercicios.*` o `admin.recetas.*`. Hay que
añadir `'admin.contenido.contacto'` a esa lista para que el ítem del menú siga iluminado
cuando la pestaña "Contacto" esté abierta.

### B.2.5 La landing lee los textos editables (`contacto.blade.php`)

Sustituir la cabecera fija (`:4-6`) por valores con respaldo:

```blade
@php $contacto = $gym->settings['contacto'] ?? []; @endphp
<span class="eyebrow">{{ $contacto['eyebrow'] ?? 'Contacto' }}</span>
<h2>{{ $contacto['titulo'] ?? 'Ven a verlo' }}</h2>
<p class="lead">{{ $contacto['lead'] ?? 'Pásate cuando quieras. La primera visita incluye una vuelta por la sala.' }}</p>
```

- Sin valores guardados → se muestra el texto actual (cero regresión).
- *(Opcional)*: si `$gym->whatsapp` tiene valor, añadir el dato "WhatsApp" con link
  `https://wa.me/…` siguiendo el mismo bloque que el teléfono (`:19-27`).

## B.3 Alcance y no-alcance

**Sí:** pestaña "Contacto", formulario de edición, persistencia en `gyms` (incluida la cabecera
en `settings.contacto`), lectura en la landing, validación.

**No:** no se toca el formulario de envío de la landing, no se migra nada (la columna `settings`
ya existe), no se cambia el módulo Sedes, no se añade multi-sede al formulario (edita el gym
activo; en el caso actual de una sola sede es exactamente el que sirve la web). Si mañana hay
varias sedes, cada una edita la suya desde su propia sede activa.

## B.4 Criterios de aceptación (Parte B)

- [ ] En el panel, "Contenido web" muestra la pestaña **"Contacto"** (solo con `web.editar`).
- [ ] El formulario edita cabecera, dirección, ciudad, teléfono, correo, WhatsApp (opcional),
      horario y coordenadas del mapa, y guarda sin errores de validación.
- [ ] El horario admite varias franjas y descarta filas vacías (mismo comportamiento que Sedes).
- [ ] En la landing, la sección "Contacto / Ven a verlo" refleja los cambios al instante
      (cabecera editable incluida) y, sin tocar nada, sigue mostrando los valores por defecto.
- [ ] Si se rellenan latitud/longitud, el mapa de OpenStreetMap aparece; si no, sigue oculto.
- [ ] `npm run build` limpio; `php artisan route:list` incluye las dos rutas nuevas.

---

## Referencias clave verificadas

- `resources/views/landing/sections/beneficios.blade.php` · `.../guias.blade.php` · `.../contacto.blade.php` · `.../ejercicios.blade.php` (modal `.video` aparte)
- `resources/css/landing.css` (bento `:518-598`, modal-info `:600-651`, guías `:1246-1301`)
- `resources/views/landing/index.blade.php` (orden de secciones)
- `app/Support/GymContext.php` · `app/Models/Gym.php` (fillable/casts, `settings` JSON) · `database/migrations/2026_08_03_000101_create_gyms_table.php:34-37`
- `app/Http/Controllers/Admin/GymController.php` (validación `:87-123`, guardado de schedule) · `resources/views/admin/sedes/form.blade.php` (repeater de horario `:49-71`)
- `app/Http/Controllers/Admin/FaqController.php` (patrón de pestaña) · `routes/admin.php:133-144` (grupo `web.editar`) · `resources/views/admin/contenido/_pestanas.blade.php`
- `resources/views/layouts/partials/panel-nav.blade.php:95` (enlace "Contenido web", `aria-current` a ampliar)
- `resources/css/panel.css` (`.pestanas__*` `:661-672`, `.formulario-panel` `:676-686`)
- `database/seeders/RolePermissionSeeder.php:61` (permiso `web.editar`)
