# Ejecutar: Módulo "Mi rutina" + arreglo del IMC + indicadores de progreso

Este archivo **es la especificación**. No hay PLAN-*.md previo para esto.

---

## Parte 1 — Módulo propio "Mi rutina" (cliente)

### Por qué

La rutina es lo que el socio abre **dentro del gimnasio, con el celular en la mano, entre series**. Hoy está enterrada como una sección de `/cliente/progreso`, debajo de KPIs y gráficas de peso. Merece un clic directo.

### El problema a resolver primero: hoy la rutina está DUPLICADA

Ahora mismo aparece en dos sitios:
- `resources/views/cliente/dashboard.blade.php:112-121` — cards por día, colapsables
- `resources/views/cliente/progreso.blade.php:164-170` — sección "Mi rutina asignada"

Con un módulo nuevo serían **tres**. Resuélvelo así:

| Sitio | Qué queda |
|---|---|
| **`/cliente/rutina`** (nuevo) | **Canónico.** La rutina completa: días, ejercicios, series, reps, peso, descanso y notas. |
| **Dashboard** | Se queda el preview compacto que ya existe, pero **agrega un enlace "Ver rutina completa"** al módulo nuevo. Un dashboard resume, está bien que la muestre. |
| **Progreso** | **Se quita la sección de rutina.** Progreso es medición (peso, IMC, grasa, metas, historial). Deja en su lugar, si acaso, un enlace discreto al módulo. |

### Qué construir

- Ruta `GET /cliente/rutina` → `cliente.rutina`, dentro del grupo que ya existe en `routes/cliente.php` (ya está protegido por `middleware('rol:cliente')`).
- Controlador `app/Http/Controllers/Cliente/RoutineController.php`, invokable.
- Vista `resources/views/cliente/rutina.blade.php`.
- Enlace en el nav del cliente en `resources/views/layouts/partials/panel-nav.blade.php`, dentro del bloque `@if (auth()->user()->esCliente())`, entre "Mi panel" y "Mi progreso". Usa un icono ya existente del sprite.

### Contenido de la vista

- Cabecera: nombre de la rutina y, si viene de un programa, el nombre del programa (`$rutina->program->name`).
- Un bloque por día (`routine_days`), con su `focus`.
- Por ejercicio: nombre, series × reps, y peso / descanso / notas **solo si existen** (son nullable — no pintes "null kg").
- **Empty state**: si no tiene rutina, un mensaje claro invitando a elegir un programa en la landing o pasar por recepción. No dejes la pantalla vacía.
- Pensada para usarse **de pie, en el gimnasio**: prioriza legibilidad en móvil. Tipografía cómoda, objetivos táctiles grandes, nada de tablas densas que obliguen a hacer zoom.

### Seguridad

El socio sale **siempre** de `$request->user()->member()`. Nunca aceptes un `member_id` ni un `routine_id` que venga del request. Un cliente solo puede ver su propia rutina.

---

## Parte 2 — Arreglar el IMC (bug con causa raíz confirmada)

### Causa raíz (ya investigada, no la re-investigues)

El wizard de matrícula —la vía principal de alta— **nunca pide la altura**. Su paso 1 captura nombres, apellidos, documento, teléfono y correo. Sin `height_cm`, `MemberMeasurement::getBmiAttribute()` (`app/Models/MemberMeasurement.php:44-51`) devuelve `null`, y `progreso.blade.php:32-34` pinta un aro al 0% con un guión mudo: ni explica que falta la altura, ni dice dónde ponerla. El socio no tiene forma de enterarse.

### Los dos arreglos

**a) El origen — pedir la altura en la matrícula.**
Agrega un campo de altura (cm) al paso 1 del wizard de matrícula, en `Admin\MatriculaController` y su vista, y también en el equivalente del entrenador (`Entrenador\InscripcionController`) si comparte el formulario.
- **Debe ser opcional (`nullable`).** No puede bloquear un alta: si alguien llega al mostrador y no sabe su altura, la matrícula tiene que completarse igual.
- Validación coherente con la que ya existe en `PerfilController:80`: `['nullable','integer','min:100','max:260']`.
- La lógica de negocio vive en `MatriculaService` — respétala, no dupliques el alta.

