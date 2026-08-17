# Ejecutar: Indicadores del panel admin + gráficas en módulos del entrenador

Dos trabajos, en este orden. No hay plan previo escrito: **este archivo es la especificación**.

---

## Contexto: decisión previa que NO se revierte

El "Resumen" (dashboard) del entrenador **se dio de baja a propósito el 13-08-2026**. La razón está documentada en `routes/entrenador.php`, `resources/views/layouts/partials/panel-nav.blade.php` y en tres controladores: los indicadores se movieron a vivir **dentro de cada módulo** (Inscripciones, Ventas, Asistencia) en vez de una pantalla aparte.

**No recrees el dashboard del entrenador. No agregues una ruta ni un enlace de "Resumen".** El trabajo aquí es darle **gráficas a los módulos que ya existen**, respetando esa decisión.

---

## Parte 1 — Rebalancear los indicadores del admin

### Problema

`app/Http/Controllers/Admin/DashboardController.php` está sesgado al dinero: de 7 KPIs, 3 son ingresos; de 5 gráficas, 3 son de plata. Los indicadores de **clientes** son un conteo suelto de activos/inactivos y matrículas del mes — sin tendencia.

### Constraint verificado (importante)

`members.status` es un enum de estado **actual** (`activo|inactivo|suspendido`) y **no existe tabla de historial**. Por tanto:

- **NO intentes** graficar una curva histórica de inactivos: el dato no existe. No inventes una migración de historial — está fuera de alcance.
- **Sí puedes** derivar la pérdida de clientes de las **membresías**: una membresía con `ends_at` pasado que no tiene una renovación apuntando a ella (`renewed_from`) es una baja real. Ese dato sí es histórico y es más honesto que el flag manual.

### Qué agregar

Respeta lo que ya existe (no borres los KPIs ni las gráficas de ingresos — siguen siendo útiles). **Agrega** el eje de clientes:

1. **Altas diarias (30 días)** — bar chart. Es lo que el usuario pidió literal: "los indicadores diarios de los clientes que voy registrando". Fuente: `members.joined_at`. Reusa el helper `rellenarDias()` que ya existe para que los días sin altas muestren 0 y no desaparezcan del eje.

2. **Altas vs bajas por mes (6 meses)** — line chart con dos series. Altas: `joined_at`. Bajas: membresías vencidas en ese mes sin renovación posterior. Da el crecimiento neto, que es la pregunta real del dueño.

3. **Composición de clientes hoy** — doughnut de activos / inactivos / suspendidos. Es una foto, no una serie, y está bien que lo sea: es el "de dónde parto".

4. **KPI de retención** — % de membresías vencidas en los últimos 30 días que sí se renovaron. Un solo número con su variación.

### Reglas de la Parte 1

- **Todas las consultas nuevas DEBEN pasar por los helpers `$this->members()`, `$this->sales()`, `$this->memberships()`, `$this->attendances()`.** Son el punto único que aplica el filtro de sedes (`conSedes()`). Una consulta que llame a `Member::query()` directo **rompe el filtro multi-sede** y mezcla datos entre gimnasios. Esto es lo más importante de toda la tarea.
- El filtro de sedes ya funciona (checkboxes `?sedes[]` + `GymContext`). **No lo rediseñes.** Solo asegúrate de que lo nuevo lo respeta.
- Usa `attended_on` (columna generada e indexada) para agregaciones de asistencia, nunca `DATE(checked_in_at)`.
- Cuida el número de queries. `altasSocios()` ya hace un query por mes en un loop; si agregas algo parecido, prefiere **una sola query agrupada** y rellena los huecos en PHP.

---

## Parte 2 — Gráficas en los módulos del entrenador

El entrenador tiene indicadores pero **cero gráficas**: todos sus números son escalares sin tendencia. Agrega una gráfica por módulo, dentro de la pantalla que ya existe.

| Módulo | Vista | Gráfica a agregar | Pregunta que responde |
|---|---|---|---|
| Inscripciones/Rutinas | `entrenador/inscripciones/index.blade.php` | Inscripciones propias por mes (6 meses), bar | "¿Cómo va mi captación?" |
| Ventas | `entrenador/ventas/index.blade.php` | Ventas propias en el tiempo dentro del rango ya filtrado, line | "¿Cómo van mis ventas?" |
| Asistencia | `entrenador/asistencia/mi-marcacion.blade.php` | Sus marcaciones por semana (12 semanas), bar | "¿Cuán constante soy yo?" |

### Reglas de la Parte 2

- **Aislamiento de datos: cada gráfica es SOLO del entrenador autenticado.** Filtra por `sold_by = $request->user()->id` (ventas), `created_by = $request->user()->id` (inscripciones) o su propio registro de asistencia. Un entrenador **nunca** debe ver datos agregados de otros entrenadores ni del gimnasio entero. Esto es un requisito de seguridad, no de diseño.
- La gráfica de ventas debe respetar **el rango de fechas que ya filtra esa pantalla**, no un rango fijo. Si el usuario filtra a "este mes", la gráfica muestra este mes.
- No agregues rutas nuevas, ni pantalla de Resumen, ni enlace en el nav.
- Empty state obligatorio en cada gráfica: un entrenador nuevo sin ventas no puede ver un canvas roto.

---

## Reglas comunes

1. **Sin browser, sin QA visual, sin screenshots.** Solo código.
2. **No corromper nada existente.** Lee cada archivo completo antes de tocarlo. Edita por reemplazo parcial (`oldString` → `newString`), nunca reescribas archivos existentes enteros.
3. **Responsive first.** Gráficas ~180px de alto en mobile, ~260px en desktop. Las tablas del panel pasan a modo tarjeta en **960px**.
4. **Todo el CSS desde `tokens.css`.** Cero literales de color/tamaño/radio/duración. Los colores de gráficas se pasan como **token** (`--sangre`, `--brasa`, `--bronce`), nunca hex, para que sigan funcionando en claro/oscuro.
5. **Patrón de gráficas existente:** el controlador arma el array PHP, la vista lo pasa como `<canvas data-grafico="{{ json_encode($config) }}">`, y `resources/js/graficos.js` hace el resto. Ese archivo ya soporta `tipo`, `token`, `eje: 'y1'`, `relleno`, `guiones`, `tituloEjeY`. **Léelo antes de asumir qué acepta.**
6. **Solo Alpine.js.** No agregues librerías.
7. **Verificación al terminar cada parte:** `php -l` en los `.php` tocados, `php artisan view:cache`, y `npm run build` si tocas CSS/JS. Reporta la salida literal.
8. **No toques** `app/Http/Controllers/Cliente/*` ni `resources/views/cliente/*`: el panel del cliente se rediseñó en otra fase paralela.

## Archivos previstos

**Parte 1:** `app/Http/Controllers/Admin/DashboardController.php`, `resources/views/admin/dashboard.blade.php`, y `resources/css/panel.css` solo si hace falta.

**Parte 2:** `app/Http/Controllers/Entrenador/{InscripcionController,VentaController,AttendanceController}.php` y sus tres vistas.

## Output esperado

1. Resumen de archivos modificados
2. Salida literal de `php -l`, `view:cache` y `npm run build`
3. **Confirmación explícita** de que toda consulta nueva del admin pasa por los helpers con filtro de sedes, y de que toda gráfica del entrenador está filtrada a su propio usuario
4. Decisiones que tomaste distintas a esta especificación y por qué
5. Tu opinión honesta: qué quedó bien y qué se puede mejorar

No hagas commit ni push. Deja todo en el working tree.
