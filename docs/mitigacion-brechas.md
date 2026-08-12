# Mitigación de brechas funcionales

Plan para cerrar las 4 brechas reportadas antes de dedicarse al diseño
visual. **Planes queda fuera de este plan a propósito** — ya está completo
y editable desde `/panel/planes`, no requiere ningún cambio.

Fuera de alcance de este documento: el pase de diseño responsive/móvil.
Ese viene después de que esto esté cerrado, como su propio trabajo.

## Estado de la base de datos

Reseteada hoy (`php artisan migrate:fresh` + `RolePermissionSeeder` +
`SpartaGymSeeder` + `ExerciseSeeder`, sin `DemoSeeder`). Queda: 1 gimnasio,
4 planes, biblioteca de ejercicios compartida, y una sola cuenta
(`admin@spartagym.pe` / `sparta2026`). Cero socios, cero pagos, cero
entrenadores — para que el dueño cargue todo manual desde el panel.

---

## Parte H — Editor de contenido de la landing

### Por qué

`AGENTS.md` dice explícito que FAQs, testimonios, galería, instalaciones e
"historia" deben editarse sin tocar código. Hoy no hay ninguna pantalla
para eso — solo Planes y Entrenadores (que también alimentan la landing)
son editables.

### Diseño

Cuatro recursos, mismo patrón que `PlanController` (el CRUD más simple que
ya existe — copiarlo como plantilla):

- `Admin\FaqController` — `Faq` (pregunta, respuesta, orden, publicada)
- `Admin\TestimonialController` — `Testimonial` (nombre, texto, foto opcional, publicado).
  **Decisión confirmada con el dueño:** además de que el admin cargue
  testimonios a mano, cada socio puede escribir el suyo desde su propio
  panel de cliente (`cliente/dashboard` o una pestaña nueva). Al enviarlo
  se guarda con `is_published = false` — **no aparece en la landing hasta
  que el admin lo aprueba** desde `admin.testimonios.index` (un botón
  "Publicar" además del CRUD normal). Esto necesita: un formulario nuevo
  en el panel de cliente, vincular el `Testimonial` al `member_id` de
  quien lo escribió, y en `admin.testimonios.index` separar visualmente
  los pendientes de aprobación de los ya publicados.
- `Admin\FacilityController` — `Facility` (instalaciones: nombre, descripción, foto).
  Ojo: la sección pública de instalaciones se eliminó de la landing (ver
  nota más abajo) — este CRUD sigue teniendo sentido para lo que se
  gestiona puertas adentro, pero ya no alimenta ninguna vista pública.

~~`Admin\GalleryController`~~ — **eliminado de este plan.** La sección
"La sala · Así se ve" (galería de fotos) se quitó por completo de la
landing pública a pedido del dueño. No hace falta un editor para algo que
ya no se muestra en ningún lado.

Todas con permiso `web.editar` (ya existe en el seeder, sin asignar a
ningún rol excepto admin por definición).

Rutas en `routes/admin.php`, dentro del grupo existente:
```php
Route::resource('faqs', FaqController::class)->except(['show'])->middleware('permiso:web.editar');
Route::resource('testimonios', TestimonialController::class)->except(['show'])->middleware('permiso:web.editar');
Route::resource('instalaciones', FacilityController::class)->except(['show'])->middleware('permiso:web.editar');
```

Vistas: mismo patrón `index.blade.php` + `form.blade.php` que `admin/planes/*`.

Un enlace nuevo en el menú, grupo "Configuración": **"Contenido web"** —
puede ser una sola pantalla con pestañas (FAQs / Testimonios / Instalaciones)
en vez de 3 enlaces sueltos, para no saturar el sidebar. Sugerido pero no
obligatorio — decidir al implementar.

### Checklist

- [ ] Crear/editar/desactivar una FAQ → aparece/desaparece en la landing
- [ ] Mismo para testimonios
- [ ] Instalaciones se administra desde el panel aunque ya no tenga vista
      pública (la sección se eliminó de la landing) — confirmar con el
      dueño si vale la pena construir este CRUD o si se puede recortar
      de Parte H también
- [ ] El testimonio que envía un socio desde su panel de cliente aparece
      como "pendiente" en `admin.testimonios.index`, y sólo se ve en la
      landing tras aprobarlo
- [ ] Sólo el admin (o quien tenga `web.editar`) ve el enlace en el menú

---

## Parte I — Inventario y ventas

### Por qué

