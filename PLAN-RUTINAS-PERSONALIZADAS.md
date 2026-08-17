# Plan: Rutinas Personalizadas + Mejoras de Panel

> **Proyecto:** Sparta Gym — Laravel 12 · Blade · Alpine · GSAP
> **Fecha:** 2026-08-16
> **Objetivo:** Agregar la sección de rutinas personalizadas en la landing, mejorar el panel del cliente con recomendaciones nutricionales/IA, y pulir la experiencia de progreso.

---

## Índice

1. [Resumen ejecutivo](#1-resumen-ejecutivo)
2. [Estado actual](#2-estado-actual)
3. [Fase 1 — Sección "Rutinas Personalizadas" en la landing](#3-fase-1--sección-rutinas-personalizadas-en-la-landing)
4. [Fase 2 — Modal de detalle de programa](#4-fase-2--modal-de-detalle-de-programa)
5. [Fase 3 — Recomendaciones IA en el perfil del cliente](#5-fase-3--recomendaciones-ia-en-el-perfil-del-cliente)
6. [Fase 4 — Mejoras al panel de progreso](#6-fase-4--mejoras-al-panel-de-progreso)
7. [Fase 5 — Reseñas: reestructuración](#7-fase-5--reseñas-reestructuración)
8. [Base de datos: migraciones necesarias](#8-base-de-datos-migraciones-necesarias)
9. [Archivos a crear/modificar](#9-archivos-a-crearmodificar)
10. [Criterios de aceptación](#10-criterios-de-aceptación)
11. [Orden de ejecución recomendado](#11-orden-de-ejecución-recomendado)

---

## 1. Resumen ejecutivo

El sistema ya tiene la infraestructura clave:

- **Tablas:** `routines`, `routine_days`, `routine_exercises`, `exercises`, `recipes`, `recipe_portions`, `meal_logs`, `member_measurements`, `member_goals`.
- **Modelos:** `Routine`, `RoutineDay`, `RoutineExercise`, `Exercise`, `Recipe`, `MemberMeasurement`, `MemberGoal`, `Member` (con métodos de nutrición).
- **Vistas:** Landing con 9 secciones, panel del cliente con dashboard y progreso.
- **Diseño:** Sistema de tokens CSS, modales `modal-info` (landing) y `modal__fondo` (panel), Alpine.js para interactividad, GSAP para animaciones.

Lo que falta es **conectar estas piezas** en la landing (programas públicos) y **enriquecer el panel** con recomendaciones inteligentes y mejor跟踪 de progreso.

---

## 2. Estado actual

| Componente | Estado |
|------------|--------|
| Sección ejercicios (biblioteca) | Completa — cards con filtro, video modal |
| Sección guías | Completa — 3 guías hardcodeadas + CTA rutina personalizada |
| Rutinas en el panel del cliente | Solo lectura — muestra rutina asignada por el entrenador |
| Recetas compartidas | 15 recetas peruanas en BD, modelo `Recipe` funcional |
| Registro de comidas por porciones | Funcional — `meal_logs` + `meal_log_items` |
| Medidas corporales | Funcional — `member_measurements` con IMC calculado |
| Metas del socio | Funcional — `member_goals` con progreso calculado |
| Gráficas de peso/grasa | Funcional — Chart.js en `progreso.blade.php` |
| Platos habituales | Funcional — `saved_meals` con "usar hoy" |
| Sección reseñas (landing) | Solo lectura — muestra testimonios publicados |
| Reseñas en panel cliente | Funcional — formulario para enviar reseña |

---

## 3. Fase 1 — Sección "Rutinas Personalizadas" en la landing

### 3.1 Concepto

Una nueva sección **debajo de la biblioteca de ejercicios** (`#ejercicios`) que muestre los dos programas principales del gimnasio:

1. **Ganar masa muscular** — volumen, fuerza, hipertrofia
2. **Perder grasa** — definición, cardio, recomposición

Cada programa se presenta como una **tarjeta interactiva** (misma estética `tarjeta--interactiva` existente) que al hacer clic abre un **modal informativo** con el detalle del programa.

### 3.2 Estructura de la sección

```
┌─────────────────────────────────────────────────────┐
│  eyebrow: "Programas"                               │
│  h2: "Elige tu camino"                              │
│  lead: "Dos objetivos. Dos estrategias. Un solo      │
│         gimnasio."                                   │
├──────────────────────┬──────────────────────────────┤
│                      │                              │
│   ┌──────────────┐   │   ┌────────────────────┐     │
│   │   ICONO 🔥   │   │   │   ICONO ⚡         │     │
│   │              │   │   │                    │     │
│   │ GANAR MASA   │   │   │  PERDER GRASA      │     │
│   │ MUSCULAR     │   │   │  CORPORAL          │     │
│   │              │   │   │                    │     │
│   │ • Fuerza     │   │   │ • Cardio HIIT      │     │
│   │ • Hipertrofia│   │   │ • Nutrición        │     │
│   │ • 4-5 días   │   │   │ • 3-4 días         │     │
│   │              │   │   │                    │     │
│   │ [Ver programa]│  │   │ [Ver programa]     │     │
│   └──────────────┘   │   └────────────────────┘     │
│                      │                              │
└──────────────────────┴──────────────────────────────┘
```

### 3.3 Diseño de las tarjetas

Las tarjetas siguen el patrón `tarjeta--interactiva` existente:

```html
<article class="tarjeta tarjeta--interactiva programa"
         @click="abierto = 'masa'">
    <span class="tarjeta__filo"></span>
    <div class="programa__icono">
        <x-icono nombre="fuego" />
    </div>
    <h3 class="programa__nombre">Ganar masa muscular</h3>
    <p class="programa__objetivo">Volumen · Fuerza · Hipertrofia</p>
    <ul class="programa__caracteristicas">
        <li><x-icono nombre="reloj" /> 4-5 días por semana</li>
        <li><x-icono nombre="pesa" /> Progresión de carga</li>
        <li><x-icono nombre="plato" /> Plan nutricional incluido</li>
    </ul>
    <span class="btn btn--fuego programa__cta">
        Ver programa <x-icono nombre="flecha" />
    </span>
</article>
```

### 3.4 Datos desde la BD (no hardcodeados)

Crear una tabla `programs` para almacenar los programas públicos:

```sql
CREATE TABLE programs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id BIGINT UNSIGNED NULL,
    slug VARCHAR(60) UNIQUE,
    name VARCHAR(100),
    tagline VARCHAR(200) NULL,
    objective ENUM('ganar_masa', 'perder_grasa', 'fuerza', 'resistencia', 'salud', 'otro'),
    description TEXT,           -- descripción larga (HTML seguro)
    highlights JSON,            -- ["4-5 días por semana", "Progresión de carga", ...]
    icon VARCHAR(40),           -- nombre del icono SVG
    accent_color VARCHAR(9) NULL,  -- override del --fuego si se desea
    duration_weeks UNSIGNED TINYINT NULL,  -- duración típica
    difficulty ENUM('principiante', 'intermedio', 'avanzado') DEFAULT 'intermedio',
    sort_order UNSIGNED SMALLINT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    is_public BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### 3.5 Modelo `Program`

```php
// app/Models/Program.php
class Program extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'gym_id', 'slug', 'name', 'tagline', 'objective',
        'description', 'highlights', 'icon', 'accent_color',
        'duration_weeks', 'difficulty', 'sort_order',
        'is_active', 'is_public',
    ];

    protected function casts(): array {
        return [
            'highlights' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function scopePublicos(Builder $q): Builder {
        return $q->where('is_active', true)->where('is_public', true)->orderBy('sort_order');
    }
}
```

### 3.6 Seeder de contenido

Crear `ProgramSeeder.php` con los 2 programas base:

**Programa 1 — Ganar masa muscular:**
- `slug`: `ganar-masa`
- `name`: `Ganar masa muscular`
- `tagline`: `Volumen · Fuerza · Hipertrofia`
- `objective`: `ganar_masa`
- `highlights`: `["4-5 días por semana", "Progresión de carga semanal", "Plan nutricional personalizado", "Seguimiento mensual de medidas"]`
- `duration_weeks`: 12
- `difficulty`: `intermedio`
- `description`: (HTML con secciones: qué incluye, cómo funciona, qué esperar)

**Programa 2 — Perder grasa:**
- `slug`: `perder-grasa`
- `name`: `Perder grasa corporal`
- `tagline`: `Definición · Cardio · Recomposición`
- `objective`: `perder_grasa`
- `highlights`: `["3-4 días por semana", "HIIT + fuerza combinados", "Control de porciones con palmas", "Gráficas de progreso semanales"]`
- `duration_weeks`: 8
- `difficulty`: `principiante`
- `description`: (HTML similar)

### 3.7 Controlador y datos

Modificar `LandingController::index()` para incluir programas:

```php
'programs' => Program::publicos()->get(),
```

### 3.8 Animaciones GSAP

En `animations.js`, agregar la nueva sección al sistema de revelado:

```js
// La sección usa data-revelar y data-revelar-grupo como las demás
// No necesita código adicional si se respeta el patrón existente
```

### 3.9 Responsive

- **Desktop (≥741px):** Grid de 2 columnas, tarjetas lado a lado
- **Tablet (741-1024px):** Grid de 2 columnas, tarjetas más compactas
- **Mobile (≤740px):** Carrusel horizontal con snap (mismo patrón que ejercicios/testimonios)

### 3.10 CSS

```css
/* Programas — sección debajo de biblioteca */
.programas {
    display: grid; gap: var(--e-5);
    grid-template-columns: repeat(2, 1fr);
}

.programa {
    display: grid; gap: var(--e-4); text-align: center;
    cursor: pointer;
}

.programa__icono {
    width: 64px; height: 64px; margin: 0 auto;
    display: grid; place-items: center;
    background: var(--fuego); border-radius: var(--r-lg);
    color: var(--hueso);
}

.programa__nombre {
    font-family: var(--f-display); font-size: var(--t-2xl);
    text-transform: uppercase; letter-spacing: .02em;
}

.programa__objetivo {
    color: var(--ceniza); font-size: var(--t-sm);
    text-transform: uppercase; letter-spacing: .08em;
}

.programa__caracteristicas {
    list-style: none; display: grid; gap: var(--e-3);
    text-align: left; color: var(--humo);
}

.programa__caracteristicas li {
    display: flex; align-items: center; gap: var(--e-2);
    font-size: var(--t-sm);
}

.programa__caracteristicas svg {
    width: 16px; height: 16px; color: var(--brasa);
}

.programa__cta { justify-self: center; margin-top: var(--e-3); }

@media (max-width: 740px) {
    .programas {
        display: flex; overflow-x: auto;
        scroll-snap-type: x mandatory;
        scrollbar-width: none; gap: var(--e-4);
    }
    .programa { flex: 0 0 85%; scroll-snap-align: center; }
}
```

---

## 4. Fase 2 — Modal de detalle de programa

### 4.1 Concepto

Cuando el usuario hace clic en "Ver programa" (o en la tarjeta completa), se abre un **modal informativo** con el detalle completo del programa. El diseño copia el patrón `modal-info` existente.

### 4.2 Contenido del modal

El modal tiene **dos niveles de profundidad**:

**Nivel 1 — Información general** (visible para todos):
- Nombre del programa
- Descripción detallada (qué incluye, cómo funciona)
- Características destacadas
- Rutina tipo ejemplo (3-4 ejercicios representativos)
- Galería de imágenes de ejercicios del programa

**Nivel 2 — Acción** (requiere login):
- "Agendar mi evaluación" → si está logueado, muestra formulario de agendamiento
- "Empezar hoy" → redirige a `#planes` si no está logueado, o a `/mi-cuenta` si lo está

### 4.3 Estructura del modal

```html
<!-- Modal de programa — patrón modal-info existente -->
<div class="modal-info" x-cloak x-show="programaActivo"
     @keydown.escape.window="programaActivo = null"
     role="dialog" aria-modal="true"
     :aria-label="programaActivo?.nombre">

    <div class="modal-info__fondo" @click="programaActivo = null"></div>

    <div class="modal-info__caja modal-info__caja--ancho">
        <button type="button" class="modal-info__cerrar"
                @click="programaActivo = null" aria-label="Cerrar">
            <x-icono nombre="cerrar" />
        </button>

        <!-- Cabecera del programa -->
        <div class="programa-detalle__cabecera">
            <div class="programa-detalle__icono" :style="'background:' + programaActivo?.color">
                <x-icono :nombre="programaActivo?.icono" />
            </div>
            <div>
                <h2 x-text="programaActivo?.nombre" class="programa-detalle__titulo"></h2>
                <p x-text="programaActivo?.tagline" class="programa-detalle__tagline"></p>
            </div>
        </div>

        <!-- Descripción -->
        <div class="programa-detalle__descripcion"
             x-html="programaActivo?.descripcion"></div>

        <!-- Características -->
        <ul class="programa-detalle__lista">
            <template x-for="item in (programaActivo?.highlights ?? [])" :key="item">
                <li x-text="item"></li>
            </template>
        </ul>

        <!-- Rutina tipo ejemplo -->
        <div class="programa-detalle__ejemplo">
            <h3>Rutina tipo — ejemplo de un día</h3>
            <div class="programa-detalle__ejercicios">
                <!-- Cards de 3-4 ejercicios representativos -->
                @foreach ($ejerciciosEjemplo as $ejercicio)
                    <div class="programa-detalle__ejercicio">
                        <span class="etiqueta">{{ $ejercicio->category }}</span>
                        <b>{{ $ejercicio->name }}</b>
                        <small>{{ $ejercicio->equipment }}</small>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- CTA -->
        <div class="programa-detalle__acciones">
            @auth
                <a href="{{ route('cliente.dashboard') }}" class="btn btn--fuego">
                    Agendar mi evaluación
                </a>
            @else
                <a href="{{ route('login') }}" class="btn btn--fuego">
                    Empezar hoy
                </a>
                <a href="#planes" class="btn btn--vidrio"
                   @click="programaActivo = null">
                    Ver planes
                </a>
            @endauth
        </div>
    </div>
</div>
```

### 4.4 Alpine.js — estado

```js
// En la sección x-data
x-data="{
    abierto: null,
    programaActivo: null,
    programas: {{ Illuminate\Support\Js::from($programs) }},
    abrirPrograma(slug) {
        this.programaActivo = this.programas.find(p => p.slug === slug);
    }
}"
```

### 4.5 CSS del modal de programa

```css
/* Modal programa — extiende modal-info */
.modal-info__caja--ancho {
    width: min(600px, 100%);
}

.programa-detalle__cabecera {
    display: flex; align-items: center; gap: var(--e-4);
    padding-bottom: var(--e-4);
    border-bottom: 1px solid var(--acero);
}

.programa-detalle__icono {
    width: 56px; height: 56px; min-width: 56px;
    display: grid; place-items: center;
    border-radius: var(--r-lg); color: var(--hueso);
}

.programa-detalle__titulo {
    font-family: var(--f-display); font-size: var(--t-xl);
    text-transform: uppercase; margin: 0;
}

.programa-detalle__tagline {
    color: var(--ceniza); font-size: var(--t-xs);
    text-transform: uppercase; letter-spacing: .06em;
    margin: var(--e-1) 0 0;
}

.programa-detalle__descripcion {
    color: var(--humo); font-size: var(--t-sm); line-height: 1.6;
}

.programa-detalle__lista {
    list-style: none; display: grid; gap: var(--e-3);
}

.programa-detalle__lista li {
    display: flex; align-items: center; gap: var(--e-2);
    color: var(--ceniza); font-size: var(--t-sm);
}

.programa-detalle__lista li::before {
    content: ''; width: 6px; height: 6px;
    background: var(--brasa); border-radius: 50%; flex-shrink: 0;
}

.programa-detalle__ejemplo {
    background: var(--metal); border-radius: var(--r-md);
    padding: var(--e-4);
}

.programa-detalle__ejemplo h3 {
    font-size: var(--t-sm); text-transform: uppercase;
    letter-spacing: .06em; color: var(--ceniza);
    margin: 0 0 var(--e-3);
}

.programa-detalle__ejercicios {
    display: grid; gap: var(--e-3);
}

.programa-detalle__ejercicio {
    display: flex; align-items: center; gap: var(--e-3);
    padding: var(--e-3);
    background: var(--grafito); border-radius: var(--r-md);
    border: 1px solid var(--acero);
}

.programa-detalle__ejercicio .etiqueta {
    font-size: var(--t-xs); text-transform: uppercase;
    color: var(--brasa); min-width: 60px;
}

.programa-detalle__ejercicio b {
    color: var(--hueso); font-weight: 500; flex: 1;
}

.programa-detalle__ejercicio small {
    color: var(--humo); font-size: var(--t-xs);
}

.programa-detalle__acciones {
    display: flex; gap: var(--e-3); justify-content: center;
    padding-top: var(--e-4);
    border-top: 1px solid var(--acero);
}
```

---

## 5. Fase 3 — Recomendaciones IA en el perfil del cliente

### 5.1 Concepto

En la página de **perfil** (`/mi-perfil`), agregar una sección de **"Recomendaciones personalizadas"** que, basándose en los datos del socio (objetivo, peso actual, altura, IMC, nivel de actividad), genere recomendaciones de:

1. **Proteína:** cuántos gramos/scoops al día
2. **Creatina:** cuántos gramos al día
3. **Agua:** litros recomendados
4. **Calorías estimadas:** rango para su objetivo
5. **Macros:** distribución aproximada (proteína/carbohidratos/grasas)

**Nota:** Esto NO es una integración con una API de IA externa. Es un **sistema de reglas basado en la lógica de negocio** que ya existe en `Member::porcionesPara()`. Se expande con fórmulas nutricionales estándar.

### 5.2 Fuente de datos

El sistema usa los datos existentes:
- `Member::goal` (activo) → tipo de objetivo
- `Member::latestMeasurement` → peso actual
- `Member::height_cm` → altura
- `MemberMeasurement::bmi` → IMC calculado
- `Member::gender` → género (para factores de actividad)
- `Member::age` → edad

### 5.3 Fórmulas de cálculo

```php
// app/Services/NutritionAdvisor.php
class NutritionAdvisor
{
    public function __construct(private Member $member) {}

    public function recomendar(): array
    {
        $medida = $this->member->latestMeasurement;
        $meta = $this->member->goals()->activos()->first();
        $peso = $medida?->weight_kg ?? 70;
        $altura = $this->member->height_cm ?? 170;
        $edad = $this->member->age ?? 25;
        $genero = $this->member->gender ?? 'M';
        $objetivo = $meta?->type ?? 'salud';

        // TMB (Harris-Benedict revisada)
        $tmb = $genero === 'F'
            ? (447.593 + (9.247 * $peso) + (3.098 * $altura) - (4.330 * $edad))
            : (88.362 + (13.397 * $peso) + (4.799 * $altura) - (5.677 * $edad));

        // Factor de actividad (sedentario base, ajustable)
        $factorActividad = match($objetivo) {
            'ganar_muso' => 1.55,
            'perder_peso' => 1.45,
            'fuerza' => 1.6,
            'resistencia' => 1.65,
            default => 1.375,
        };

        $calorias = (int) round($tmb * $factorActividad);

        // Ajuste por objetivo
        $caloriasFinales = match($objetivo) {
            'perder_peso' => $calorias - 300,  // déficit moderado
            'ganar_musculo' => $calorias + 300, // superávit moderado
            default => $calorias,
        };

        // Proteína: 2g/kg para ganar, 1.8g/kg para perder, 1.6g/kg base
        $proteinaG = match($objetivo) {
            'ganar_musculo' => (int) round($peso * 2.0),
            'perder_peso' => (int) round($peso * 1.8),
            default => (int) round($peso * 1.6),
        };

        // Creatina: 5g/día estándar
        $creatinaG = 5;

        // Agua: 35ml/kg base + ajuste por objetivo
        $aguaML = match($objetivo) {
            'ganar_musculo' => (int) round($peso * 40),
            'perder_peso' => (int) round($peso * 38),
            default => (int) round($peso * 35),
        };

        // Macros (proteína 4cal/g, carbs 4cal/g, grasas 9cal/g)
        $grasasG = (int) round(($caloriasFinales * 0.25) / 9);
        $carbsG = (int) round(($caloriasFinales - ($proteinaG * 4) - ($grasasG * 9)) / 4);

        return [
            'calorias' => $caloriasFinales,
            'proteina_g' => $proteinaG,
            'proteina_scoops' => (int) ceil($proteinaG / 25), // ~25g por scoop
            'creatina_g' => $creatinaG,
            'agua_ml' => $aguaML,
            'agua_litros' => round($aguaML / 1000, 1),
            'grasas_g' => $grasasG,
            'carbs_g' => $carbsG,
            'objetivo' => $objetivo,
        ];
    }
}
```

### 5.4 Ubicación en el perfil

Agregar una nueva tarjeta **debajo del formulario de perfil** y **antes del cambio de contraseña**:

```html
<!-- Tarjeta de recomendaciones -->
<div class="tarjeta recomendaciones">
    <span class="tarjeta__filo"></span>
    <h3 class="recomendaciones__titulo">
        <x-icono nombre="lampara" /> Recomendaciones para ti
    </h3>

    @php
        $advisor = new \App\Services\NutritionAdvisor($socio);
        $rec = $advisor->recomendar();
    @endphp

    <div class="recomendaciones__grid">
        <!-- Proteína -->
        <div class="recomendacion">
            <div class="recomendacion__icono recomendacion__icono--proteina">
                <x-icono nombre="proteina" />
            </div>
            <div class="recomendacion__datos">
                <small>Proteína diaria</small>
                <b>{{ $rec['proteina_g'] }}g</b>
                <span>≈ {{ $rec['proteina_scoops'] }} scoops</span>
            </div>
        </div>

        <!-- Creatina -->
        <div class="recomendacion">
            <div class="recomendacion__icono recomendacion__icono--creatina">
                <x-icono nombre="polvo" />
            </div>
            <div class="recomendacion__datos">
                <small>Creatina diaria</small>
                <b>{{ $rec['creatina_g'] }}g</b>
                <span>1 cucharadita</span>
            </div>
        </div>

        <!-- Agua -->
        <div class="recomendacion">
            <div class="recomendacion__icono recomendacion__icono--agua">
                <x-icono nombre="gota" />
            </div>
            <div class="recomendacion__datos">
                <small>Agua diaria</small>
                <b>{{ $rec['agua_litros'] }}L</b>
                <span>{{ $rec['agua_ml'] }} ml</span>
            </div>
        </div>

        <!-- Calorías -->
        <div class="recomendacion">
            <div class="recomendacion__icono recomendacion__icono--calorias">
                <x-icono nombre="fuego" />
            </div>
            <div class="recomendacion__datos">
                <small>Calorías estimadas</small>
                <b>{{ number_format($rec['calorias']) }} kcal</b>
                <span>{{ ucfirst($rec['objetivo']) }}</span>
            </div>
        </div>
    </div>

    <!-- Macros -->
    <div class="recomendaciones__macros">
        <h4>Distribución de macros</h4>
        <div class="macro-barra">
            <div class="macro" style="flex:{{ $rec['proteina_g'] }}">
                <b>{{ $rec['proteina_g'] }}g</b>
                <small>Proteína</small>
            </div>
            <div class="macro macro--carbs" style="flex:{{ $rec['carbs_g'] }}">
                <b>{{ $rec['carbs_g'] }}g</b>
                <small>Carbos</small>
            </div>
            <div class="macro macro--grasas" style="flex:{{ $rec['grasas_g'] }}">
                <b>{{ $rec['grasas_g'] }}g</b>
                <small>Grasas</small>
            </div>
        </div>
    </div>

    <!-- Botón: Ver guía completa -->
    <button type="button" class="btn btn--vidrio recomendaciones__guia"
            @click="guiaNutricional = true">
        <x-icono nombre="libro" /> Ver guía completa
    </button>
</div>
```

### 5.5 Modal "Guía completa" de nutrición

Al hacer clic en "Ver guía completa", se abre un modal (patrón `modal-info`) con:

1. **Resumen de recomendaciones** (los mismos datos de la tarjeta)
2. **Tablas de referencia de porciones** (ya existen en `progreso.blade.php`)
3. **3 recetas para ganar masa muscular** (de la BD `recipes`, filtradas por tag)
4. **2 recetas para perder grasa** (de la BD `recipes`, filtradas por tag)
5. **Consejos de hidratación y suplementación**

### 5.6 Recetas en el modal

```php
// En el PerfilController o via JavaScript
$recetasMasa = Recipe::disponibles()
    ->whereJsonContains('tags', 'ganar_masa')
    ->take(3)->get();

$recetasGrasa = Recipe::disponibles()
    ->whereJsonContains('tags', 'perder_grasa')
    ->take(2)->get();
```

### 5.7 Iconos SVG necesarios

Agregar a `resources/svg/` o al sistema de iconos:
- `proteina.svg` (cámara de proteína o gota)
- `polvo.svg` (scoop de suplemento)
- `gota.svg` (gota de agua)
- `lampara.svg` (bombilla para recomendaciones)
- `libro.svg` (libro abierto para guía)
- `flecha.svg` (flecha hacia la derecha para CTA)

### 5.8 CSS de recomendaciones

```css
/* Tarjeta recomendaciones */
.recomendaciones {
    display: grid; gap: var(--e-5);
}

.recomendaciones__titulo {
    display: flex; align-items: center; gap: var(--e-2);
    font-family: var(--f-display); font-size: var(--t-lg);
    text-transform: uppercase; margin: 0;
}

.recomendaciones__titulo svg {
    width: 22px; height: 22px; color: var(--brasa);
}

.recomendaciones__grid {
    display: grid; grid-template-columns: repeat(2, 1fr);
    gap: var(--e-3);
}

.recomendacion {
    display: flex; align-items: center; gap: var(--e-3);
    padding: var(--e-4);
    background: var(--metal); border-radius: var(--r-md);
    border: 1px solid var(--acero);
}

.recomendacion__icono {
    width: 44px; height: 44px; min-width: 44px;
    display: grid; place-items: center;
    border-radius: var(--r-md); color: var(--hueso);
}

.recomendacion__icono--proteina { background: linear-gradient(135deg, #D6202B, #FF6A1F); }
.recomendacion__icono--creatina { background: linear-gradient(135deg, #B0894A, #D6A84A); }
.recomendacion__icono--agua    { background: linear-gradient(135deg, #2D7DD2, #45A5F5); }
.recomendacion__icono--calorias { background: linear-gradient(135deg, #FF6A1F, #FFB74D); }

.recomendacion__icono svg { width: 20px; height: 20px; }

.recomendacion__datos small {
    display: block; color: var(--humo); font-size: var(--t-xs);
    text-transform: uppercase; letter-spacing: .04em;
}

.recomendacion__datos b {
    display: block; color: var(--hueso); font-size: var(--t-lg);
    font-family: var(--f-mono);
}

.recomendacion__datos span {
    color: var(--ceniza); font-size: var(--t-xs);
}

/* Barra de macros */
.recomendaciones__macros h4 {
    font-size: var(--t-xs); text-transform: uppercase;
    letter-spacing: .06em; color: var(--ceniza);
    margin: 0 0 var(--e-3);
}

.macro-barra {
    display: flex; height: 36px;
    border-radius: var(--r-md); overflow: hidden;
}

.macro {
    display: grid; place-items: center;
    background: var(--sangre); color: var(--hueso);
    transition: flex .4s var(--curva);
}

.macro--carbs { background: var(--brasa); }
.macro--grasas { background: var(--bronce); }

.macro b {
    font-family: var(--f-mono); font-size: var(--t-xs);
    font-weight: 600;
}

.macro small {
    font-size: 9px; text-transform: uppercase;
    letter-spacing: .04em; opacity: .8;
}

/* Responsive */
@media (max-width: 740px) {
    .recomendaciones__grid {
        grid-template-columns: 1fr;
    }
}
```

---

## 6. Fase 4 — Mejoras al panel de progreso

### 6.1 Mejoras existentes

El panel de progreso (`/cliente/progreso`) ya es bastante completo. Se proponen las siguientes mejoras:

#### 6.1.1 — Indicador de progreso visual mejorado

Agregar un **resumen visual tipo "dashboard"** en la parte superior que muestre:

```html
<!-- Resumen de progreso — KPIs visuales -->
<div class="progreso-resumen">
    <div class="progreso-kpi">
        <x-discos :valor="$pesoActual" :max="150" />
        <small>Peso actual</small>
        <span class="progreso-kpi__delta" :class="$deltaPeso > 0 ? 'positivo' : 'negativo'">
            {{ $deltaPeso > 0 ? '+' : '' }}{{ $deltaPeso }} kg
        </span>
    </div>

    <div class="progreso-kpi">
        <div class="progreso-kpi__circulo"
             style="--progreso: {{ min($imc / 40 * 100, 100) }}%">
            <span>{{ number_format($imc, 1) }}</span>
        </div>
        <small>IMC</small>
        <span class="progreso-kpi__badge">{{ $imcCategoria }}</span>
    </div>

    <div class="progreso-kpi">
        <x-discos :valor="$grasaActual" :max="50" />
        <small>% Grasa</small>
        <span class="progreso-kpi__delta" :class="$deltaGrasa > 0 ? 'positivo' : 'negativo'">
            {{ $deltaGrasa > 0 ? '+' : '' }}{{ $deltaGrasa }}%
        </span>
    </div>

    <div class="progreso-kpi">
        <span class="progreso-kpi__numero">{{ $diasRegistro }}</span>
        <small>Días registrando</small>
    </div>
</div>
```

#### 6.1.2 — Gráfica de progreso combinada

Agregar una gráfica que muestre **peso + grasa corporal en el mismo eje** para ver la correlación:

```html
<canvas data-grafico-combinado="{{ $graficoCombinado }}"></canvas>
```

```js
// En app-panel.js o un archivo dedicado
const ctx = document.querySelector('[data-grafico-combinado]');
if (ctx) {
    const data = JSON.parse(ctx.dataset.graficoCombinado);
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.fechas,
            datasets: [
                {
                    label: 'Peso (kg)',
                    data: data.peso,
                    borderColor: '#D6202B',
                    backgroundColor: 'rgba(214, 32, 43, .1)',
                    fill: true,
                    tension: .3,
                    yAxisID: 'y',
                },
                {
                    label: '% Grasa',
                    data: data.grasa,
                    borderColor: '#FF6A1F',
                    backgroundColor: 'rgba(255, 106, 31, .1)',
                    fill: true,
                    tension: .3,
                    yAxisID: 'y1',
                },
            ],
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { type: 'linear', position: 'left', title: { display: true, text: 'Peso (kg)' } },
                y1: { type: 'linear', position: 'right', title: { display: true, text: '% Grasa' }, grid: { drawOnChartArea: false } },
            },
        },
    });
}
```

#### 6.1.3 — Tabla de historial mejorada

Mejorar la tabla de historial de medidas existente con:
- **Badges de tendencia** (↑ subió, ↓ bajó, → estable)
- **Resaltado de la fila más reciente**
- **Exportar a PDF** (futuro, usar la misma librería que los reportes de admin)

#### 6.1.4 — Recordatorios de registro

Si el socio no ha registrado hoy, mostrar un banner recordatorio:

```html
<div class="recordatorio" x-show="!registradoHoy" x-transition>
    <x-icono nombre="reloj" />
    <span>¿Ya pesaste hoy? <b>Registra tu peso</b></span>
    <button class="btn btn--vidrio btn--sm" @click="scrollAlFormulario()">
        Registrar ahora
    </button>
</div>
```

### 6.2 CSS de mejoras de progreso

```css
/* Resumen de progreso */
.progreso-resumen {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: var(--e-3); margin-bottom: var(--e-5);
}

.progreso-kpi {
    display: grid; place-items: center; gap: var(--e-2);
    padding: var(--e-5) var(--e-3);
    background: var(--grafito); border: 1px solid var(--acero);
    border-radius: var(--r-lg); text-align: center;
}

.progreso-kpi small {
    color: var(--humo); font-size: var(--t-xs);
    text-transform: uppercase; letter-spacing: .04em;
}

.progreso-kpi__numero {
    font-family: var(--f-mono); font-size: var(--t-2xl);
    color: var(--hueso); font-weight: 600;
}

.progreso-kpi__delta {
    font-family: var(--f-mono); font-size: var(--t-xs);
    padding: 2px 8px; border-radius: var(--r-sm);
}

.progreso-kpi__delta.positivo {
    color: var(--sangre); background: rgba(214, 32, 43, .15);
}

.progreso-kpi__delta.negativo {
    color: #2ecc71; background: rgba(46, 204, 113, .15);
}

.progreso-kpi__circulo {
    width: 64px; height: 64px;
    border-radius: 50%;
    background: conic-gradient(
        var(--brasa) calc(var(--progreso) * 3.6deg),
        var(--acero) 0
    );
    display: grid; place-items: center;
}

.progreso-kpi__circulo span {
    width: 52px; height: 52px;
    display: grid; place-items: center;
    border-radius: 50%; background: var(--grafito);
    font-family: var(--f-mono); font-size: var(--t-sm);
    color: var(--hueso);
}

.progreso-kpi__badge {
    font-size: var(--t-xs); padding: 2px 8px;
    border-radius: var(--r-sm);
    background: var(--metal); color: var(--ceniza);
    border: 1px solid var(--acero);
}

/* Recordatorio */
.recordatorio {
    display: flex; align-items: center; gap: var(--e-3);
    padding: var(--e-3) var(--e-4);
    background: rgba(255, 106, 31, .08);
    border: 1px solid rgba(255, 106, 31, .2);
    border-radius: var(--r-md);
    margin-bottom: var(--e-4);
    color: var(--ceniza); font-size: var(--t-sm);
}

.recordatorio svg { width: 18px; height: 18px; color: var(--brasa); }

.recordatorio b { color: var(--brasa); }

@media (max-width: 740px) {
    .progreso-resumen { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 480px) {
    .progreso-resumen { grid-template-columns: 1fr; }
}
```

---

## 7. Fase 5 — Reseñas: reestructuración

### 7.1 Estado actual

- **Landing** (`testimonios.blade.php`): Muestra testimonios publicados en carrusel. Los datos vienen de `Testimonial::publicados()`.
- **Panel cliente**: Permite enviar una reseña (textarea + rating). Se guarda como `is_published: false` hasta que admin la apruebe.

### 7.2 Propuesta de mejora

La sección de reseñas en la landing está bien. Lo que se propone:

#### 7.2.1 — Panel de cliente: mejorar la sección de reseña

En `dashboard.blade.php`, la tarjeta "Tu reseña" actual es un formulario básico. Se propone:

1. **Si ya tiene reseña publicada:** Mostrarla con estrellas, fecha, badge de "Publicada"
2. **Si tiene reseña pendiente:** Mostrarla con badge "En revisión" + opción de editar
3. **Si no tiene reseña:** Mostrar el formulario con:
   - Textarea con placeholder motivador
   - Selector de estrellas interactivo (mismo diseño que la landing)
   - Botón "Enviar reseña"
   - Texto: "Tu reseña será publicada tras revisión por el equipo."

#### 7.2.2 — Landing: agregar reseñas recientes del panel

Opcionalmente, mostrar un **counter** de cuántas reseñas tiene el gimnasio:

```html
<span class="eyebrow">{{ $testimonios->count() }}+ reseñas de clientes</span>
```

### 7.3 No se requieren migraciones

El sistema de reseñas ya funciona correctamente.

---

## 8. Base de datos: migraciones necesarias

### 8.1 Tabla `programs` (NUEVA)

```php
// database/migrations/2026_08_16_000001_create_programs_table.php
Schema::create('programs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('gym_id')->nullable()->constrained()->nullOnDelete();
    $table->string('slug', 60)->unique();
    $table->string('name', 100);
    $table->string('tagline', 200)->nullable();
    $table->enum('objective', [
        'ganar_masa', 'perder_grasa', 'fuerza',
        'resistencia', 'salud', 'otro'
    ]);
    $table->text('description');
    $table->json('highlights')->nullable();
    $table->string('icon', 40)->nullable();
    $table->string('accent_color', 9)->nullable();
    $table->unsignedTinyInteger('duration_weeks')->nullable();
    $table->enum('difficulty', [
        'principiante', 'intermedio', 'avanzado'
    ])->default('intermedio');
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->boolean('is_active')->default(true);
    $table->boolean('is_public')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['is_active', 'is_public']);
    $table->index('sort_order');
});
```

### 8.2 Archivos de migración a crear

1. `database/migrations/2026_08_16_000001_create_programs_table.php`
2. `database/seeders/ProgramSeeder.php`

### 8.3 Modelo a crear

1. `app/Models/Program.php`

### 8.4 Servicio a crear

1. `app/Services/NutritionAdvisor.php`

---

## 9. Archivos a crear/modificar

### 9.1 Archivos NUEVOS

| Archivo | Tipo | Descripción |
|---------|------|-------------|
| `app/Models/Program.php` | Modelo | Modelo para programas públicos |
| `app/Services/NutritionAdvisor.php` | Servicio | Lógica de recomendaciones nutricionales |
| `database/migrations/2026_08_16_000001_create_programs_table.php` | Migración | Tabla programs |
| `database/seeders/ProgramSeeder.php` | Seeder | Contenido de los 2 programas |
| `resources/views/landing/sections/programas.blade.php` | Blade | Nueva sección en la landing |
| `resources/svg/proteina.svg` | SVG | Icono de proteína |
| `resources/svg/polvo.svg` | SVG | Icono de suplemento |
| `resources/svg/gota.svg` | SVG | Icono de agua |
| `resources/svg/lampara.svg` | SVG | Icono de recomendación |
| `resources/svg/libro.svg` | SVG | Icono de guía |
| `resources/svg/flecha.svg` | SVG | Icono de flecha CTA |

### 9.2 Archivos a MODIFICAR

| Archivo | Cambios |
|---------|---------|
| `app/Http/Controllers/LandingController.php` | Agregar `$programs` a la vista |
| `resources/views/landing/index.blade.php` | Incluir `@include('landing.sections.programas')` después de ejercicios |
| `resources/css/landing.css` | Agregar estilos de `.programas` y `.programa-detalle` |
| `resources/views/perfil/index.blade.php` | Agregar tarjeta de recomendaciones + modal guía |
| `app/Http/Controllers/PerfilController.php` | Pasar datos de `NutritionAdvisor` y recetas a la vista |
| `resources/css/panel.css` | Agregar estilos de `.recomendaciones` y `.progreso-resumen` |
| `resources/views/cliente/progreso.blade.php` | Agregar resumen KPI visual mejorado + gráfica combinada + recordatorio |
| `resources/views/cliente/dashboard.blade.php` | Mejorar tarjeta de reseña |
| `resources/views/landing/sections/testimonios.blade.php` | Agregar counter de reseñas |
| `resources/svg/iconos.svg` | Agregar nuevos iconos al sprite |

---

## 10. Criterios de aceptación

### Landing — Rutinas Personalizadas

- [ ] La sección "Programas" aparece debajo de "La biblioteca" en la landing
- [ ] Se muestran 2 tarjetas: "Ganar masa muscular" y "Perder grasa"
- [ ] Las tarjetas tienen el mismo estilo `tarjeta--interactiva` con brillo y borde
- [ ] Al hacer clic se abre un modal con el detalle del programa
- [ ] El modal muestra descripción, características, rutina tipo ejemplo y CTA
- [ ] Si el usuario no está logueado, el CTA lleva al login/planes
- [ ] Si está logueado, el CTA lleva a "Agendar mi evaluación"
- [ ] El modal se cierra con Escape, clic en fondo, o botón cerrar
- [ ] Responsive: carrusel en mobile, grid 2 columnas en desktop
- [ ] Las animaciones GSAP se activan al hacer scroll (data-revelar)
- [ ] El contenido viene de la BD (tabla `programs`), no hardcodeado

### Panel — Recomendaciones IA

- [ ] En `/mi-perfil`, aparece una tarjeta "Recomendaciones para ti"
- [ ] Muestra: proteína (g + scoops), creatina (g), agua (L), calorías (kcal)
- [ ] Muestra barra visual de distribución de macros
- [ ] Los cálculos se basan en: peso, altura, edad, género, objetivo activo
- [ ] Botón "Ver guía completa" abre modal con recetas y tablas de referencia
- [ ] El modal muestra 3 recetas para masa y 2 para perder grasa (de la BD)
- [ ] Responsive: grid 2x2 en desktop, 1 columna en mobile

### Panel — Progreso

- [ ] Los KPIs muestran delta visual (↑↓→) con colores
- [ ] El IMC se muestra en un círculo de progreso visual
- [ ] La gráfica combinada peso+grasa se renderiza correctamente
- [ ] El recordatorio de registro aparece si no se ha registrado hoy
- [ ] Todo es responsive y funciona en mobile/tablet/desktop

### Reseñas

- [ ] El contador de reseñas se muestra en la sección de testimonios
- [ ] La tarjeta de reseña en el dashboard muestra estado (publicada/pendiente/nueva)
- [ ] El formulario de reseña tiene selector de estrellas interactivo

### General

- [ ] Ningún color, tamaño o duración literal en CSS — todo sale de tokens
- [ ] No se rompe el diseño responsive existente
- [ ] No se afecta el rendimiento (lazy loading en modales, datos cacheados)
- [ ] Los formularios tienen `@csrf` y validación
- [ ] `prefers-reduced-motion` funciona correctamente

---

## 11. Orden de ejecución recomendado

```
┌─────────────────────────────────────────────────────────┐
│  FASE 0 — Preparación                                   │
│  ├── Crear migración + modelo Program                   │
│  ├── Crear ProgramSeeder                                │
│  ├── Crear iconos SVG                                   │
│  └── Ejecutar migrate + seed                            │
├─────────────────────────────────────────────────────────┤
│  FASE 1 — Landing: sección programas                    │
│  ├── Crear programas.blade.php                          │
│  ├── Agregar include en index.blade.php                 │
│  ├── Modificar LandingController                        │
│  ├── Agregar CSS en landing.css                         │
│  └── Verificar responsive + animaciones                 │
├─────────────────────────────────────────────────────────┤
│  FASE 2 — Landing: modal de programa                    │
│  ├── Agregar modal-info a programas.blade.php           │
│  ├── Agregar Alpine.js (abrirPrograma)                  │
│  ├── Agregar CSS del modal                              │
│  └── Probar: desktop / mobile / tablet                  │
├─────────────────────────────────────────────────────────┤
│  FASE 3 — Panel: Recomendaciones IA                     │
│  ├── Crear NutritionAdvisor.php                         │
│  ├── Modificar PerfilController                         │
│  ├── Agregar tarjeta en perfil/index.blade.php          │
│  ├── Agregar modal "guía completa"                      │
│  ├── Agregar CSS en panel.css                           │
│  └── Probar con diferentes objetivos                    │
├─────────────────────────────────────────────────────────┤
│  FASE 4 — Panel: Mejoras de progreso                    │
│  ├── Agregar resumen KPI visual                         │
│  ├── Agregar gráfica combinada                          │
│  ├── Agregar recordatorio de registro                   │
│  ├── Agregar CSS en panel.css                           │
│  └── Probar responsive                                  │
├─────────────────────────────────────────────────────────┤
│  FASE 5 — Reseñas (mejoras menores)                    │
│  ├── Mejorar tarjeta de reseña en dashboard             │
│  ├── Agregar counter en landing                         │
│  └── Probar flujo completo                              │
├─────────────────────────────────────────────────────────┤
│  FASE 6 — QA final                                     │
│  ├── Probar en Chrome, Firefox, Safari                  │
│  ├── Probar responsive: 320px → 1440px                  │
│  ├── Verificar GSAP animations                         │
│  ├── Verificar prefers-reduced-motion                   │
│  ├── Verificar accesibilidad (aria-labels)              │
│  └── npm run build + verificar tamaño de bundle         │
└─────────────────────────────────────────────────────────┘
```

### Tiempo estimado por fase

| Fase | Horas estimadas |
|------|----------------|
| Fase 0 — Preparación | 1h |
| Fase 1 — Sección landing | 2h |
| Fase 2 — Modal programa | 2h |
| Fase 3 — Recomendaciones IA | 3h |
| Fase 4 — Mejoras progreso | 2h |
| Fase 5 — Reseñas | 1h |
| Fase 6 — QA final | 1h |
| **Total** | **~12h** |

---

## Notas para el equipo de ejecución

1. **Respetar los tokens CSS.** Nunca escribir colores literales. Usar `var(--fuego)`, `var(--grafito)`, etc.
2. **El patrón modal-info ya existe.** Copiar la estructura de `beneficios.blade.php` o `guias.blade.php`.
3. **Alpine.js es la única librería de interactividad.** No agregar jQuery ni otras.
4. **GSAP solo para animaciones de entrada.** El estado inicial va en CSS (`[data-revelar]`).
5. **Lazy loading en iframes de video.** Ya existe el patrón en ejercicios.
6. **Las recetas ya tienen tags JSON.** Usar `whereJsonContains` para filtrar por objetivo.
7. **El `NutritionAdvisor` es puro PHP.** Sin dependencias externas, sin APIs, sin costo.
8. **Los iconos SVG van al sprite.** Seguir el patrón de `iconos.svg` existente.
9. **Probar `prefers-reduced-motion`.** Los modales deben funcionar sin transiciones.
10. **El contenido de programas es editable desde admin** (futuro: CRUD de admin para `programs`).