**b) La salida — dar camino cuando falta.**
En `progreso.blade.php`, cuando no haya altura, en vez del guión mudo muestra un aviso corto con **enlace directo a Mi Perfil** ("Falta tu altura para calcular el IMC — agrégala en Mi perfil"). Reusa el patrón de avisos que ya existe (`.aviso`). Que se entienda qué falta y dónde se arregla.

### Lo que NO hay que hacer

No agregues un campo de altura al formulario "Registrar hoy" de progreso. La altura no cambia semana a semana como el peso; ensuciaría un formulario de seguimiento diario. Su sitio es el alta y el perfil.

---

## Parte 3 — Indicadores útiles en Progreso

Al quitar la rutina, Progreso queda como el módulo de **medición**. Tienes libertad para proponer, con este criterio: **cada indicador responde una pregunta que el socio se hace**. Si no responde ninguna, no va.

Lo que ya tiene: KPIs (peso, IMC, grasa, días registrando), "Registrar hoy", Mis metas con discos, gráfico peso+grasa, historial de medidas.

Ideas que sí aportan (elige con criterio, no las metas todas):
- **Delta desde el inicio**: cuánto bajó/subió desde su primera medida. Es la pregunta número uno de cualquiera que entrena.
- **Avance hacia la meta activa**: cuánto le falta en unidades reales, no solo el %.
- **Constancia de registro**: hace cuánto no se pesa. Un socio que no mide, no progresa.

Reglas: los deltas llevan signo y color (bajar de peso puede ser bueno o malo según la meta — no asumas que bajar siempre es verde). Todo indicador nuevo necesita su empty state para el socio que recién entra y no tiene historial.

---

## Reglas comunes

1. **Sin browser, sin QA visual, sin screenshots.** Solo código.
2. **No corromper nada existente.** Lee cada archivo completo antes de tocarlo. Edita por reemplazo parcial (`oldString` → `newString`), nunca reescribas archivos existentes enteros.
3. **Responsive: móvil primero, y cuida la tablet.** El usuario lo pidió explícitamente. Mobile ≤740px, tablet 741-1024px, desktop ≥1025px. Ojo: **las tablas del panel pasan a modo tarjeta en 960px** — cualquier tabla necesita `tabla tabla--tarjetas` y sus `<td data-etiqueta="...">`. La franja de tablet es la que más se rompe: verifica que la vista de rutina se lea bien ahí, no solo en los extremos.
4. **Respeta el diseño.** Todo desde `tokens.css`, cero literales de color/tamaño/radio/duración. Reusa clases y componentes que ya existen (`tarjeta`, `aviso`, `estado-vacio`, `campo`) antes de crear CSS nuevo.
5. **Solo Alpine.js.** No agregues librerías.
6. **Verificación al terminar cada parte:** `php -l` en los `.php` tocados, `php artisan view:cache`, y `npm run build` si tocas CSS/JS. Reporta la salida literal.
7. **No toques** los dashboards de admin ni los módulos del entrenador: acaban de modificarse en otra fase. La única excepción es el formulario de inscripción del entrenador, y solo si comparte el formulario de matrícula (Parte 2a).

## Detalle menor, si te queda margen

`app/Http/Controllers/Entrenador/AttendanceController.php:306` dice *"Solo podés cerrar asistencias que registraste vos"* — voseo rioplatense en un proyecto peruano (Piura, soles). Ese mensaje lo ve el usuario. Ajústalo al español neutro que usa el resto del proyecto ("Solo puedes cerrar asistencias que registraste"). Es una línea; si toca conflicto con algo, déjalo y repórtalo.

## Output esperado

1. Resumen de archivos creados y modificados
2. Salida literal de `php -l`, `view:cache` y `npm run build`
3. **Confirmación explícita** de que la rutina ya no está duplicada: dónde quedó cada una de las tres apariciones
4. Qué indicadores de progreso elegiste y qué pregunta responde cada uno
5. Decisiones distintas a esta especificación y por qué
6. Tu opinión honesta: qué quedó bien y qué se puede mejorar

No hagas commit ni push. Deja todo en el working tree.
