# Plan: Rediseño del Dashboard del Cliente con Gráficas

> **Proyecto:** Sparta Gym — Laravel 12 · Blade · Alpine · GSAP · Chart.js
> **Fecha:** 2026-08-16
> **Objetivo:** Transformar el dashboard del cliente ("Mi panel") en una experiencia visual que cuente la historia de su progreso, usando gráficas intuitivas que cualquier usuario entienda de un vistazo.

---

## Índice

1. [Resumen ejecutivo](#1-resumen-ejecutivo)
2. [Estado actual](#2-estado-actual)
3. [Filosofía del rediseño](#3-filosofía-del-rediseño)
4. [Gráfica 1 — Asistencia semanal](#4-gráfica-1--asistencia-semanal)
5. [Gráfica 2 — Frecuencia por día de la semana](#5-gráfica-2--frecuencia-por-día-de-la-semana)
6. [Gráfica 3 — Progreso corporal (peso + grasa)](#6-gráfica-3--progreso-corporal-peso--grasa)
7. [Gráfica 4 — Inversión acumulada](#7-gráfica-4--inversión-acumulada)
8. [Sección — Mi rutina actual](#8-sección--mi-rutina-actual)
9. [Sección — Mis metas](#9-sección--mis-metas)
10. [KPIs mejorados](#10-kpis-mejorados)
11. [Layout propuesto](#11-layout-propuesto)
12. [Migraciones y cambios en controladores](#12-migraciones-y-cambios-en-controladores)
13. [Archivos a crear/modificar](#13-archivos-a-crearmodificar)
14. [Criterios de aceptación](#14-criterios-de-aceptación)

---

## 1. Resumen ejecutivo

### El problema actual

El dashboard del cliente es una lista de texto plano: tablas de ventas, tablas de asistencia, párrafos de membresía. No hay una narrativa visual que le diga al cliente "así vas". Un cliente que abre su panel ve números sueltos, no una historia de progreso.

### La solución

Un dashboard que responda las 5 preguntas que todo socio se hace:

| Pregunta | Gráfica/Sección | Tipo |
|----------|----------------|------|
| ¿Cuán constante soy? | Asistencia semanal | Bar chart |
| ¿Qué días entreno? | Frecuencia por día | Horizontal bar |
| ¿Cómo va mi cuerpo? | Progreso peso + grasa | Line chart (dual axis) |
| ¿Cuánto he invertido? | Inversión acumulada | Line chart |
| ¿Qué hago hoy? | Rutina actual + Metas | Cards visuales |

### Qué NO se toca

- No se modifica la lógica de negocio (asistencia, ventas, rutinas siguen igual)
- No se rompe el responsive
- No se eliminan datos — se reorganizan visualmente
- El progreso (`/cliente/progreso`) se mantiene separado con su propio enfoque

---

## 2. Estado actual

### Dashboard actual (`/cliente/`)

```
┌─────────────────────────────────────────────────┐
│ KPIs: Días membresía | Visitas mes | Rutinas |  │
│       Objetivos                                  │
├─────────────────────────────────────────────────┤
│ Mi membresía (texto)  │ Mis objetivos (texto)   │
├─────────────────────────────────────────────────┤
│ Mi rutina (tablas de texto)                     │
├─────────────────────────────────────────────────┤
│ Últimas ventas (tabla) │ Asistencia (tabla)     │
├─────────────────────────────────────────────────┤
│ Tu reseña (formulario)                          │
└─────────────────────────────────────────────────┘
```

**Problemas:**
- 0 gráficas — todo es texto y tablas
- Las tablas de ventas y asistencia no cuentan una historia
- "Mis objetivos" es una lista sin visualización de progreso
- "Mi rutina" es una tabla densa de ejercicios
- No hay indicador visual de constancia o tendencia

### Datos disponibles pero no graficados

| Dato | Fuente | Gráfica propuesta |
|------|--------|-------------------|
| Asistencia por semana | `attendances.checked_in_at` | Bar chart semanal |
| Días de mayor asistencia | `attendances.attended_on` | Horizontal bar por día |
| Duración de sesiones | `attendances.duracion_minutos` | (futuro) |
| Peso + grasa over time | `member_measurements` | Line chart dual axis |
| Gasto acumulado | `sales.total + sold_at` | Line chart acumulado |
| Métodos de pago | `sales.method` | (futuro) |
| Medidas corporales | `member_measurements.chest_cm, waist_cm...` | (futuro) |
| Masa muscular | `member_measurements.muscle_mass_kg` | (futuro) |

### Infraestructura de gráficas lista

- Chart.js registrado con Line, Bar, Doughnut controllers
- Patrón `data-grafico` + JSON attribute funcional
- Soporte de dual Y-axis implementado
- Colores via CSS tokens (se adapta a light/dark)
- Animación por tema (`tema:refrescar` event)

---

## 3. Filosofía del rediseño

### Principios

1. **Una gráfica = una pregunta.** Cada gráfica responde algo que el cliente se pregunta. Si no responde una pregunta, no va.

2. **Tendencia > dato puntual.** No mostrar "asististe 12 veces este mes" sino "tu constancia subió 20% vs el mes pasado" con una gráfica que muestre la curva.

3. **Acción > información.** Junto a cada gráfica, un CTA claro: "Registrar peso", "Ver tu rutina", "Agendar evaluación".

4. **Sin jerga.** Nada de "BMI", "recomposición", "hipertrofia" en las gráficas. El cliente ve "Tu peso", "Tu grasa", "Tus visitas".

5. **Mobile-first.** Las gráficas se apilan en mobile, lado a lado en desktop. Altura fija de 200px en mobile, 260px en desktop.

---

## 4. Gráfica 1 — Asistencia semanal

### Pregunta que responde: "¿Cuán constante soy?"

### Tipo: Bar chart

### Datos

```php
// En DashboardController
$asistenciasPorSemana = $socio->attendances()
    ->where('checked_in_at', '>=', now()->subWeeks(12))
    ->selectRaw('YEARWEEK(checked_in_at, 1) as semana, COUNT(*) as total')
    ->groupBy('semana')
    ->orderBy('semana')
    ->pluck('total', 'semana')
    ->toArray();
```

### Configuración JSON

```json
{
    "tipo": "bar",
    "labels": ["Sem 1", "Sem 2", "Sem 3", "..."],
    "datasets": [{
        "label": "Visitas",
        "data": [3, 4, 2, 5, 4, 6, 3, 4, 5, 4, 3, 5],
        "token": "--sangre",
        "borderRadius": 6
    }],
    "tituloEjeY": "Visitas"
}
```

### Comportamiento

- Muestra las últimas 12 semanas
- Si una semana no tiene datos, muestra 0 (barra vacía)
- Color: `--sangre` (rojo fuego — el color de acción del gym)
- En mobile: altura 180px, labels cada 2 semanas para no saturar

### Detalle visual

```
Visitas por semana
┌──────────────────────────────────────┐
│     █                               │
│     █     █           █             │
│  █  █  █  █     █  █  █  █     █    │
│  █  █  █  █  █  █  █  █  █  █  █    │
│──┼──┼──┼──┼──┼──┼──┼──┼──┼──┼──┼──│
│ S1 S2 S3 S4 S5 S6 S7 S8 S9 ... S12 │
└──────────────────────────────────────┘
 4 visitas esta semana ↑12% vs anterior
```

---

## 5. Gráfica 2 — Frecuencia por día de la semana

### Pregunta que responde: "¿Qué días entreno?"

### Tipo: Horizontal bar chart

### Datos

```php
$asistenciasPorDia = $socio->attendances()
    ->where('checked_in_at', '>=', now()->subMonths(3))
    ->selectRaw('DAYOFWEEK(checked_in_at) as dia, COUNT(*) as total')
    ->groupBy('dia')
    ->orderBy('dia')
    ->pluck('total', 'dia')
    ->toArray();

// Mapear a nombres
$diasNombres = [1 => 'Dom', 2 => 'Lun', 3 => 'Mar', 4 => 'Mié', 5 => 'Jue', 6 => 'Vie', 7 => 'Sáb'];
```

### Configuración JSON

```json
{
    "tipo": "bar",
    "labels": ["Dom", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"],
    "datasets": [{
        "label": "Visitas",
        "data": [0, 12, 8, 14, 6, 15, 3],
        "token": "--brasa",
        "horizontal": true,
        "borderRadius": 6
    }]
}
```

### Comportamiento

- Bars horizontales (Lun, Mar, Mié, Jue, Vie, Sáb, Dom)
- Color: `--brasa` (naranja brasa — cálido pero diferente a sangre)
- El día más frecuente se resalta con color más intenso
- Muestra los últimos 3 meses para tener masa estadística

### Nota técnica

Chart.js no tiene `horizontal: true` nativo en bar charts. Se logra usando `indexAxis: 'y'` en la configuración. El `graficos.js` necesita un pequeño ajuste para soportar esta opción:

```javascript
// En graficos.js, agregar después de crear el chart:
if (config.horizontal) {
    options.indexAxis = 'y';
}
```

### Detalle visual

```
Tus días de entrenamiento
┌──────────────────────────────────────┐
│ Lun  ████████████████████  12        │
│ Mar  ████████████  8                 │
│ Mié  ██████████████████████  14  ←   │
│ Jue  █████████  6                    │
│ Vie  ███████████████████████  15  ←  │
│ Sáb  ████  3                         │
│ Dom                                  │
└──────────────────────────────────────┘
  Viernes es tu día favorito
```

---

## 6. Gráfica 3 — Progreso corporal (peso + grasa)

### Pregunta que responde: "¿Cómo va mi cuerpo?"

### Tipo: Line chart dual axis (YA EXISTE en progreso, se replica al dashboard)

### Datos

Ya calculados en `ProgressController::graficoCombinado`. Se copia la misma lógica al `DashboardController`.

### Configuración JSON

Igual a la existente en `ProgressController`:

```json
{
    "tipo": "line",
    "labels": ["01/08", "08/08", "15/08", "..."],
    "tituloEjeY": "Peso (kg)",
    "tituloEjeY1": "% Grasa",
    "datasets": [
        {
            "label": "Peso (kg)",
            "data": [75.2, 74.8, 74.5, "..."],
            "token": "--sangre"
        },
        {
            "label": "% Grasa",
            "data": [22.1, 21.8, 21.5, "..."],
            "token": "--brasa",
            "eje": "y1",
            "relleno": false
        }
    ]
}
```

### Comportamiento

- Misma gráfica que en progreso pero más compacta (200px en mobile)
- Si no hay medidas, mostrar estado vacío: "Registra tu peso en Progreso para ver tu curva"
- CTA: "Registrar peso" → enlace a `/cliente/progreso`

### Detalle visual

```
Tu progreso corporal
┌──────────────────────────────────────┐
│ 76 ─╲                               │
│      ╲──────╲        Peso (kg)      │
│ 74           ╲──────╲               │
│ 72                    ╲──           │
│                          22 ─╲      │
│ 21                   ╲────── %Grasa │
│                           ╲──       │
│──┼────┼────┼────┼────┼────┼────┼──│
│  Ago 1  Ago 8  Ago 15 ...          │
└──────────────────────────────────────┘
 ↓1.2 kg · ↓0.6% grasa desde el inicio
```

---

## 7. Gráfica 4 — Inversión acumulada

### Pregunta que responde: "¿Cuánto he invertido en mi salud?"

### Tipo: Line chart con área rellena

### Datos

```php
$ventasPorMes = $socio->sales()
    ->completadas()
    ->where('sold_at', '>=', now()->subMonths(12))
    ->selectRaw('DATE_FORMAT(sold_at, "%Y-%m") as mes, SUM(total) as total')
    ->groupBy('mes')
    ->orderBy('mes')
    ->get();

// Acumular
$acumulado = 0;
$labels = [];
$data = [];
foreach ($ventasPorMes as $v) {
    $acumulado += $v->total;
    $labels[] = $v->mes; // "2026-01", "2026-02", ...
    $data[] = round($acumulado, 2);
}
```

### Configuración JSON

```json
{
    "tipo": "line",
    "labels": ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "..."],
    "datasets": [{
        "label": "Inversión acumulada (S/)",
        "data": [120, 240, 360, 480, 600, 720, "..."],
        "token": "--bronce",
        "relleno": true
    }],
    "tituloEjeY": "S/"
}
```

### Comportamiento

- Área rellena con degradado suave (token `--bronce`)
- Muestra acumulado mensual de los últimos 12 meses
- Si solo tiene 1 mes de datos, muestra una barra sola (no una línea con 1 punto)
- Formato de moneda en el eje Y: "S/ 120", "S/ 240", etc.
- Nota: los datos de `sales` ya están filtrados por `gym_id` via `BelongsToGym`

### Detalle visual

```
Tu inversión en salud
┌──────────────────────────────────────┐
│                          ╱██████████ │
│                    ╱█████            │
│              ╱█████                  │
│        ╱█████                        │
│  ╱█████                              │
│████                                  │
│──┼────┼────┼────┼────┼────┼────┼──│
│  Ene  Feb  Mar  Abr  May  Jun  ...   │
└──────────────────────────────────────┘
 S/ 720 invertidos en 6 meses
```

---

## 8. Sección — Mi rutina actual

### Pregunta que responde: "¿Qué hago hoy en el gym?"

### Formato: Cards por día (no tabla)

En vez de la tabla densa actual, se muestra como cards apilables:

```
Mi rutina — Ganar masa muscular
┌─────────────────────────────────────┐
│ Día 1 · Empuje                      │
│ Pecho y tríceps                     │
│ ┌─────────────────────────────────┐ │
│ │ Press banca         4 × 8-10   │ │
│ │ Press militar       3 × 10-12  │ │
│ │ Aperturas           3 × 12-15  │ │
│ │ Fondos              3 × al fallo│ │
│ │ Extensión tríceps   3 × 12-15  │ │
│ └─────────────────────────────────┘ │
├─────────────────────────────────────┤
│ Día 2 · Tirón                       │
│ Espalda y bíceps                    │
│ ┌─────────────────────────────────┐ │
│ │ Dominadas           4 × 8-10   │ │
│ │ Remo con barra      4 × 8-10   │ │
│ │ Jalón al pecho       3 × 10-12  │ │
│ │ Curl con barra      3 × 10-12  │ │
│ └─────────────────────────────────┘ │
├─────────────────────────────────────┤
│ Día 3 · Piernas                     │
│ ...                                 │
└─────────────────────────────────────┘
```

### Comportamiento

- Cada día es una card colapsable (Alpine.js `x-show`)
- Por defecto, el día de hoy (o el primero) está abierto
- Cada ejercicio muestra: nombre, series × repeticiones
- Si tiene peso prescrito, lo muestra
- Si tiene descanso, lo muestra
- Si no tiene rutina: "Aún no tienes una rutina. Selecciona un programa en la landing o pásate por recepción."

### Datos (ya cargados en DashboardController)

```php
'routines' => fn ($q) => $q->activas()->with('days.exercises.exercise'),
```

---

## 9. Sección — Mis metas

### Pregunta que responde: "¿Hacia dónde voy?"

### Formato: Cards con disco de progreso

Cada meta se muestra como una card con:
- Título de la meta
- Barra de progreso (discos)
- Valor actual vs objetivo
- Porcentaje de avance

```
Mis metas
┌─────────────────────────────────────┐
│ Perder peso                          │
│ 75 kg → 70 kg  ████████░░  60%      │
│ [discos: 5/8 cargados]              │
├─────────────────────────────────────┤
│ Ganar masa muscular                  │
│ 32 kg → 35 kg  ████░░░░░░  40%      │
│ [discos: 3/8 cargados]              │
└─────────────────────────────────────┘
```

### Comportamiento

- Misma lógica de progreso que en `ProgressController` (ya calculada)
- Si no hay metas: "Tu entrenador aún no definió objetivos"
- Los discos se animan con GSAP al hacer scroll

---

## 10. KPIs mejorados

### KPIs actuales (4 counters animados)

| KPI | Fuente | Mejora |
|-----|--------|--------|
| Días de membresía | `days_left` | Agregar barra de progreso circular (como IMC en progreso) |
| Visitas este mes | `attendances()->count()` | Agregar delta vs mes anterior (↑/↓) |
| Rutinas activas | `routines->count()` | Sin cambio |
| Objetivos activos | `goals->count()` | Sin cambio |

### KPIs propuestos (reemplazo)

| # | KPI | Fuente | Visual |
|---|-----|--------|--------|
| 1 | **Días restantes** | `days_left` | Número + barra circular del % de membresía consumido |
| 2 | **Visitas este mes** | attendance count | Número + delta vs mes anterior (↑12% / ↓5%) |
| 3 | **Racha actual** | consecutive weeks with attendance | Número + label "semanas seguidas" |
| 4 | **Peso actual** | latest measurement | Número + delta vs primera medida |

### Cálculo de racha

```php
// En DashboardController
$semanasConAsistencia = 0;
for ($i = 0; $i < 52; $i++) {
    $semana = now()->subWeeks($i)->startOfWeek();
    $asistio = $socio->attendances()
        ->where('checked_in_at', '>=', $semana)
        ->where('checked_in_at', '<', $semana->copy()->endOfWeek())
        ->exists();
    if ($asistio) {
        $semanasConAsistencia++;
    } else {
        break;
    }
}
```

---

## 11. Layout propuesto

### Desktop (≥1025px)

```
┌─────────────────────────────────────────────────────────────┐
│ Hola, [Nombre]                                              │
├──────────┬──────────┬──────────┬────────────────────────────┤
│ Días     │ Visitas  │ Racha    │ Peso actual                │
│ restantes│ este mes │ actual   │                            │
│ (circular│ (delta)  │ (segs)   │ (delta)                    │
│  gauge)  │          │          │                            │
├──────────┴──────────┴──────────┴────────────────────────────┤
│ Asistencia semanal (bar chart)    │ Tu progreso corporal    │
│ [12 semanas]                      │ [peso + grasa line]     │
│                                   │                         │
├───────────────────────────────────┼─────────────────────────┤
│ Tus días de entrenamiento         │ Tu inversión en salud   │
│ [horizontal bar por día]          │ [área acumulada]        │
│                                   │                         │
├───────────────────────────────────┴─────────────────────────┤
│ Mi rutina — [Nombre del programa]                           │
│ ┌─ Día 1 · Empuje ─┐ ┌─ Día 2 · Tirón ─┐ ┌─ Día 3 ─┐    │
│ │ Press banca 4×8   │ │ Dominadas 4×8    │ │ ...     │    │
│ │ Press mil   3×10  │ │ Remo      4×8    │ │         │    │
│ └───────────────────┘ └──────────────────┘ └─────────┘    │
├─────────────────────────────────────────────────────────────┤
│ Mis metas                                                   │
│ ┌─ Perder peso ─────────┐ ┌─ Ganar masa ────────────────┐  │
│ │ 75→70 kg  ████████░░  │ │ 32→35 kg  ████░░░░░░       │  │
│ └────────────────────────┘ └────────────────────────────┘  │
├─────────────────────────────────────────────────────────────┤
│ Últimas visitas (tabla compacta, últimos 5)                 │
│ 16 Ago · 07:30 - 09:15 · 1h 45min                         │
│ 14 Ago · 08:00 - 09:30 · 1h 30min                         │
│ 12 Ago · 07:15 - 08:45 · 1h 30min                         │
├─────────────────────────────────────────────────────────────┤
│ Tu reseña                                                   │
└─────────────────────────────────────────────────────────────┘
```

### Mobile (≤740px)

```
┌───────────────────────────┐
│ Hola, [Nombre]            │
├───────────┬───────────────┤
│ Días      │ Visitas       │
│ restantes │ este mes      │
├───────────┼───────────────┤
│ Racha     │ Peso actual   │
│ actual    │               │
├───────────┴───────────────┤
│ Asistencia semanal        │
│ [bar chart, 180px]        │
├───────────────────────────┤
│ Tu progreso corporal      │
│ [line chart, 180px]       │
├───────────────────────────┤
│ Tus días de entrenamiento │
│ [horizontal bar, 160px]   │
├───────────────────────────┤
│ Tu inversión en salud     │
│ [line chart, 180px]       │
├───────────────────────────┤
│ Mi rutina                 │
│ [cards apiladas]          │
├───────────────────────────┤
│ Mis metas                 │
│ [cards apiladas]          │
├───────────────────────────┤
│ Últimas visitas           │
│ [tabla compacta]          │
├───────────────────────────┤
│ Tu reseña                 │
└───────────────────────────┘
```

---

## 12. Migraciones y cambios en controladores

### No hay migraciones nuevas

Todos los datos ya existen en tablas con esquema completo. Solo se necesitan queries diferentes en el controlador.

### Cambios en `DashboardController`

**Archivo:** `app/Http/Controllers/Cliente/DashboardController.php`

```php
<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $socio = $request->user()->member()->with([
            'currentMembership.plan',
            'currentAssignment.trainer.user',
            'goals' => fn ($q) => $q->activos(),
            'routines' => fn ($q) => $q->activas()->with('days.exercises.exercise'),
            'sales' => fn ($q) => $q->completadas()->latest('sold_at'),
            'attendances' => fn ($q) => $q->latest('checked_in_at'),
            'measurements' => fn ($q) => $q->orderBy('measured_at'),
            'testimonial',
        ])->firstOrFail();

        // ── KPIs ──────────────────────────────────────────────
        $asistenciasMes = $socio->attendances()
            ->whereMonth('checked_in_at', now()->month)
            ->whereYear('checked_in_at', now()->year)
            ->count();

        $asistenciasMesAnterior = $socio->attendances()
            ->whereMonth('checked_in_at', now()->subMonth()->month)
            ->whereYear('checked_in_at', now()->subMonth()->year)
            ->count();

        $deltaAsistencia = $asistenciasMesAnterior > 0
            ? round(($asistenciasMes - $asistenciasMesAnterior) / $asistenciasMesAnterior * 100)
            : null;

        // Racha de semanas consecutivas con al menos 1 visita
        $racha = 0;
        for ($i = 0; $i < 52; $i++) {
            $semana = now()->subWeeks($i)->startOfWeek();
            $asistio = $socio->attendances()
                ->where('checked_in_at', '>=', $semana)
                ->where('checked_in_at', '<', $semana->copy()->endOfWeek())
                ->exists();
            if ($asistio) {
                $racha++;
            } else {
                break;
            }
        }

        $ultimaMedida = $socio->measurements->last();
        $primeraMedida = $socio->measurements->first();
        $deltaPeso = ($primeraMedida && $ultimaMedida && $primeraMedida->id !== $ultimaMedida->id)
            ? round($ultimaMedida->weight_kg - $primeraMedida->weight_kg, 1)
            : null;

        $kpis = [
            'diasRestantes'       => $socio->days_left,
            'diasRestantesPct'    => $socio->currentMembership
                ? round($socio->days_left / $socio->currentMembership->plan->duration_days * 100)
                : 0,
            'asistenciasMes'      => $asistenciasMes,
            'deltaAsistencia'     => $deltaAsistencia,
            'racha'               => $racha,
            'pesoActual'          => $ultimaMedida?->weight_kg,
            'deltaPeso'           => $deltaPeso,
        ];

        // ── GRÁFICA: Asistencia semanal (12 semanas) ──────────
        $asistenciasPorSemana = $socio->attendances()
            ->where('checked_in_at', '>=', now()->subWeeks(12))
            ->selectRaw('YEARWEEK(checked_in_at, 1) as semana, COUNT(*) as total')
            ->groupBy('semana')
            ->orderBy('semana')
            ->pluck('total', 'semana')
            ->toArray();

        // Rellenar semanas faltantes con 0
        $semanasLabels = [];
        $semanasData = [];
        for ($i = 11; $i >= 0; $i--) {
            $semana = now()->subWeeks($i)->format('Y') . str_pad(now()->subWeeks($i)->format('W'), 2, '0', STR_PAD_LEFT);
            $semanasLabels[] = 'Sem ' . (12 - $i);
            $semanasData[] = $asistenciasPorSemana[$semana] ?? 0;
        }

        $graficoAsistencia = [
            'tipo' => 'bar',
            'labels' => $semanasLabels,
            'datasets' => [[
                'label' => 'Visitas',
                'data' => $semanasData,
                'token' => '--sangre',
            ]],
            'tituloEjeY' => 'Visitas',
        ];

        // ── GRÁFICA: Frecuencia por día de la semana ──────────
        $asistenciasPorDia = $socio->attendances()
            ->where('checked_in_at', '>=', now()->subMonths(3))
            ->selectRaw('DAYOFWEEK(checked_in_at) as dia, COUNT(*) as total')
            ->groupBy('dia')
            ->orderBy('dia')
            ->pluck('total', 'dia')
            ->toArray();

        $diasNombres = [1 => 'Dom', 2 => 'Lun', 3 => 'Mar', 4 => 'Mié', 5 => 'Jue', 6 => 'Vie', 7 => 'Sáb'];
        $diaMax = 0;
        $maxVisitas = 0;
        foreach ($asistenciasPorDia as $dia => $total) {
            if ($total > $maxVisitas) {
                $maxVisitas = $total;
                $diaMax = $dia;
            }
        }

        $graficoFrecuencia = [
            'tipo' => 'bar',
            'labels' => array_values($diasNombres),
            'datasets' => [[
                'label' => 'Visitas',
                'data' => array_map(fn ($d) => $asistenciasPorDia[$d] ?? 0, range(1, 7)),
                'token' => '--brasa',
                'horizontal' => true,
            ]],
        ];

        // ── GRÁFICA: Progreso corporal (peso + grasa) ─────────
        $medidas = $socio->measurements->values();

        $graficoProgreso = [
            'tipo' => 'line',
            'labels' => $medidas->map(fn ($m) => $m->measured_at->format('d/m'))->all(),
            'tituloEjeY' => 'Peso (kg)',
            'tituloEjeY1' => '% Grasa',
            'datasets' => [
                [
                    'label' => 'Peso (kg)',
                    'data' => $medidas->map(fn ($m) => (float) $m->weight_kg)->all(),
                    'token' => '--sangre',
                ],
                [
                    'label' => '% Grasa',
                    'data' => $medidas->map(fn ($m) => $m->body_fat_pct !== null ? (float) $m->body_fat_pct : null)->all(),
                    'token' => '--brasa',
                    'eje' => 'y1',
                    'relleno' => false,
                ],
            ],
        ];

        // ── GRÁFICA: Inversión acumulada ──────────────────────
        $ventasPorMes = $socio->sales()
            ->completadas()
            ->where('sold_at', '>=', now()->subMonths(12))
            ->selectRaw('DATE_FORMAT(sold_at, "%Y-%m") as mes, SUM(total) as total')
            ->groupBy('mes')
            ->orderBy('mes')
            ->pluck('total', 'mes')
            ->toArray();

        $acumulado = 0;
        $inversionLabels = [];
        $inversionData = [];
        foreach ($ventasPorMes as $mes => $total) {
            $acumulado += $total;
            $inversionLabels[] = \Carbon\Carbon::parse($mes . '-01')->translatedFormat('M');
            $inversionData[] = round($acumulado, 2);
        }

        $graficoInversion = [
            'tipo' => 'line',
            'labels' => $inversionLabels,
            'datasets' => [[
                'label' => 'Inversión (S/)',
                'data' => $inversionData,
                'token' => '--bronce',
                'relleno' => true,
            ]],
            'tituloEjeY' => 'S/',
        ];

        // ── Últimas visitas (5 más recientes) ─────────────────
        $ultimasVisitas = $socio->attendances()
            ->latest('checked_in_at')
            ->take(5)
            ->get();

        return view('cliente.dashboard', [
            'socio'              => $socio,
            'kpis'               => $kpis,
            'graficoAsistencia'  => $graficoAsistencia,
            'graficoFrecuencia'  => $graficoFrecuencia,
            'graficoProgreso'    => $graficoProgreso,
            'graficoInversion'   => $graficoInversion,
            'ultimasVisitas'     => $ultimasVisitas,
            'diaMaxFrecuencia'   => $diasNombres[$diaMax] ?? null,
        ]);
    }
}
```

---

## 13. Archivos a crear/modificar

### Modificar

| Archivo | Cambio |
|---------|--------|
| `app/Http/Controllers/Cliente/DashboardController.php` | Reescribir: agregar queries de gráficas, KPIs mejorados, racha |
| `resources/views/cliente/dashboard.blade.php` | Reescribir: layout con gráficas, KPIs circulares, cards de rutina/metas |
| `resources/js/graficos.js` | Agregar soporte para `horizontal: true` (`indexAxis: 'y'`) |
| `resources/css/panel.css` | Agregar estilos para KPIs circulares, cards de rutina, layout de gráficas |
| `routes/cliente.php` | Sin cambios (ya existe la ruta GET `/cliente/`) |

### No crear

- No hay migraciones nuevas
- No hay modelos nuevos
- No hay controladores nuevos
- No hay rutas nuevas

### Dependencias

- Chart.js ya instalado y configurado
- `graficos.js` ya maneja `data-grafico` con JSON
- CSS tokens ya definen `--sangre`, `--brasa`, `--bronce`
- GSAP ya anima KPIs con `data-contador`

---

## 14. Criterios de aceptación

### KPIs

- [ ] "Días restantes" muestra número + barra circular de % consumido
- [ ] "Visitas este mes" muestra número + delta vs mes anterior (↑/↓ con color)
- [ ] "Racha" muestra semanas consecutivas con asistencia
- [ ] "Peso actual" muestra kg + delta vs primera medida
- [ ] Todos los KPIs se animan con GSAP al cargar

### Gráficas

- [ ] "Asistencia semanal" muestra bar chart de 12 semanas con datos reales
- [ ] "Tus días" muestra horizontal bar chart con frecuencia por día
- [ ] "Progreso corporal" muestra line chart dual axis (peso + grasa)
- [ ] "Inversión" muestra line chart acumulado con área rellena
- [ ] Las 4 gráficas responden a temas (light/dark)
- [ ] Las gráficas se destruyen y reconstruyen al cambiar tema

### Rutina

- [ ] Se muestra como cards por día (no tabla)
- [ ] Cada día es colapsable con Alpine.js
- [ ] Muestra nombre del programa si viene de uno
- [ ] Si no hay rutina, muestra mensaje de invitación

### Metas

- [ ] Se muestran como cards con disco de progreso
- [ ] Cada meta muestra valor actual vs objetivo
- [ ] Si no hay metas, muestra mensaje del entrenador

### Responsive

- [ ] Desktop: gráficas lado a lado (2 columnas)
- [ ] Tablet: gráficas apiladas
- [ ] Mobile: todo apilado, gráficas 180px de altura
- [ ] KPIs: 2 columnas en mobile, 4 en desktop

### No regresión

- [ ] La ruta `/cliente/` sigue funcionando
- [ ] El progress `/cliente/progreso` no se ve afectado
- [ ] Las gráficas del admin no se ven afectadas
- [ ] No hay errores de PHP ni de JS

---

## Notas de implementación

### Patrón de gráficas

Seguir exactamente el patrón existente en `graficos.js`:

1. En el controlador: armar el array PHP con la config
2. Pasar al view como `json_encode($config)`
3. En el blade: `<canvas data-grafico="{{ json_encode($config) }}"></canvas>`
4. El JS ya sabe qué hacer

### Soporte horizontal

Agregar en `graficos.js`, dentro del loop de datasets o al final:

```javascript
// Después de crear la instancia del chart:
if (rawConfig.horizontal) {
    chart.options.indexAxis = 'y';
    chart.update();
}
```

O mejor: leer `horizontal` del config global (no del dataset) y aplicarlo a `options` antes de `new Chart()`.

### KPI circular

Usar el mismo patrón que `progreso-kpi__circulo` en `progreso.blade.php`:

```css
.kpi-circular {
    width: 80px; height: 80px;
    border-radius: 50%;
    background: conic-gradient(
        var(--fuego) 0%,
        var(--fuego) var(--progreso),
        var(--acero) var(--progreso),
        var(--acero) 100%
    );
    display: grid; place-items: center;
}
.kpi-circular__centro {
    width: 60px; height: 60px;
    border-radius: 50%;
    background: var(--tarjeta);
    display: grid; place-items: center;
    font-family: var(--f-mono);
    font-size: var(--t-xl);
    font-weight: 700;
}
```

### Empty states

Cada sección debe tener un estado vacío claro:

| Sección | Empty state |
|---------|------------|
| Asistencia semanal | "Aún no tienes visitas registradas" |
| Frecuencia por día | "Aún no tienes suficientes visitas para mostrar el patrón" |
| Progreso corporal | "Registra tu peso en Progreso para ver tu curva" + CTA |
| Inversión | "Sin registros de pago aún" |
| Rutina | "Selecciona un programa en la landing o pásate por recepción" |
| Metas | "Tu entrenador aún no definió objetivos" |

### Performance

Las queries de asistencia y ventas pueden ser pesadas si el socio tiene años de historial. Optimizaciones:

1. **Asistencia semanal:** LIMIT implícito por `subWeeks(12)` — máximo 12 filas
2. **Frecuencia por día:** LIMIT implícito por `subMonths(3)` — aggregation sobre ~90 días
3. **Inversión:** LIMIT por `subMonths(12)` — máximo 12 filas
4. **Racha:** Loop de 52 queries pero cada una es `exists()` (indexada por `checked_in_at`) — aceptable
5. **Medidas:** Ya eager-loaded en la relación `measurements`

Si la racha se vuelve lenta, se puede cachear con `Cache::remember('racha_' . $socio->id, 3600, ...)`.

### Testing manual

1. Login como cliente (crear socio con datos de prueba: asistencias, ventas, medidas, rutina)
2. Verificar que las 4 gráficas muestran datos correctos
3. Verificar que los KPIs muestran números correctos con delta
4. Verificar que la rutina se muestra como cards
5. Verificar que las metas muestran progreso con discos
6. Cambiar tema (light/dark) y verificar que las gráficas se reconstruyen
7. Verificar responsive en mobile (Chrome DevTools)
8. Probar con socio sin datos (empty states)
9. Probar con socio nuevo (1 semana de asistencia, 1 medida)
10. Probar con socio con 1 año de datos (verificar performance)
