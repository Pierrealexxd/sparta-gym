# Plan: Programas + Rutinas Automáticas + Rediseño del Progreso

> **Proyecto:** Sparta Gym — Laravel 12 · Blade · Alpine · GSAP
> **Fecha:** 2026-08-16
> **Objetivo:** CRUD de programas en el admin, vinculación programa→rutina base, asignación automática al cliente, y rediseño de la vista de progreso (quitar registro de comidas, mantener mediciones/metas/notas, agregar rutina asignada).

---

## Índice

1. [Resumen ejecutivo](#1-resumen-ejecutivo)
2. [Estado actual](#2-estado-actual)
3. [Parte 1 — CRUD de Programas en el admin](#3-parte-1--crud-de-programas-en-el-admin)
4. [Parte 2 — Rutinas base por programa (plantillas)](#4-parte-2--rutinas-base-por-programa-plantillas)
5. [Parte 3 — Asignación automática al seleccionar programa](#5-parte-3--asignación-automática-al-seleccionar-programa)
6. [Parte 4 — Rediseño de la vista de progreso del cliente](#6-parte-4--rediseño-de-la-vista-de-progreso-del-cliente)
7. [Migraciones necesarias](#7-migraciones-necesarias)
8. [Archivos a crear/modificar](#8-archivos-a-crearmodificar)
9. [Criterios de aceptación](#9-criterios-de-aceptación)
10. [Orden de ejecución](#10-orden-de-ejecución)

---

## 1. Resumen ejecutivo

### Flujo completo del usuario

```
Landing → Sección "Programas" → Click en programa → Modal detalle
    → "Agendar mi evaluación" (autenticado) / "Empezar hoy" (guest)
        → Si autenticado: se asigna rutina base del programa automáticamente
        → Dashboard del cliente muestra la rutina asignada
        → Progreso muestra: rutina, mediciones, metas, notas
```

### Qué se pide

| # | Funcionalidad | Dónde |
|---|--------------|-------|
| 1 | CRUD de programas (admin) | Panel admin → Contenido web → Programas |
| 2 | Rutinas base por programa (plantillas) | Admin crea plantillas de rutina por programa |
| 3 | Asignación automática al cliente | Al hacer click en "Agendar mi evaluación" |
| 4 | Rediseño de progreso | Vista del cliente → Mi progreso |

### Qué NO se toca

- No se modifica la lógica de rutinas del entrenador (sigue creando/editando)
- No se rompe el responsive actual
- No se elimina la sección de programas de la landing (ya existe)
- No se cambia el esquema de BD existente (solo se agregan columnas/tablas)

---

## 2. Estado actual

| Componente | Estado | Notas |
|------------|--------|-------|
| Sección "Programas" en landing | **EXISTE** | `programas.blade.php` + `programa-modal.blade.php` |
| Modelo `Program` | **EXISTE** | `app/Models/Program.php` — sin `BelongsToGym` (biblioteca compartida) |
| Seeder de programas | **EXISTE** | 2 programas: ganar-masa, perder-grasa |
| Migración `programs` | **EXISTE** | `2026_08_16_120000_create_programs_table.php` |
| Admin CRUD de programas | **NO EXISTE** | No hay controlador, rutas ni vistas |
| Rutinas base por programa | **NO EXISTE** | Programs y Routines son sistemas separados |
| Asignación automática | **NO EXISTE** | El botón "Agendar mi evaluación" solo redirige al dashboard |
| Progreso del cliente | **EXISTE** | Con registro de comidas, metas, mediciones, gráficos |
| Rutina en dashboard | **EXISTE** | Solo lectura — muestra rutina asignada por entrenador |

### Arquitectura actual de tablas relacionadas

```
programs (landing content)
    └── No tiene FK a routines

routines (per-member, trainer-assigned)
    ├── routine_days
    │   └── routine_exercises → exercises
    ├── member_id → members
    └── trainer_id → trainers (nullable)
```

**Problema:** No hay forma de decir "este programa tiene esta rutina base". El admin no puede definir qué ejercicios vienen con cada programa.

---

## 3. Parte 1 — CRUD de Programas en el admin

### 3.1 Controlador: `ProgramController`

**Archivo:** `app/Http/Controllers/Admin/ProgramController.php`

Siguiendo el patrón de `PlanController` y `FaqController`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProgramController extends Controller
{
    public function index()
    {
        $programas = Program::orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.programas.index', compact('programas'));
    }

    public function create()
    {
        return view('admin.programas.create');
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'tagline'        => ['nullable', 'string', 'max:200'],
            'objective'      => ['required', 'in:ganar_masa,perder_grasa,fuerza,resistencia,salud,otro'],
            'description'    => ['required', 'string'],
            'highlights'     => ['nullable', 'array'],
            'highlights.*'   => ['string', 'max:200'],
            'icon'           => ['nullable', 'string', 'max:40'],
            'accent_color'   => ['nullable', 'string', 'max:9'],
            'duration_weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'difficulty'     => ['required', 'in:principiante,intermedio,avanzado'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
            'is_active'      => ['boolean'],
            'is_public'      => ['boolean'],
        ]);

        $datos['slug'] = Str::slug($datos['name']);

        Program::create($datos);

        return redirect()->route('admin.programas.index')
            ->with('exito', 'Programa creado.');
    }

    public function edit(Program $programa)
    {
        return view('admin.programas.edit', compact('programa'));
    }

    public function update(Request $request, Program $programa)
    {
        $datos = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'tagline'        => ['nullable', 'string', 'max:200'],
            'objective'      => ['required', 'in:ganar_masa,perder_grasa,fuerza,resistencia,salud,otro'],
            'description'    => ['required', 'string'],
            'highlights'     => ['nullable', 'array'],
            'highlights.*'   => ['string', 'max:200'],
            'icon'           => ['nullable', 'string', 'max:40'],
            'accent_color'   => ['nullable', 'string', 'max:9'],
            'duration_weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'difficulty'     => ['required', 'in:principiante,intermedio,avanzado'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
            'is_active'      => ['boolean'],
            'is_public'      => ['boolean'],
        ]);

        $datos['slug'] = Str::slug($datos['name']);

        $programa->update($datos);

        return redirect()->route('admin.programas.index')
            ->with('exito', 'Programa actualizado.');
    }

    public function destroy(Program $programa)
    {
        $programa->delete();

        return redirect()->route('admin.programas.index')
            ->with('exito', 'Programa eliminado.');
    }

    public function publicar(Program $programa)
    {
        $programa->update(['is_active' => true]);
        return back()->with('exito', 'Programa publicado.');
    }

    public function ocultar(Program $programa)
    {
        $programa->update(['is_active' => false]);
        return back()->with('exito', 'Programa ocultado.');
    }
}
```

### 3.2 Rutas

**Archivo:** `routes/admin.php` — agregar dentro del grupo `rol:admin,recepcion`:

```php
Route::resource('programas', ProgramController::class)
    ->except(['show'])
    ->parameters(['programas' => 'programa'])
    ->middleware('permiso:web.editar');

Route::post('programas/{programa}/publicar', [ProgramController::class, 'publicar'])
    ->name('programas.publicar')
    ->middleware('permiso:web.editar');

Route::post('programas/{programa}/ocultar', [ProgramController::class, 'ocultar'])
    ->name('programas.ocultar')
    ->middleware('permiso:web.editar');
```

### 3.3 Vistas

**Estructura:**

```
resources/views/admin/programas/
    index.blade.php      ← Lista de programas con publicar/ocultar/eliminar
    _form.blade.php      ← Formulario compartido (create + edit)
    create.blade.php     ← wrapper de _form
    edit.blade.php       ← wrapper de _form
```

**`index.blade.php`** — patrón de `admin/faqs/index.blade.php`:

- Tabla con columnas: Nombre, Objetivo, Dificultad, Duración, Estado, Acciones
- Botón "Nuevo programa" arriba
- Toggle publicar/ocultar (mismo patrón que FAQs)
- Botón editar (link a `admin.programas.edit`)
- Botón eliminar (form con `@method('DELETE')`)
- Paginación

**`_form.blade.php`** — formulario con:

- Campos de texto: name, tagline, description (textarea/tinymce)
- Select: objective (6 opciones), difficulty (3 opciones)
- Number: duration_weeks, sort_order
- Color picker: accent_color
- JSON editor simple: highlights (array de strings — agregar/quitar con Alpine.js)
- Checkboxes: is_active, is_public
- Icon selector: dropdown con los iconos disponibles (llama, rayo, etc.)

### 3.4 Pestaña en el admin

**Archivo:** `resources/views/admin/contenido/_pestanas.blade.php` — agregar:

```blade
<a class="pestanas__enlace" href="{{ route('admin.programas.index') }}"
   aria-current="{{ request()->routeIs('admin.programas.*') ? 'true' : 'false' }}">Programas</a>
```

### 3.5 Permisos

No se necesita un permiso nuevo: `web.editar` ya cubre contenido de la landing. Los programas son contenido público, igual que FAQs, testimonios y ejercicios.

---

## 4. Parte 2 — Rutinas base por programa (plantillas)

### 4.1 Concepto

El admin define **rutinas base** para cada programa. Estas son plantillas que se copian al momento de asignarlas a un cliente. No son rutinas reales (no tienen `member_id` hasta que se asignan).

### 4.2 Nueva tabla: `program_routines`

**Migración:** `database/migrations/2026_08_16_130000_create_program_routines_table.php`

```php
Schema::create('program_routines', function (Blueprint $table) {
    $table->id();
    $table->foreignId('program_id')->constrained()->cascadeOnDelete();
    $table->string('name', 100);           // "Rutina base - Ganar masa"
    $table->text('notes')->nullable();
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->timestamps();

    $table->index('program_id');
});

Schema::create('program_routine_days', function (Blueprint $table) {
    $table->id();
    $table->foreignId('program_routine_id')->constrained()->cascadeOnDelete();
    $table->string('name', 100);           // "Día 1 - Empuje"
    $table->string('focus', 100)->nullable(); // "Pecho y tríceps"
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->timestamps();
});

Schema::create('program_routine_exercises', function (Blueprint $table) {
    $table->id();
    $table->foreignId('program_routine_day_id')->constrained()->cascadeOnDelete();
    $table->foreignId('exercise_id')->constrained();
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->unsignedTinyInteger('sets')->default(3);
    $table->string('reps', 20)->default('8-10');  // "8-10", "12-15", "al fallo"
    $table->unsignedDecimal('weight_kg', 5, 1)->nullable();
    $table->unsignedSmallInteger('time_seconds')->nullable();
    $table->unsignedSmallInteger('rest_seconds')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

### 4.3 Modelos

**`app/Models/ProgramRoutine.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramRoutine extends Model
{
    protected $fillable = ['program_id', 'name', 'notes', 'sort_order'];

    public function program(): BelongsTo { return $this->belongsTo(Program::class); }
    public function days(): HasMany { return $this->hasMany(ProgramRoutineDay::class)->orderBy('sort_order'); }
}
```

**`app/Models/ProgramRoutineDay.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramRoutineDay extends Model
{
    protected $fillable = ['program_routine_id', 'name', 'focus', 'sort_order'];

    public function routine(): BelongsTo { return $this->belongsTo(ProgramRoutine::class, 'program_routine_id'); }
    public function exercises(): HasMany { return $this->hasMany(ProgramRoutineExercise::class)->orderBy('sort_order'); }
}
```

**`app/Models/ProgramRoutineExercise.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramRoutineExercise extends Model
{
    protected $fillable = [
        'program_routine_day_id', 'exercise_id', 'sort_order',
        'sets', 'reps', 'weight_kg', 'time_seconds', 'rest_seconds', 'notes',
    ];

    protected $casts = [
        'weight_kg' => 'decimal:1',
        'time_seconds' => 'integer',
        'rest_seconds' => 'integer',
    ];

    public function day(): BelongsTo { return $this->belongsTo(ProgramRoutineDay::class, 'program_routine_day_id'); }
    public function exercise(): BelongsTo { return $this->belongsTo(Exercise::class); }
}
```

### 4.4 Relación en Program

Agregar al modelo `Program`:

```php
public function routineTemplates(): HasMany
{
    return $this->hasMany(ProgramRoutine::class)->orderBy('sort_order');
}
```

### 4.5 CRUD de rutinas base en el admin

**Controlador:** `app/Http/Controllers/Admin/ProgramRoutineController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ProgramRoutine;
use Illuminate\Http\Request;

class ProgramRoutineController extends Controller
{
    public function index(Program $programa)
    {
        $rutinas = $programa->routineTemplates()->with('days.exercises.exercise')->get();

        return view('admin.programas.rutinas.index', compact('programa', 'rutinas'));
    }

    public function create(Program $programa)
    {
        $ejercicios = \App\Models\Exercise::disponibles()->orderBy('name')->get();

        return view('admin.programas.rutinas.create', compact('programa', 'ejercicios'));
    }

    public function store(Request $request, Program $programa)
    {
        $datos = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'notes'    => ['nullable', 'string'],
            'dias'     => ['required', 'array', 'min:1'],
            'dias.*.name'   => ['required', 'string', 'max:100'],
            'dias.*.focus'  => ['nullable', 'string', 'max:100'],
            'dias.*.ejercicios' => ['required', 'array', 'min:1'],
            'dias.*.ejercicios.*.exercise_id' => ['required', 'exists:exercises,id'],
            'dias.*.ejercicios.*.sets'   => ['nullable', 'integer', 'min:1', 'max:10'],
            'dias.*.ejercicios.*.reps'   => ['nullable', 'string', 'max:20'],
            'dias.*.ejercicios.*.rest_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        $rutina = $programa->routineTemplates()->create([
            'name'  => $datos['name'],
            'notes' => $datos['notes'] ?? null,
        ]);

        foreach ($datos['dias'] as $i => $diaData) {
            $dia = $rutina->days()->create([
                'name'       => $diaData['name'],
                'focus'      => $diaData['focus'] ?? null,
                'sort_order' => $i,
            ]);

            foreach ($diaData['ejercicios'] as $j => $ejData) {
                $dia->exercises()->create([
                    'exercise_id'   => $ejData['exercise_id'],
                    'sort_order'    => $j,
                    'sets'          => $ejData['sets'] ?? 3,
                    'reps'          => $ejData['reps'] ?? '8-10',
                    'rest_seconds'  => $ejData['rest_seconds'] ?? null,
                ]);
            }
        }

        return redirect()->route('admin.programas.rutinas.index', $programa)
            ->with('exito', 'Rutina base creada.');
    }

    public function edit(Program $programa, ProgramRoutine $rutina)
    {
        $rutina->load('days.exercises.exercise');
        $ejercicios = \App\Models\Exercise::disponibles()->orderBy('name')->get();

        return view('admin.programas.rutinas.edit', compact('programa', 'rutina', 'ejercicios'));
    }

    public function update(Request $request, Program $programa, ProgramRoutine $rutina)
    {
        // Mismo patrón que store, pero actualiza
        $datos = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'notes'    => ['nullable', 'string'],
            'dias'     => ['required', 'array', 'min:1'],
            'dias.*.name'   => ['required', 'string', 'max:100'],
            'dias.*.focus'  => ['nullable', 'string', 'max:100'],
            'dias.*.ejercicios' => ['required', 'array', 'min:1'],
            'dias.*.ejercicios.*.exercise_id' => ['required', 'exists:exercises,id'],
            'dias.*.ejercicios.*.sets'   => ['nullable', 'integer', 'min:1', 'max:10'],
            'dias.*.ejercicios.*.reps'   => ['nullable', 'string', 'max:20'],
            'dias.*.ejercicios.*.rest_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        $rutina->update(['name' => $datos['name'], 'notes' => $datos['notes'] ?? null]);

        // Borrar días existentes y recrear (simpler que sync individual)
        $rutina->days()->each(function ($dia) {
            $dia->exercises()->delete();
            $dia->delete();
        });

        foreach ($datos['dias'] as $i => $diaData) {
            $dia = $rutina->days()->create([
                'name'       => $diaData['name'],
                'focus'      => $diaData['focus'] ?? null,
                'sort_order' => $i,
            ]);

            foreach ($diaData['ejercicios'] as $j => $ejData) {
                $dia->exercises()->create([
                    'exercise_id'   => $ejData['exercise_id'],
                    'sort_order'    => $j,
                    'sets'          => $ejData['sets'] ?? 3,
                    'reps'          => $ejData['reps'] ?? '8-10',
                    'rest_seconds'  => $ejData['rest_seconds'] ?? null,
                ]);
            }
        }

        return redirect()->route('admin.programas.rutinas.index', $programa)
            ->with('exito', 'Rutina base actualizada.');
    }

    public function destroy(Program $programa, ProgramRoutine $rutina)
    {
        $rutina->days()->each(function ($dia) {
            $dia->exercises()->delete();
            $dia->delete();
        });
        $rutina->delete();

        return redirect()->route('admin.programas.rutinas.index', $programa)
            ->with('exito', 'Rutina base eliminada.');
    }
}
```

### 4.6 Rutas de rutinas base

**Archivo:** `routes/admin.php` — anidar bajo `/programas`:

```php
Route::resource('programas/{programa}/rutinas', ProgramRoutineController::class)
    ->except(['show'])
    ->parameters(['rutinas' => 'rutina'])
    ->middleware('permiso:web.editar');
```

### 4.7 Vistas de rutinas base

**Estructura:**

```
resources/views/admin/programas/rutinas/
    index.blade.php      ← Lista de rutinas base del programa
    _form.blade.php      ← Formulario con días y ejercicios (Alpine.js)
    create.blade.php
    edit.blade.php
```

**`_form.blade.php`** — formulario dinámico con Alpine.js:

- Campo nombre de rutina
- Sección de días (array dinámico):
  - Nombre del día, enfoque
  - Lista de ejercicios (dropdown de la biblioteca + sets/reps/descanso)
  - Botón "Agregar ejercicio" / "Quitar ejercicio"
  - Botón "Agregar día" / "Quitar día"
- Mismo patrón de formularios dinámicos que el CRUD de rutinas del entrenador

---

## 5. Parte 3 — Asignación automática al seleccionar programa

### 5.1 Modificar el botón "Agendar mi evaluación"

**Archivo:** `resources/views/landing/sections/programa-modal.blade.php`

Cambiar el CTA para que, si el usuario está autenticado y es cliente, envíe una solicitud POST para asignar la rutina base:

```blade
<div class="programa-detalle__acciones">
    @auth
        @if (auth()->user()->rol->name === 'cliente')
            <form method="POST" action="{{ route('cliente.programa.asignar') }}" style="display:inline">
                @csrf
                <input type="hidden" name="program_slug" :value="programaActivo?.slug">
                <button type="submit" class="btn btn--fuego">Agendar mi evaluación</button>
            </form>
        @else
            <a href="{{ route('cliente.dashboard') }}" class="btn btn--fuego">Ver panel</a>
        @endif
    @else
        <a href="{{ route('login') }}" class="btn btn--fuego">Empezar hoy</a>
        <a href="#planes" class="btn btn--vidrio" @click="programaActivo = null">Ver planes</a>
    @endauth
</div>
```

### 5.2 Ruta de asignación

**Archivo:** `routes/cliente.php` — agregar:

```php
Route::post('programa/asignar', [Cliente\ProgramController::class, 'asignar'])
    ->name('programa.asignar');
```

### 5.3 Controlador de asignación

**Archivo:** `app/Http/Controllers/Cliente/ProgramController.php`

```php
<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ProgramRoutine;
use App\Models\Routine;
use App\Models\RoutineDay;
use App\Models\RoutineExercise;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class ProgramController extends Controller
{
    public function asignar(Request $request): RedirectResponse
    {
        $request->validate([
            'program_slug' => ['required', 'string', 'exists:programs,slug'],
        ]);

        $socio = $request->user()->member()->firstOrFail();
        $programa = Program::where('slug', $request->program_slug)->firstOrFail();

        // Verificar que no tenga ya una rutina activa de este programa
        $yaTiene = Routine::where('member_id', $socio->id)
            ->where('program_id', $programa->id)
            ->where('status', 'activa')
            ->exists();

        if ($yaTiene) {
            return back()->with('info', 'Ya tienes una rutina activa de este programa.');
        }

        // Buscar la plantilla de rutina base
        $plantilla = ProgramRoutine::where('program_id', $programa->id)
            ->with('days.exercises')
            ->first();

        if (!$plantilla) {
            return back()->with('error', 'Este programa aún no tiene una rutina disponible. Pásate por recepción.');
        }

        // Clonar la plantilla como rutina real del socio
        $rutina = Routine::create([
            'gym_id'     => $socio->gym_id,
            'member_id'  => $socio->id,
            'trainer_id' => null, // Se asigna después en la evaluación
            'name'       => $plantilla->name,
            'objective'  => $programa->objective,
            'notes'      => $plantilla->notes,
            'starts_at'  => now()->toDateString(),
            'status'     => 'activa',
            'program_id' => $programa->id, // Nueva FK
        ]);

        foreach ($plantilla->days as $diaPlantilla) {
            $dia = RoutineDay::create([
                'routine_id' => $rutina->id,
                'name'       => $diaPlantilla->name,
                'focus'      => $diaPlantilla->focus,
                'sort_order' => $diaPlantilla->sort_order,
            ]);

            foreach ($diaPlantilla->exercises as $ejPlantilla) {
                RoutineExercise::create([
                    'routine_day_id' => $dia->id,
                    'exercise_id'    => $ejPlantilla->exercise_id,
                    'sort_order'     => $ejPlantilla->sort_order,
                    'sets'           => $ejPlantilla->sets,
                    'reps'           => $ejPlantilla->reps,
                    'weight_kg'      => $ejPlantilla->weight_kg,
                    'time_seconds'   => $ejPlantilla->time_seconds,
                    'rest_seconds'   => $ejPlantilla->rest_seconds,
                    'notes'          => $ejPlantilla->notes,
                ]);
            }
        }

        return redirect()->route('cliente.progreso')
            ->with('exito', 'Rutina asignada. Revisa tu progreso para ver los detalles.');
    }
}
```

### 5.4 Agregar `program_id` a `routines`

**Migración:** `database/migrations/2026_08_16_130001_add_program_id_to_routines_table.php`

```php
Schema::table('routines', function (Blueprint $table) {
    $table->foreignId('program_id')->nullable()->after('member_id')->constrained()->nullOnDelete();
});
```

Actualizar el modelo `Routine`:

```php
// Agregar a $fillable:
'program_id',

// Agregar relación:
public function program(): BelongsTo { return $this->belongsTo(Program::class); }
```

---

## 6. Parte 4 — Rediseño de la vista de progreso del cliente

### 6.1 Qué se quita

| Sección | Razón |
|---------|-------|
| "Hoy comiste" (diario de comidas por porciones) | El usuario pidió quitarlo |
| "Mis platos habituales" | Dependiente del diario de comidas |
| "Tu balanza la llevas puesta" (guía de porciones) | El usuario pidió quitarlo |
| "Equivalencias sin balanza" | Dependiente de la guía de porciones |

### 6.2 Qué se mantiene

| Sección | Notas |
|---------|-------|
| KPIs (peso, IMC, grasa, días registrando) | Se mantiene igual |
| "Registrar hoy" (formulario de peso/nota) | Se mantiene — el usuario quiere seguir registrando peso y notas |
| "Mis metas" | Se mantiene con discos de progreso |
| Gráfico "Peso y grasa corporal" | Se mantiene |
| Tabla de historial de medidas | Se mantiene |

### 6.3 Qué se agrega

| Sección | Descripción |
|---------|-------------|
| **Mi rutina asignada** | Nueva sección arriba que muestra la rutina activa del programa seleccionado, con días y ejercicios |

### 6.4 Estructura de la vista rediseñada

```
┌─────────────────────────────────────────────────┐
│ Recordatorio de peso (si hoy no registró)       │
├─────────────────────────────────────────────────┤
│ KPIs: Peso | IMC | Grasa | Días registrando     │
├────────────────────────┬────────────────────────┤
│ Registrar hoy          │ Mis metas              │
│ (peso + nota)          │ (con discos)           │
├────────────────────────┴────────────────────────┤
│ Mi rutina asignada (nueva)                      │
│ ┌─ Día 1 - Empuje · Pecho y tríceps ─────────┐ │
│ │ Ejercicio | Sets | Reps | Descanso          │ │
│ │ Press banca | 4 | 8-10 | 90s                │ │
│ │ ...                                         │ │
│ └─────────────────────────────────────────────┘ │
│ ┌─ Día 2 - Tirón · Espalda y bíceps ────────┐  │
│ │ ...                                        │  │
│ └────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────┤
│ Peso y grasa corporal (gráfico)                 │
├─────────────────────────────────────────────────┤
│ Tabla de historial de medidas                   │
└─────────────────────────────────────────────────┘
```

### 6.5 Cambios en `ProgressController`

**Archivo:** `app/Http/Controllers/Cliente/ProgressController.php`

Eliminar:
- `TIPOS_COMIDA` constante
- Variables `$comidasHoy`, `$totalHoy`, `$objetivoDiario`, `$tiposComida`, `$platosPag`
- Datos del view: `comidasHoy`, `totalHoy`, `objetivoDiario`, `tiposComida`, `platosPag`

Agregar:
- Carga de rutina activa del socio con sus días y ejercicios
- Si la rutina viene de un programa, mostrar el nombre del programa

```php
public function __invoke(Request $request): View
{
    $socio = $request->user()->member()->with([
        'measurements' => fn ($q) => $q->orderBy('measured_at'),
        'goals' => fn ($q) => $q->activos(),
        'routines' => fn ($q) => $q->activas()->with('days.exercises.exercise'),
    ])->firstOrFail();

    $medidasPag = $socio->measurements()->latest('measured_at')->paginate(10);

    // ... (resto igual pero sin meal logs)

    return view('cliente.progreso', [
        'medidasPag'   => $medidasPag,
        'socio'        => $socio,
        'ultima'       => $socio->measurements->last(),
        'primera'      => $socio->measurements->first(),
        'hoy'          => $socio->measurements->first(fn ($m) => $m->measured_at->isToday()),
        'graficoCombinado' => [ /* igual que antes */ ],
        'metas'        => [ /* igual que antes */ ],
        'rutinaActiva' => $socio->routines->first(), // Nueva variable
    ]);
}
```

### 6.6 Eliminar rutas de comidas

**Archivo:** `routes/cliente.php` — eliminar:

```php
// Eliminar estas rutas:
Route::post('progreso/comidas', ...)->name('progreso.comidas.guardar');
Route::post('platos', ...)->name('platos.guardar');
Route::post('platos/{plato}/usar', ...)->name('platos.usar');
Route::delete('platos/{plato}', ...)->name('platos.destroy');
```

### 6.7 Eliminar controladores de comidas

- `app/Http/Controllers/Cliente/SavedMealController.php` — eliminar o dejar sin rutas
- Métodos `guardarComida()` en `ProgressController` — eliminar

### 6.8 Modelo `SavedMeal` y tablas relacionadas

No eliminar las tablas `saved_meals`, `meal_logs`, `meal_log_items` — pueden ser útiles en el futuro. Solo se dejan de usar en la vista.

---

## 7. Migraciones necesarias

| # | Migración | Tabla | Acción |
|---|-----------|-------|--------|
| 1 | `2026_08_16_130000_create_program_routines_table.php` | `program_routines`, `program_routine_days`, `program_routine_exercises` | **Crear** |
| 2 | `2026_08_16_130001_add_program_id_to_routines_table.php` | `routines` | **Agregar columna** `program_id` nullable FK |

---

## 8. Archivos a crear/modificar

### Crear

| Archivo | Tipo |
|---------|------|
| `app/Http/Controllers/Admin/ProgramController.php` | Controlador |
| `app/Http/Controllers/Admin/ProgramRoutineController.php` | Controlador |
| `app/Http/Controllers/Cliente/ProgramController.php` | Controlador |
| `app/Models/ProgramRoutine.php` | Modelo |
| `app/Models/ProgramRoutineDay.php` | Modelo |
| `app/Models/ProgramRoutineExercise.php` | Modelo |
| `database/migrations/2026_08_16_130000_create_program_routines_table.php` | Migración |
| `database/migrations/2026_08_16_130001_add_program_id_to_routines_table.php` | Migración |
| `resources/views/admin/programas/index.blade.php` | Vista |
| `resources/views/admin/programas/_form.blade.php` | Vista |
| `resources/views/admin/programas/create.blade.php` | Vista |
| `resources/views/admin/programas/edit.blade.php` | Vista |
| `resources/views/admin/programas/rutinas/index.blade.php` | Vista |
| `resources/views/admin/programas/rutinas/_form.blade.php` | Vista |
| `resources/views/admin/programas/rutinas/create.blade.php` | Vista |
| `resources/views/admin/programas/rutinas/edit.blade.php` | Vista |

### Modificar

| Archivo | Cambio |
|---------|--------|
| `app/Models/Program.php` | Agregar relación `routineTemplates()` |
| `app/Models/Routine.php` | Agregar `program_id` a fillable + relación `program()` |
| `routes/admin.php` | Agregar rutas de programas y rutinas base |
| `routes/cliente.php` | Agregar ruta `programa/asignar`, eliminar rutas de comidas |
| `resources/views/admin/contenido/_pestanas.blade.php` | Agregar pestaña "Programas" |
| `resources/views/landing/sections/programa-modal.blade.php` | Modificar CTA para asignar rutina |
| `resources/views/cliente/progreso.blade.php` | Quitar comidas, agregar rutina asignada |
| `app/Http/Controllers/Cliente/ProgressController.php` | Quitar lógica de comidas, agregar rutina |
| `database/seeders/RolePermissionSeeder.php` | No necesita cambios (`web.editar` ya existe) |

---

## 9. Criterios de aceptación

### Admin CRUD de Programas

- [ ] El admin puede ver la lista de programas en `/admin/programas`
- [ ] Puede crear, editar y eliminar programas
- [ ] Puede publicar/ocultar programas (toggle)
- [ ] Los campos obligatorios son: name, objective, description, difficulty
- [ ] El slug se genera automáticamente del nombre
- [ ] La pestaña "Programas" aparece en Contenido web
- [ ] Solo usuarios con permiso `web.editar` pueden acceder

### Rutinas base por programa

- [ ] El admin puede crear rutinas base para cada programa
- [ ] Puede agregar/quitar días y ejercicios dinámicamente (Alpine.js)
- [ ] Cada ejercicio tiene: sets, reps, descanso, notas
- [ ] Las rutinas base se listan en la página del programa
- [ ] Puede editar y eliminar rutinas base

### Asignación automática

- [ ] El botón "Agendar mi evaluación" envía POST con el slug del programa
- [ ] Se verifica que el cliente no tenga ya una rutina activa de ese programa
- [ ] Se clonan todos los días y ejercicios de la plantilla
- [ ] La rutina clonada tiene `program_id` y `member_id`
- [ ] Si no hay plantilla disponible, muestra mensaje de error
- [ ] El cliente es redirigido a su progreso con mensaje de éxito

### Rediseño de progreso

- [ ] Se eliminó la sección "Hoy comiste"
- [ ] Se eliminó la sección "Mis platos habituales"
- [ ] Se eliminó la guía de porciones y equivalencias
- [ ] Se mantiene el formulario "Registrar hoy" (peso + nota)
- [ ] Se mantiene "Mis metas" con discos de progreso
- [ ] Se mantiene el gráfico de peso y grasa
- [ ] Se mantiene la tabla de historial de medidas
- [ ] Nueva sección "Mi rutina asignada" muestra días y ejercicios
- [ ] Si no hay rutina, muestra mensaje invitando a seleccionar un programa
- [ ] El responsive funciona en mobile/tablet/desktop

### No regresión

- [ ] La landing sigue funcionando igual (programas se ven correctamente)
- [ ] El dashboard del cliente sigue mostrando rutinas del entrenador
- [ ] Las rutinas del entrenador no se ven afectadas
- [ ] Los permisos existentes siguen funcionando
- [ ] No hay errores de SQL ni de PHP

---

## 10. Orden de ejecución

| Paso | Descripción | Dependencias |
|------|-------------|--------------|
| 1 | Crear migración `program_routines` | Ninguna |
| 2 | Crear migración `add_program_id_to_routines` | Ninguna |
| 3 | Crear modelos `ProgramRoutine`, `ProgramRoutineDay`, `ProgramRoutineExercise` | Paso 1 |
| 4 | Actualizar modelo `Program` (agregar relación) | Paso 3 |
| 5 | Actualizar modelo `Routine` (agregar `program_id` + relación) | Paso 2 |
| 6 | Crear `ProgramController` (admin CRUD) | Paso 4 |
| 7 | Crear vistas de administración de programas | Paso 6 |
| 8 | Agregar rutas de admin | Paso 6 |
| 9 | Agregar pestaña "Programas" en `_pestanas.blade.php` | Paso 7 |
| 10 | Crear `ProgramRoutineController` | Paso 3 |
| 11 | Crear vistas de rutinas base | Paso 10 |
| 12 | Agregar rutas de rutinas base | Paso 10 |
| 13 | Crear `Cliente\ProgramController` (asignación) | Paso 5 |
| 14 | Modificar `programa-modal.blade.php` (CTA) | Paso 13 |
| 15 | Agregar ruta `programa/asignar` en `routes/cliente.php` | Paso 13 |
| 16 | Modificar `ProgressController` (quitar comidas, agregar rutina) | Paso 5 |
| 17 | Rediseñar `progreso.blade.php` | Paso 16 |
| 18 | Eliminar rutas de comidas en `routes/cliente.php` | Paso 16 |
| 19 | Ejecutar migraciones | Pasos 1-2 |
| 20 | Seedear rutinas base para los 2 programas existentes | Paso 19 |
| 21 | Verificar responsive en mobile/tablet/desktop | Todos |
| 22 | Verificar que la landing no se rompió | Todos |

---

## Notas de implementación

### Seeder de rutinas base

Crear `database/seeders/ProgramRoutineSeeder.php` que defina rutinas base para los 2 programas existentes:

- **Ganar masa muscular:** 4 días (Empuje, Tirón, Piernas, Full body)
- **Perder grasa corporal:** 3 días (Full body + HIIT, Fuerza + Cardio, Activo)

Usar ejercicios que ya existan en la BD (ver `ExerciseSeeder`).

### Alpine.js en formularios de rutinas

El formulario de rutinas base necesita un array dinámico de días, cada uno con un array dinámico de ejercicios. Mismo patrón que el CRUD de rutinas del entrenador (`resources/views/entrenador/rutinas/_form.blade.php`).

### Validación de duplicados

El controlador de asignación verifica que el cliente no tenga ya una rutina activa del mismo programa. Si la tiene, muestra un mensaje informativo sin error.

### Rutina sin plantilla

Si un programa no tiene rutina base definida, el botón "Agendar mi evaluación" muestra un mensaje: "Este programa aún no tiene una rutina disponible. Pásate por recepción."

### Testing manual

1. Login como admin → ir a Contenido web → Programas
2. Crear un programa con 1 rutina base de 2 días y 2 ejercicios cada uno
3. Login como cliente → ir a landing → seleccionar el programa → "Agendar mi evaluación"
4. Verificar que se creó la rutina en la BD
5. Ir a progreso → verificar que aparece la rutina asignada
6. Verificar que se eliminó la sección de comidas
7. Verificar responsive en mobile
