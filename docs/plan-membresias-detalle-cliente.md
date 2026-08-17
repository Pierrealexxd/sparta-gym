# Plan — Gestión integral de membresías en el detalle del cliente

> **Estado:** Diagnóstico + plan técnico (sin cambios aplicados). Auditoría de solo lectura del 17-08-2026.
> **Destinatario:** agente que implementará los cambios.
> **Regla de oro:** no romper lo que ya funciona. Todo lo que se propone aquí reutiliza columnas, accesos y estilos existentes; **no requiere migraciones**.

---

## 1. Diagnóstico: qué existe hoy

### 1.1 Registro de membresía con fecha de inicio libre — YA EXISTE

- Formulario en `resources/views/admin/clientes/show.blade.php` (pestaña "Membresías", ~línea 92): campos `plan_id`, `starts_at` (input `date`, por defecto hoy), `discount`, `method`. **No hay campo de fecha de fin.**
- `app/Http/Controllers/Admin/MembershipController.php::store` valida `starts_at => ['required', 'date']` — **sin restricción de pasado**: una inscripción hecha fuera del sistema se puede registrar con `starts_at` en el pasado.
- `app/Services/MatriculaService.php::crearMembresia` calcula el vencimiento: `ends_at = Carbon::parse($starts_at)->addDays($plan->duration_days)`. La fecha de fin **siempre se deriva del plan**; no se puede indicar un vencimiento distinto al de `inicio + duración`.

**Conclusión:** registrar con inicio en el pasado ✓. Indicar el vencimiento manualmente ✗ (solo se calcula).

### 1.2 Cálculo automático de días restantes — YA EXISTE

- `app/Models/Membership.php`:
  - `getDiasRestantesAttribute()` → `now()->startOfDay()->diffInDays($this->ends_at, false)` (negativo = vencida).
  - `getEstaVigenteAttribute()`, scopes `vigentes()`, `vencidas()`, `vencenEn(int $dias)`.
- `app/Models/Member.php`:
  - `getDaysLeftAttribute()`, `getIsUpToDateAttribute()`, scopes `porVencer(int $dias = 7)` y `conMembresiaVencida()`.
- Índices ya creados en la migración `2026_08_03_000104_create_plans_and_memberships_tables.php`: `['gym_id', 'status', 'ends_at']` y `['member_id', 'ends_at']`.
- Usos actuales: ficha del cliente ("X días" en el resumen), dashboard admin ("Vencen esta semana" con `porVencer(7)`), dashboard cliente (reloj circular `progreso-kpi__circulo` con `--progreso`).

**Conclusión:** la fecha de fin calculada a partir del inicio ya alimenta el contador de días. Falta presentarlo con jerarquía visual.

### 1.3 Integración WhatsApp — YA EXISTE, pero como banner transitorio

- `resources/views/admin/clientes/show.blade.php` (~líneas 154-205): bloque `@php` que calcula `$membresiaActual`, `$diasRestantes` y `$mostrarWhatsApp = $membresiaActual && $diasRestantes !== null && $diasRestantes <= config('sparta.aviso_vencimiento_dias', 7)`, y pinta un `div.aviso.aviso--whatsapp` con botón `btn--whatsapp` "Enviar WhatsApp" (enlace `https://wa.me/{teléfono}?text={mensaje}` con mensaje personalizado según días restantes: 7-5 / 4-3 / 2-1 / hoy / vencida).
- **Defecto:** `x-data="{ abierto: true }"` + `x-init="setTimeout(() => abierto = false, 3000)"` → **el banner se auto-oculta a los 3 segundos**. No es un botón persistente; un recepcionista que mire la pantalla tarde no lo ve.
- Estilos y assets ya listos: `.btn--whatsapp` y `.aviso--whatsapp` en `resources/css/components.css`; tokens `--whatsapp` / `--whatsapp-hover` en `resources/css/tokens.css` (excepción de marca externa documentada); icono `whatsapp` en `resources/views/components/icono.blade.php`.
- Patrón `wa.me` ya usado en: `resources/views/perfil/index.blade.php`, landing (`contacto.blade.php`), mensajería (`app/Http/Controllers/MensajeController.php` línea ~248, `mensajes/index.blade.php`).
- El plan original de esta función está documentado en `PLAN-VENTAS-CLIENTES.md`, Parte 3 (§5). El implementador debe leerlo.

**Conclusión:** el enlace personalizado existe; falta convertirlo en botón persistente y con el texto pedido ("Enviar recordatorio").

### 1.4 Organización visual — FALTA (hoy es una tabla)

