# PLAN-CORRECCIONES-TECNICAS

Auditoría técnica del código existente. Bugs, relaciones faltantes y
mejoras que no alteran la funcionalidad visible ni el diseño.

---

## 1. BUGS (causan comportamiento incorrecto)

### 1.1 `AttendanceEditRequest::getObjetoAttribute()` — retorno invertido

**Archivo:** `app/Models/AttendanceEditRequest.php`

El accessor devuelve `'staff'` cuando `attendance` **no** está eager-loaded
o cuando es null, y solo devuelve `'cliente'` si `attendance` está cargado y
no es null. Pero hay un problema de nombre: existen **dos** funciones con el
mismo nombre `getObjetoAttribute` — una retorna `Attendance|StaffAttendance|null`
(línea 50) y otra retorna `string` (línea 62). PHP se queda con la segunda.

```php
// Línea 50-53 — retorna objeto (relación)
public function getObjetivoAttribute(): Attendance|StaffAttendance|null
{
    return $this->attendance ?? $this->staffAttendance;
}

// Línea 62-65 — retorna string (tipo)
public function getObjetoAttribute(): string
{
    return ($this->relationLoaded('attendance') && $this->attendance) ? 'cliente' : 'staff';
}
```

**Problema:** El accessor de retorno (línea 50) se llama `getObjetivoAttribute`
 pero el de tipo (línea 62) se llama `getObjetoAttribute`. El de retorno está
 bien. El de tipo (línea 62) tiene el nombre correcto pero la lógica es frágil:
 si `attendance` no está eager-loaded, devuelve `'staff'` aunque el registro
 **sí** sea de cliente. La restricción CHECK de la BD garantiza que solo uno de
 los FKs es no-null, pero Eloquent no lo sabe a priori.

**Fix:** Hacer que `getObjetoAttribute()` consulte la BD directamente si la
relación no está eager-loaded, o exigir `with(['attendance', 'staffAttendance'])`
en todo query que use este accessor. La solución más robusta es la segunda
porque evita N+1:

```php
public function getObjetoAttribute(): string
{
    if ($this->relationLoaded('attendance')) {
        return $this->attendance ? 'cliente' : 'staff';
    }

    return $this->attendance()->exists() ? 'cliente' : 'staff';
}
```

**Ubicaciones que usan este accessor:** `app/Http/Controllers/Admin/AttendanceEditRequestController.php`.
Verificar que todos los queries hagan `with(['attendance', 'staffAttendance'])`.

---

### 1.2 `MemberMeasurement` — casts faltantes en medidas corporales

**Archivo:** `app/Models/MemberMeasurement.php`

Los campos `chest_cm`, `waist_cm`, `hip_cm`, `arm_cm`, `thigh_cm` y
`height_cm` no tienen cast `decimal`. MySQL los devuelve como strings y
PHP compara strings en vez de floats. Esto afecta:
- Gráficos de progreso corporal (Chart.js recibe strings, no nums)
- Cálculos de IMC que usen `height_cm` del accessor `getAlturaAttribute()`
- Cualquier lógica de comparación (`$actual > $anterior`)

```php
// Estado actual:
protected function casts(): array
{
    return [
        'measured_at'    => 'date',
        'weight_kg'      => 'decimal:2',
        'body_fat_pct'   => 'decimal:1',
        'muscle_mass_kg' => 'decimal:2',
    ];
}

// Fix — agregar los campos faltantes:
protected function casts(): array
{
    return [
        'measured_at'    => 'date',
        'weight_kg'      => 'decimal:2',
        'height_cm'      => 'decimal:1',
        'body_fat_pct'   => 'decimal:1',
        'muscle_mass_kg' => 'decimal:2',
        'chest_cm'       => 'decimal:1',
        'waist_cm'       => 'decimal:1',
        'hip_cm'         => 'decimal:1',
        'arm_cm'         => 'decimal:1',
        'thigh_cm'       => 'decimal:1',
    ];
}
```

---

### 1.3 `MatriculaController::store()` — nombre de campo inconsistente

**Archivo:** `app/Http/Controllers/Admin/MatriculaController.php`

El blade envía `name="crear_login"` (checkbox) pero la validación revisa
`crear_acceso`. Como `$request->boolean('crear_acceso')` siempre es false,
la regla condicional de `access_email` nunca se ejecuta y el login nunca
se crea aunque el usuario marque el checkbox.

```php
// Línea 40 — validación:
'crear_acceso' => ['nullable', 'boolean'],

// Línea 48 — condición de validación:
if ($request->boolean('crear_acceso')) { ... }

// Línea 70 — condición de ejecución:
if ($request->boolean('crear_login')) { ... }
```

**Fix:** Unificar a `crear_login` en todo el controller:

```php
'crear_login'  => ['nullable', 'boolean'],

if ($request->boolean('crear_login')) {
    $reglas += ['access_email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')]];
}
// ...
if ($request->boolean('crear_login')) {
    $credenciales = $this->matricula->crearLogin($socio, $datos['access_email']);
}
```