`Product` y `Sale` ya existen como modelos, con migraciones y permisos
(`inventario.ver`, `inventario.gestionar`, `ventas.registrar`) ya en el
seeder y asignados a recepción — pero cero controlador, cero vista. Si el
gimnasio vende suplementos o accesorios, hoy no hay dónde registrarlo.

### Diseño

- `Admin\ProductController` — CRUD de productos (nombre, precio, stock,
  categoría). El stock es un saldo (`products.stock`), la verdad vive en
  `stock_movements` — **no escribir `stock` directo, siempre a través de
  un movimiento** (ver la regla ya documentada en `AGENTS.md`).
- `Admin\SaleController` — registrar una venta (elegir producto(s),
  cantidad, método de pago — mismo patrón que `PaymentController`), lo
  que descuenta stock vía un `StockMovement` de salida.
- Vista `admin/inventario/index.blade.php` (lista de productos + alerta de
  "necesita reposición", ya existe `Product::getNecesitaReposicionAttribute()`)
  y `admin/ventas/index.blade.php` (historial de ventas, mismo patrón que
  Pagos).
- Enlace nuevo en el menú, grupo "Dinero" o uno nuevo "Inventario".

### Checklist

- [ ] Crear un producto, registrar una venta → el stock baja según lo
      vendido, no se edita el número directo
- [ ] "Necesita reposición" se marca solo cuando el stock cae bajo el
      mínimo configurado
- [ ] Historial de ventas filtrable por fecha, igual que Pagos

---

## Parte J — QR real (imagen escaneable)

### Por qué

`Member.qr_token` existe y se usa para marcar asistencia, pero hoy solo se
muestra como texto plano en la ficha del socio — no hay forma de imprimir
una tarjeta o carnet con un código que de verdad se pueda escanear con
cámara.

### Diseño

Sin dependencias de servidor pesadas (coherente con el resto del
proyecto): generar el QR **en el navegador**, no en PHP.

- Añadir una librería JS ligera de generación de QR (ej. `qrcode` en npm,
  ~3kb) a `package.json`.
- En `admin/socios/show.blade.php`, donde hoy se muestra el token como
  texto, renderizar un `<canvas>` o `<svg>` con el QR generado a partir de
  `qr_token`, vía un pequeño script en `resources/js/`.
- Botón "Descargar / Imprimir carnet" — reusa el patrón ya construido en
  `admin/reportes/imprimir-*.blade.php` (vista standalone blanco sobre
  negro con botón `window.print()`), pero con el QR embebido.

### Checklist

- [ ] La ficha del socio muestra un QR real, no el texto del token
- [ ] Escanear ese QR con el lector/cámara que use el gimnasio marca
      asistencia correctamente (ya funciona por texto — sólo hay que
      confirmar que el valor codificado en la imagen es idéntico al token)
- [ ] Vista de impresión de carnet individual

---

## Parte K — Configuración general del gimnasio

### Por qué

`GymController` (creado para multi-sede) sólo expone nombre, dirección,
ciudad, teléfono y correo. El modelo `Gym` tiene más campos que hoy nadie
puede editar desde el panel: `schedule` (horario), `socials` (redes
sociales), `logo_path`, `description`, `tagline`, `currency`, `timezone`.

### Diseño

Extender `GymController::validarDatos()` y el formulario
`admin/sedes/form.blade.php` con:

- `tagline`, `description` — campos de texto
- `logo_path` — subida de archivo (mismo patrón que la foto del socio en
  `MemberController::guardarFoto()`)
- `schedule` — un mini-formulario repetible (día / abre / cierra), similar
  al patrón de "una línea por beneficio" que ya usa `PlanController` para
  `features`, pero con 3 campos por fila en vez de uno
- `socials` — 4 campos de texto (instagram, facebook, tiktok, youtube)

No hace falta permiso nuevo — reusa `sedes.gestionar`, que ya existe.

### Checklist

- [ ] Cambiar el horario desde el panel se refleja en la landing
- [ ] Subir un logo nuevo lo actualiza en la landing y en el panel
- [ ] Los campos de redes sociales aceptan vacío sin romper el formulario

---

## Orden sugerido

1. **Parte H** (contenido de landing) — la más urgente si el plan
   inmediato es trabajar el diseño de la web pública.
2. **Parte K** (config general) — pequeña, se apoya en el mismo
   `GymController` que ya existe.
3. **Parte J** (QR real) — acotada, una sola pantalla.
4. **Parte I** (inventario/ventas) — la más grande, dos recursos nuevos
   completos. Puede esperar si el gimnasio no vende productos todavía.