- Las membresías del cliente se listan como **tabla** (Plan / Periodo / Precio / Estado) en `show.blade.php` (~líneas 207-230), igual que en `admin/membresias/index.blade.php`.
- No hay tarjetas de membresía, ni línea de tiempo/cuenta regresiva, ni estado "próxima a vencer".
- El estado guardado es un enum: `activa | vencida | cancelada | congelada` (migración). "Próxima a vencer" **no se guarda**: es un estado derivado (`status === 'activa' && 0 <= dias_restantes <= umbral`).
- Clases de estado existentes en `resources/css/panel.css` (~línea 748):
  - `.estado--activa` → `--ok`
  - `.estado--vencida` → `--alerta`
  - `.estado--cancelada` → `--humo`
  - No existe `.estado--por-vencer` (usar token `--brasa` o `--bronce`).

**Componentes reutilizables** (verificados):
| Componente | Dónde | Para qué sirve acá |
|---|---|---|
| `progreso-kpi__circulo` (CSS, panel.css ~1524) | `cliente/dashboard.blade.php` | Reloj circular de cuenta regresiva (conic-gradient `--brasa`/`--acero`). Reutilizable en la tarjeta de la membresía vigente |
| `.recordatorio` (panel.css ~1541) | `cliente/progreso.blade.php` | Banner compacto con botón a la derecha (`margin-left:auto`); alternativa a `aviso--whatsapp` para el recordatorio |
| `x-icono` (`components/icono.blade.php`) | — | Iconos (`whatsapp`, `reloj`, `campana`, etc.) |
| `x-estado-vacio` | — | Estado vacío de la lista |
| `x-modal-confirmar` | — | Confirmar cancelación |
| `x-discos` | dashboards | Regla AGENTS: las cifras se representan con pilas de discos, no barras de progreso. Opcional para la cifra de días |

---

## 2. Objetivo del plan

1. Permitir registrar una membresía indicando **inicio y (opcional) fin** manual — cubre inscripciones fuera del sistema con periodo distinto al del plan — sin romper el cálculo automático por defecto.
2. Reemplazar la tabla de membresías por **tarjetas con línea de tiempo / cuenta regresiva** que diferencien visualmente: **activa · próxima a vencer · vencida** (y cancelada/congelada), usando el sistema de tokens.
3. Convertir el banner transitorio de WhatsApp en un **botón persistente "Enviar recordatorio"** debajo de "Registrar membresía y pago", que abra `wa.me` en pestaña nueva con mensaje personalizado (cliente + plan + fecha de vencimiento + días restantes).

**Fuera de alcance (mención para el futuro):** envío automático (API WhatsApp Business, cola), recordatorios en el panel del cliente, reuso de las tarjetas en `admin/membresias/index` y en el panel cliente. El diseño de tarjetas debe permitir ese reuso después.

---

## 3. Cambios por capa

### 3.1 Modelo — `app/Models/Membership.php`

No hay migraciones (la columna `ends_at` ya existe). Añadir accesos derivados:

- `getEstadoVisualAttribute(): string` — prioridad:
  1. `cancelada` / `congelada` → tal cual.
  2. `vencida` (almacenado) **o** (`activa` y `dias_restantes < 0`) → `vencida`.
  3. `activa` y `0 <= dias_restantes <= config('sparta.aviso_vencimiento_dias')` → `por-vencer`.
  4. resto → `activa`.
- `getPorcentajeTranscurridoAttribute(): float` — para el rail de tiempo: `clamp(0, (hoy - starts_at) / (ends_at - starts_at) * 100, 100)`; proteger división por cero (`ends_at === starts_at`).

> Alternativa aceptada: resolver `estado_visual` en el controlador o la vista. El acceso en el modelo es preferible porque lo reutilizarán la tarjeta y (después) el listado.

### 3.2 Validación — `app/Http/Controllers/Admin/MembershipController.php::store`

```php
'starts_at' => ['required', 'date'],
'ends_at'   => ['nullable', 'date', 'after_or_equal:starts_at'],
```

- Mantener `starts_at` sin restricción de pasado (inscripciones fuera del sistema).
- `ends_at` opcional: si llega vacío, el servicio lo calcula (comportamiento actual, sin cambios para quien registra normal).

### 3.3 Servicio — `app/Services/MatriculaService.php::crearMembresia`

```php
'starts_at' => $datos['starts_at'],
'ends_at'   => isset($datos['ends_at'])
    ? Carbon::parse($datos['ends_at'])
    : Carbon::parse($datos['starts_at'])->addDays($plan->duration_days),
```