---

### 1.4 `InscripcionController::store()` — mismo bug que 1.3

**Archivo:** `app/Http/Controllers/Entrenador/InscripcionController.php`

Mismo patrón inconsistente: validación usa `crear_acceso`, ejecución usa
`crear_login`. Misma corrección que 1.3.

```php
// Línea 143 — validación:
'crear_acceso' => ['nullable', 'boolean'],

// Línea 151 — condición:
if ($request->boolean('crear_acceso')) { ... }

// Línea 173 — ejecución:
if ($request->boolean('crear_login')) { ... }
```

**Fix:** Unificar a `crear_login` igual que en 1.3.

---

### 1.5 `Sale::siguienteNumero()` — riesgo de duplicados con GymContext null

**Archivo:** `app/Models/Sale.php:77`

Si `GymContext::id()` devuelve null (ej: en un seed o un contexto sin
gimnasio activo), el `->when($gymId, ...)` se salta el filtro y bloquea
**todas** las ventas, no las de la sede. Dos sedes simultáneas podrían
generar el mismo número.

**Fix:** Lanzar excepción explícita si GymContext es null:

```php
public static function siguienteNumero(): string
{
    $gymId = GymContext::id();

    if (! $gymId) {
        throw new \RuntimeException('No hay gimnasio activo para generar número de venta.');
    }

    $ultimo = DB::transaction(function () use ($gymId) {
        return static::query()
            ->where('gym_id', $gymId)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('number');
    });

    $siguiente = $ultimo ? ((int) substr($ultimo, 2)) + 1 : 1;

    return 'V-' . str_pad((string) $siguiente, 6, '0', STR_PAD_LEFT);
}
```

---

## 2. ALPINE.JS — variable muerta en ambos formularios

### 2.1 `admin/clientes/index.blade.php`

**Archivo:** `resources/views/admin/clientes/index.blade.php`

La función JS `matricula()` define `crearAcceso` (línea 542) pero el HTML
usa `x-model="crearLogin"` (línea 502). Alpine nunca vincula el checkbox
porque la variable JS se llama distinto.

```javascript
// Línea 542 — JS:
crearAcceso: false, accessEmail: '',
```

```html
<!-- Línea 502 — HTML: -->
<input type="checkbox" name="crear_login" value="1" x-model="crearLogin">
<!-- Línea 504: -->
<div x-show="crearLogin" x-cloak ...>
```

**Fix:** Cambiar en JS `crearAcceso` por `crearLogin`:

```javascript
crearLogin: false, accessEmail: '',
```

### 2.2 `entrenador/inscripciones/index.blade.php`

**Archivo:** `resources/views/entrenador/inscripciones/index.blade.php`

Mismo problema: JS define `crearAcceso` (línea 224), HTML usa
`x-model="crearLogin"` (línea 185).

```javascript
// Línea 224:
crearAcceso: false, accessEmail: '',
```

```html
<!-- Línea 185: -->
<input type="checkbox" name="crear_login" value="1" x-model="crearLogin">
```

**Fix:** Cambiar `crearAcceso` por `crearLogin` igual que en 2.1.

---

## 3. RELACIONES FALTANTES

### 3.1 `Gym` — relaciones HasMany ausentes

**Archivo:** `app/Models/Gym.php`

El modelo `Gym` tiene `users`, `members`, `plans`, `trainers`, `sales`,
`attendances`, `testimonials`, `facilities`, `faqs`, `gallery`, `qrCodes`.
Faltan las relaciones con modelos que tienen `gym_id`:

```php
// Agregar después de qrCodes():
public function programs(): HasMany       { return $this->hasMany(Program::class); }
public function products(): HasMany       { return $this->hasMany(Product::class); }
public function conversations(): HasMany  { return $this->hasMany(Conversation::class); }
public function contactMessages(): HasMany{ return $this->hasMany(ContactMessage::class); }
public function cashClosings(): HasMany   { return $this->hasMany(CashClosing::class); }
public function staffAttendances(): HasMany { return $this->hasMany(StaffAttendance::class); }
public function attendanceEditRequests(): HasMany { return $this->hasMany(AttendanceEditRequest::class); }
public function recipeCategories(): HasMany { return $this->hasMany(RecipeCategory::class); }
public function recipes(): HasMany        { return $this->hasMany(Recipe::class); }
public function memberGoals(): HasMany    { return $this->hasMany(MemberGoal::class); }
public function mealLogs(): HasMany       { return $this->hasMany(MealLog::class); }
```

### 3.2 `Exercise` — relaciones inversas ausentes

**Archivo:** `app/Models/Exercise.php`

El modelo solo tiene `gym()`. Faltan las relaciones con las tablas hijas
que referencian `exercise_id`:

