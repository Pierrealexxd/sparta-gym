# Ejecutar: Rediseño del Dashboard del Cliente con Gráficas

Lee el plan completo en `PLAN-DASHBOARD-CLIENTE.md` y ejecútalo.

## Correcciones al plan ya verificadas contra el código real

Verifiqué el plan contra el esquema y el código. **Aplica estas correcciones, no el código literal del plan:**

1. **Usa la columna `attended_on`, no `DATE(checked_in_at)`.** La tabla `attendances` tiene una columna generada `attended_on` (`storedAs('DATE(checked_in_at)')`) con índices `['member_id','attended_on']` creados **precisamente** para este tipo de agregación. El comentario de la migración lo dice literal: hacerlo con `DATE(checked_in_at)` impide usar el índice. Las queries del plan (`YEARWEEK(checked_in_at,1)`, `DAYOFWEEK(checked_in_at)`) se saltan ese índice. Agrupa por `attended_on` (`YEARWEEK(attended_on,1)`, `DAYOFWEEK(attended_on)`) y filtra por `attended_on >= ...`.

2. **Bug de año ISO al rellenar semanas vacías.** El plan arma la clave de semana con `now()->subWeeks($i)->format('Y')` + `format('W')`, pero `YEARWEEK(x, 1)` devuelve el año **ISO**, no el año calendario. Divergen en los cambios de año (p. ej. el 29-dic-2026 es semana ISO 1 de 2027), y ahí todas las semanas se rellenarían con 0. Usa **`format('o')`** (año ISO) en vez de `format('Y')`. El `str_pad` sobra porque `format('W')` ya rellena con cero, pero no estorba.

3. **La racha son 52 queries en el render de la página.** El plan hace un loop de 52 `exists()`. Reemplázalo por **una sola query** que traiga las semanas con asistencia (`selectRaw('DISTINCT YEARWEEK(attended_on,1) as semana')` de las últimas 52 semanas) y cuenta las consecutivas en PHP. Mismo resultado, 1 query en vez de 52. No hace falta caché.

4. **La racha se rompe al inicio de cada semana.** Con la lógica del plan, si el socio aún no ha ido en la semana en curso (p. ej. lunes por la mañana), la racha muestra 0 aunque lleve 10 semanas seguidas. Trátalo bien: si la semana actual todavía no tiene visita, **no la cuentes pero tampoco cortes la racha** — empieza a contar desde la semana anterior. Documenta la decisión en un comentario.

5. **`graficos.js` ya soporta** `token`, `eje: 'y1'`, `relleno`, `tituloEjeY` y `tituloEjeY1` (el eje dual se agregó en la fase del panel de progreso). Lo único que falta de verdad es `horizontal` → `indexAxis: 'y'`. Agrégalo leyéndolo del **config global** (no del dataset) y aplicándolo a `options` **antes** de `new Chart()` — no después con `chart.update()` como sugiere una de las variantes del plan, que provoca un doble render.

Nombres verificados y correctos tal como los usa el plan: `Member::attendances()`, `sales()`, `testimonial()`, `currentMembership()`, `currentAssignment()`, `days_left`, `Sale::scopeCompletadas()`, `Plan::duration_days`, `member_measurements.{measured_at,weight_kg,body_fat_pct}`.

Si encuentras más desajustes entre plan y código real, **el código real manda**. Corrige y documenta con un comentario breve.

## Reglas

1. **Sin browser, sin QA visual, sin screenshots.** Solo código: PHP, Blade, CSS, JS.
2. **No corromper nada existente.** Lee cada archivo completo antes de tocarlo. Edita por reemplazo parcial (`oldString` → `newString`). `dashboard.blade.php` es una reescritura grande: aun así, ve por secciones y conserva lo que el plan dice mantener (la tarjeta "Tu reseña" ya fue mejorada en una fase previa — **no la pierdas**).
3. **Responsive first.** Mobile (≤740px) → tablet → desktop (≥1025px). Gráficas 180px de alto en mobile, 260px en desktop. Las tablas del panel pasan a modo tarjeta en **960px**: la tabla de "Últimas visitas" necesita `tabla--tarjetas` y sus `<td data-etiqueta="...">`.
4. **Respeta el sistema de diseño.** Todo desde `tokens.css`, cero literales de color/tamaño/radio/duración. Los colores de las gráficas se pasan como **token** (`--sangre`, `--brasa`, `--bronce`), nunca como hex — así siguen funcionando en claro/oscuro.
5. **Patrones existentes.** Gráficas → `data-grafico` con JSON (el JS ya sabe qué hacer). KPI circular → mismo patrón que `progreso-kpi__circulo`. Colapsables → Alpine `x-show`. Discos de metas → el componente que ya existe.
6. **Opina sobre diseño.** Si algo del plan no funciona visualmente o se puede mejorar, cámbialo y justifícalo en un comentario breve.
7. **JS mínimo.** Solo Alpine.js y el `graficos.js` existente. No agregues librerías.
8. **Empty states obligatorios.** Cada gráfica y sección necesita su estado vacío (ver la tabla del plan). Un socio nuevo sin datos no puede ver 4 gráficas rotas.
9. **Verificar al terminar cada bloque:** `php -l` en los `.php` tocados, `php artisan view:cache` para validar que los blade compilan, y `npm run build` (tocas CSS y JS). Reporta resultados literales.
10. **No toques** `ProgressController`, `progreso.blade.php`, ni la lógica de negocio de asistencia/ventas/rutinas. El progreso vive aparte y ya fue rediseñado en otra fase.

## Orden

```
A. graficos.js — soporte horizontal (indexAxis)
B. DashboardController — KPIs, racha (1 query), y las 4 configs de gráficas
C. dashboard.blade.php — layout nuevo: KPIs, 4 gráficas, rutina en cards, metas, visitas, reseña
D. panel.css — KPI circular, grid de gráficas, cards de rutina, responsive
E. Verificación: php -l + view:cache + npm run build
```

## Archivos a modificar (5, ninguno a crear)

- `app/Http/Controllers/Cliente/DashboardController.php`
- `resources/views/cliente/dashboard.blade.php`
- `resources/js/graficos.js`
- `resources/css/panel.css`
- (`routes/cliente.php` sin cambios — la ruta ya existe)

## Contexto para leer antes de tocar

- `resources/js/graficos.js` — API real de gráficas (token/eje/relleno/tituloEjeY)
- `resources/views/cliente/progreso.blade.php` — patrón de KPI circular y de discos de metas (**leer, no modificar**)
- `app/Models/Member.php` — relaciones y `days_left`
- `app/Models/Attendance.php` + `database/migrations/2026_08_03_000105_create_payments_and_attendances_tables.php` — la columna `attended_on` y sus índices
- `resources/css/panel.css`, `resources/css/tokens.css`

## Output esperado

1. Resumen de archivos modificados
2. Resultado literal de `php -l`, `view:cache` y `npm run build`
3. Decisiones distintas al plan y por qué
4. Criterios de aceptación (sección 14 del plan) que NO se cumplieron y por qué
5. Tu opinión honesta: qué quedó bien y qué se puede mejorar

No hagas commit ni push. Deja todo en el working tree.