- Defensa en profundidad: si `ends_at` viene pero `<= starts_at`, lanzar `ValidationException` (o ignorarlo y calcular). Recomendado: validar en el servicio con `throw_if`.
- **No tocar** `nuevaMatricula` ni `renovarMembresia` en su firma: ambos pasan `$datosMembresia` a `crearMembresia`, así que el campo nuevo fluye solo. Verificar que el wizard de matrícula (`MatriculaController`/`InscripcionController`) no envíe `ends_at` (no lo envía hoy; no requiere cambios).

### 3.4 Vista — `resources/views/admin/clientes/show.blade.php` (pestaña Membresías)

Reemplazar el bloque de la pestaña (líneas ~91-235) conservando estructura y `@csrf`:

1. **Formulario "Registrar membresía y pago"** (igual, +1 campo):
   - Añadir `<input type="date" name="ends_at">` con etiqueta "Fin (opcional)" y ayuda: *"En blanco: inicio + duración del plan. Úsalo si el cliente pagó un periodo distinto (p. ej. inscripción fuera del sistema)."*
   - Mostrar `@error('ends_at')`.
2. **Botón persistente "Enviar recordatorio"** (reemplaza el bloque `aviso--whatsapp` transitorio):
   - Mismo cálculo `@php` existente (`$membresiaActual`, `$diasRestantes`, `$umbralWhatsApp`, `$mostrarWhatsApp`).
   - Mostrar solo si `$mostrarWhatsApp && $cliente->phone` (membresía vigente en ventana de 7 días o vencida).
   - **Quitar** el `x-data="{abierto:true}"` + `setTimeout(...)` — el bloque debe permanecer visible.
   - Botón con texto **"Enviar recordatorio"** (mantener icono `whatsapp`), `target="_blank" rel="noopener"`.
   - Mensajes: reutilizar el `match(true)` existente tal cual (ya cubre 7-5 / 4-3 / 2-1 / hoy / vencida). Opcional: mover las plantillas a `config/sparta.php` (`'whatsapp_mensajes'`) para editarlas sin tocar la vista — ver §4.
   - Si el cliente no tiene `phone`: no mostrar el botón (o mostrar el aviso sin enlace con texto "Sin teléfono registrado").
3. **Tarjetas de membresía** (reemplaza la tabla):
   - Grid `membresias` (CSS nuevo, §3.5): 1 columna móvil, 2 columnas ≥ 640px, 3 ≥ 1024px.
   - Cada tarjeta `article.tarjeta.membresia`:
     - Cabecera: `plan_name` + badge `estado estado--{{ $mem->estado_visual }}`.
     - **Cuenta regresiva de la vigente**: círculo `progreso-kpi__circulo` (reuso del CSS existente) con `--progreso: {{ $mem->porcentaje_transcurrido }}` y la cifra de días en el centro (mono). Si la membresía no es la vigente, omitir el círculo.
     - **Línea de tiempo (rail)**: barra horizontal `.membresia-rail` dibujada con tokens (gradiente `--acero`/`--metal` para lo transcurrido y `--brasa`/`--sangre`/`--ok` para lo restante según estado; `--alerta` si vencida), con marca de "hoy" (punto o `--riel`). Las fechas `inicio → fin` en mono abajo, y los días restantes en mono destacado.
     - Datos que hoy muestra la tabla y **no se pueden perder**: Periodo completo (`starts_at` – `ends_at`), Total (`S/ {{ number_format($mem->total, 2) }}`), Estado.
   - Mantener `@forelse` + `x-estado-vacio` para el caso sin membresías.

> **Regla de diseño del proyecto (AGENTS.md):** sin literales de color; todo desde `tokens.css`. Las cifras se representan con discos (`x-discos`) más que con barras de progreso: el rail es una **línea de tiempo de periodo** (contexto, no cifra) — si el implementador prefiere la ortodoxia total para la cifra de días, usar `<x-discos>` en lugar del círculo/rail en la tarjeta de la vigente. Decisión de diseño, no funcional.

### 3.5 CSS — `resources/css/panel.css` (y/o `components.css`)