```php
// Agregar después de gym():
public function routineExercises(): HasMany
{
    return $this->hasMany(RoutineExercise::class);
}

public function programRoutineExercises(): HasMany
{
    return $this->hasMany(ProgramRoutineExercise::class);
}
```

### 3.3 `Program` — relación `routines()` ausente

**Archivo:** `app/Models/Program.php`

`Program` tiene `routineTemplates()` pero falta `routines()` para la
relación con `ProgramRoutine` (ya existe como `routineTemplates`), y
falta la relación directa con `Routine` si se implementa el
`program_id` en la tabla `routines` (ver PLAN-PROGRAMAS.md):

```php
// Agregar (solo si se implementa program_id en routines):
public function routines(): HasMany
{
    return $this->hasMany(Routine::class);
}
```

**Nota:** Esto solo aplicar cuando se ejecute PLAN-PROGRAMAS.md. No
agregar hoy si la migración `program_id` en `routines` no existe.

---

## 4. N+1 QUERIES EN ACCESSORS

### 4.1 `Membership::getPagadoAttribute()`

**Archivo:** `app/Models/Membership.php:76`

Cada vez que se accede a `$membership->pagado`, se lanza una query:
`Sale::where('membership_id', ...)->sum('total')`. En listados con
múltiples membresías, esto genera N queries extra.

**Fix Opcional:** Agregar eager loading en los controllers que muestran
este dato, o cachear con un accessor que reciba el total precargado:

```php
// En el controller, antes de la vista:
$membership->loadCount(['sales as pagado_count' => fn ($q) => $q->where('status', 'completada')]);

// O agregar al select:
$membership->load(['sales' => fn ($q) => $q->where('status', 'completada')->select('membership_id', 'total')]);
```

No modificar el accessor本身 — solo asegurar eager loading donde se use.

### 4.2 `Member::getIsUpToDateAttribute()`

**Archivo:** `app/Models/Member.php:157`

Ya maneja bien el caso: verifica `relationLoaded('currentMembership')`
antes de lanzar query. Este accessor **no necesita fix**. Solo documentar
que los controllers deben hacer `with('currentMembership')` al listar
miembros.

---

## 5. MENOR (sin fix inmediato, documentar)

### 5.1 Ruta huérfana: `admin.clientes.objetivos.store`

**Archivo:** `routes/admin.php`

La ruta POST `admin.clientes.objetivos.store` apunta a
`MemberController::storeGoal()` pero ningún blade le envía un form.

**Acción:** No tocar. Se usará cuando se implemente el dashboard de
cliente (PLAN-DASHBOARD-CLIENTE.md) o se agregue un form de metas.

### 5.2 Accesibilidad en selects de filtro

Archivos: `resources/views/admin/clientes/index.blade.php`,
`resources/views/entrenador/inscripciones/index.blade.php`

Los `<select>` de filtros no tienen `<label>` ni `aria-label`.

**Fix futuro:** Agregar `aria-label` a cada select:

```html
<select aria-label="Filtrar por estado" ...>
```

### 5.3 Sin permiso: `admin.membresias.cancelar`

Ruta protegida con `auth` pero sin middleware `permiso:`. El admin puede
todo por definición (ver AGENTS.md), así que esto es correcto. Solo
documentar que si se crea un rol que cancele membresías, necesita el
permiso `membresias.cancelar`.

---

## RESUMEN DE CAMBIOS

| Archivo | Tipo de cambio | Complejidad |
|---------|---------------|-------------|
| `app/Models/AttendanceEditRequest.php` | Fix accessor | Baja |
| `app/Models/MemberMeasurement.php` | Agregar casts | Baja |
| `app/Http/Controllers/Admin/MatriculaController.php` | Fix nombre campo | Baja |
| `app/Http/Controllers/Entrenador/InscripcionController.php` | Fix nombre campo | Baja |
| `app/Models/Sale.php` | Fix GymContext null | Baja |
| `resources/views/admin/clientes/index.blade.php` | Fix Alpine var | Baja |
| `resources/views/entrenador/inscripciones/index.blade.php` | Fix Alpine var | Baja |
| `app/Models/Gym.php` | Agregar relaciones | Baja |
| `app/Models/Exercise.php` | Agregar relaciones | Baja |
| `app/Models/Program.php` | Agregar relation (opcional) | Media |

**Total:** 10 archivos, ~30 líneas modificadas. Sin migraciones, sin
cambios de diseño, sin rotura de funcionalidad existente.

---

## VERIFICACIÓN

Después de cada cambio:

1. `php artisan migrate:status` — no debe haber migraciones pendientes
2. `php artisan route:list --path=admin/clientes` — verificar rutas
3. `php artisan tinker` — probar accessors:
   ```php
   $m = MemberMeasurement::first();
   echo $m->chest_cm; // debe ser float, no string
   ```
4. Verificar que el form de matrícula en la UI sigue mostrando el
   checkbox de "Crear login" y que al marcarlo aparece el campo de email