- `.estado--por-vencer { color: var(--brasa); }` (verificar nombre exacto del token en `tokens.css`).
- `.membresias { display: grid; gap: var(--e-4); grid-template-columns: 1fr; }` + breakpoints (`≥640px: 2; ≥1024px: 3`), siguiendo el patrón de `.kpis` ya existente (con su media de móvil).
- `.membresia` (tarjeta): padding, cabecera flex, contenido en grid.
- `.membresia-rail`: altura fina, `border-radius: var(--r-full)`, fondo `--acero`, relleno con gradiente según estado (usar `color-mix` como ya hace el proyecto, p. ej. `.aviso--whatsapp`), marca de hoy como punto centrado.
- Responsive: cifras sin desborde (lección de la ronda de KPIs: medir `scrollWidth` vs `clientWidth` en 375/768/1280).
- Respetar `prefers-reduced-motion` si se anima el rail (`animations.js` deshace el estado inicial).

### 3.6 (Opcional) Plantillas de mensaje en config — `config/sparta.php`

Mover el `match(true)` de la vista a `config('sparta.whatsapp_mensajes')` como array de cierres o plantillas con placeholders (`{nombre}`, `{plan}`, `{dias}`, `{fecha}`). Beneficio: recepción/admin edita el copy sin tocar Blade. Costo: pequeño; es opcional para esta iteración.

---

## 4. Migraciones / rutas / permisos

- **Migraciones:** NINGUNA. `ends_at` ya existe y está indexado. No se añaden columnas de estado ("próxima a vencer" es derivado).
- **Rutas:** ninguna nueva. El enlace WhatsApp es un `href` estático (no pasa por el servidor). El `store` ya existe (`admin.clientes.membresias.store`).
- **Permisos:** ninguno nuevo. La pestaña ya está dentro del grupo `rol:admin,recepcion`; el `store` no exige `permiso:` adicional hoy (igual que el resto del módulo) — mantener ese criterio.

---

## 5. Pruebas

El repo no tiene suite real (`tests/` solo trae los ejemplos de Laravel). Plan de verificación:

1. **Compilación Blade:** `php artisan view:cache` (caza errores de sintaxis en la vista tocada).
2. **Assets:** `npm run build` tras tocar CSS.
3. **Manual en navegador** (con `php artisan serve` + datos demo `migrate:fresh --seed`):
   - Registrar membresía con `starts_at` **en el pasado** (fuera del sistema) → `ends_at` correcto = inicio + duración; días restantes coherentes.
   - Registrar con `ends_at` manual → se respeta; validación `ends_at < starts_at` rechazada con error visible.
   - Registrar sin `ends_at` → calculado igual que hoy (sin regresión).
   - Tarjetas: estados activa / por-vencer (crear membresía que vence en ≤7 días) / vencida (pasada) / cancelada → colores correctos en tema claro y oscuro.
   - Botón "Enviar recordatorio": visible solo con membresía vigente en ventana + teléfono; abre `wa.me` con el mensaje correcto en pestaña nueva; **ya no desaparece solo**.
   - Responsive 375 / 768 / 1280: sin desbordes de números (probar con `S/ 1,234.56` y días de 2-3 dígitos).
4. **Opcional (si se quiere empezar cultura de tests):** 1-2 tests de `MatriculaService` (Pest/PHPUnit) cubriendo: `ends_at` calculado por defecto y `ends_at` manual respetado. No bloquear la entrega por esto.
5. **Regresión:** pestañas Medidas/Pagos/Asistencia intactas; `renovarMembresia` sigue pasando la anterior a `vencida`; multi-gimnasio intacto (`BelongsToGym` no se toca).

---

## 6. Orden de implementación sugerido

1. Modelo: accesos `estado_visual` y `porcentaje_transcurrido` (`Membership.php`).
2. Servicio + validación: `ends_at` opcional (`MatriculaService`, `MembershipController`).
3. Vista: formulario + botón persistente + tarjetas (`show.blade.php`).
4. CSS: `.estado--por-vencer`, `.membresias`, `.membresia-rail` (`panel.css`), `npm run build`.
5. Verificación (§5), incluida la matriz responsive.

## 7. Riesgos y reglas a respetar

- **No romper el invariante** `ends_at = starts_at + duración` por defecto: el campo manual es opt-in (`nullable`).
- **Importes congelados:** no tocar `plan_name`/`price` copiados en la membresía.
- **Multi-gimnasio:** `BelongsToGym` filtra solo; los accesos nuevos no agregan consultas por gym.
- **Cero literales de color/tamaño en CSS:** todo de `tokens.css` (excepción ya documentada: `--whatsapp`).
- **No escribir `ends_at` desde la vista:** sigue siendo dato calculado/validado en servidor; la vista solo lo envía.
- **La tabla actual se reemplaza por tarjetas:** conservar la información (plan, periodo, total, estado) para no perder datos visibles.
