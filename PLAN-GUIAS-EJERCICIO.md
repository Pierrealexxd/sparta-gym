# Plan Técnico: Programas → Mi Rutina — Guías de Ejecución y Recomendaciones

## Auditoría del Estado Actual

### Lo que YA existe (no duplicar)

| Capa | Campo/Función | Estado |
|------|---------------|--------|
| **Exercise model** | `description`, `common_mistakes`, `tips`, `video_url`, `image_path` | Completo en BD y admin |
| **Exercise admin form** | Todos los campos guía + video YouTube | Funcional |
| **Exercise computed** | `getVideoIdAttribute()`, `getVideoEmbedAttribute()` | Solo YouTube |
| **Landing ejercicios** | Modal de video (`.video`), tips, errores comunes | Completo |
| **ProgramRoutineExercise** | `sets`, `reps`, `weight_kg`, `time_seconds`, `rest_seconds`, `notes` | Completo |
| **Cliente rutina view** | Muestra nombre + prescripción + notas | **Sin guía ni video** |
| **Cliente controller** | Eager-loads `days.exercises.exercise` | **No carga campos guía explícitamente** |

### Lo que FALTA

1. **Cliente "Mi Rutina"** no muestra descripción, tips, errores comunes, video ni imagen del ejercicio
2. **No hay modal de ejercicio** en la vista del cliente (solo en la landing)
3. **video_url** solo soporta YouTube; no Google Drive, Vimeo, subida directa, ni URLs genéricas
4. **No hay guía por programa**: si el mismo ejercicio aparece en 2 programas con enfoques distintos, no hay forma de personalizar la descripción/video/notes por programa
5. **No hay recomendaciones** de alimentación, recuperación, hidratación o suplementos en ningún programa
6. **El controller del cliente** no eager-loads los campos de guía del exercise

---

## Arquitectura Propuesta

### Principio rector

**No crear módulos nuevos.** Extender lo que existe siguiendo los patrones establecidos:

- Las guías viven en **Exercise** (biblioteca compartida) y se extienden en **ProgramRoutineExercise** (overrides por programa)
- Las recomendaciones viven en **Program** (campos JSON, no tabla nueva)
- El modal del cliente **reutiliza** la estructura CSS `.video` de la landing + `.modal-info` para los detalles
- Todo comienza en **Desktop** y queda preparado para responsive

---

## FASE 1: Soporte Multi-Fuente de Video

### 1.1 Migración

**Archivo nuevo:** `database/migrations/2026_08_17_000001_add_video_source_to_exercises_table.php`

```php
Schema::table('exercises', function (Blueprint $table) {
    $table->string('video_source', 20)->default('youtube')->after('video_url');
    // youtube | vimeo | gdrive | url | upload
    $table->string('video_file_path')->nullable()->after('video_source');
    // Solo para video_source = 'upload'
});
```

**Razón:** `video_url` se mantiene como está para YouTube/Vimeo/GDrive/URL. `video_source` indica cómo interpretar `video_url`. `video_file_path` es para archivos subidos (storage local/S3).

### 1.2 Modelo Exercise — Extender

**Archivo:** `app/Models/Exercise.php`

Cambios:
- Agregar `video_source` y `video_file_path` a `$fillable`
- Agregar cast `video_source` como string (no necesita cast especial)
- Renombrar `getVideoEmbedAttribute()` a mantenerlo sin cambios para retrocompatibilidad con la landing
- Agregar nuevo atributo `getVideoUrlEmbedableAttribute()` que resuelva la URL de embed según `video_source`:

```php
public function getVideoUrlEmbedableAttribute(): ?string
{
    return match ($this->video_source) {
        'youtube' => $this->video_embed,           // youtube-nocookie.com
        'vimeo' => "https://player.vimeo.com/video/{$this->video_id}",
        'gdrive' => $this->convertGDriveToEmbed($this->video_url),
        'url' => $this->video_url,                 // iframe genérico
        'upload' => $this->video_file_path ? asset('storage/' . $this->video_file_path) : null,
        default => $this->video_embed,
    };
}
```

- Agregar método privado `convertGDriveToEmbed()` que extraiga el file ID de URLs de Google Drive (`drive.google.com/file/d/{ID}/view` o `drive.google.com/open?id={ID}`) y lo convierta a `https://drive.google.com/file/d/{ID}/preview`

### 1.3 Admin Exercise Form — Extender

**Archivos:**
- `resources/views/admin/contenido/ejercicios/index.blade.php` (modal editor inline)
- `resources/views/admin/contenido/ejercicios/form.blade.php` (formulario standalone)

Cambios en ambos:
- Cambiar el label "Video (enlace de YouTube, opcional)" por "Fuente de video"
- Agregar `<select name="video_source">` con opciones: YouTube, Vimeo, Google Drive, URL de incrustación, Video subido
- Cuando `video_source = 'upload'`, mostrar `<input type="file" name="video_file" accept="video/*">` en vez del campo URL
- Cuando `video_source ≠ 'upload'`, mostrar el campo `video_url` como está hoy
- Agregar preview del video si ya existe (iframe pequeño condicional)

**Controller ExerciseController** (`app/Http/Controllers/Admin/ExerciseController.php`):
- Agregar `video_source` y `video_file_path` a la validación en `validarDatos()`
- Agregar método `guardarVideo()` similar al existente `guardarImagen()`:
  ```php
  private function guardarVideo(Request $request, Exercise $ejercicio): void
  {
      if ($request->hasFile('video_file')) {
          if ($ejercicio->video_file_path) {
              Storage::disk('public')->delete($ejercicio->video_file_path);
          }
          $ejercicio->video_file_path = $request->file('video_file')
              ->store('ejercicios/videos', 'public');
      }
  }
  ```

### 1.4 Modelo ProgramRoutineExercise — Extender

**Archivo:** `app/Models/ProgramRoutineExercise.php`

Agregar campos de override al `$fillable`:
```php
protected $fillable = [
    // ... existentes ...
    'guide_video_url',
    'guide_video_source',
    'guide_video_file_path',
    'guide_description',
    'guide_tips',
    'guide_common_mistakes',
];
```

Agregar atributos computados:
```php
public function getEffectiveVideoEmbedAttribute(): ?string
{
    if ($this->guide_video_url) {
        return $this->resolveEmbed($this->guide_video_url, $this->guide_video_source);
    }
    return $this->exercise?->video_url_embedable;
}

public function getEffectiveDescriptionAttribute(): ?string
{
    return $this->guide_description ?? $this->exercise?->description;
}

public function getEffectiveTipsAttribute(): ?string
{
    return $this->guide_tips ?? $this->exercise?->tips;
}

public function getEffectiveCommonMistakesAttribute(): ?string
{
    return $this->guide_common_mistakes ?? $this->exercise?->common_mistakes;
}
```

### 1.5 Migración para overrides

**Archivo nuevo:** `database/migrations/2026_08_17_000002_add_guide_overrides_to_program_routine_exercises_table.php`

```php
Schema::table('program_routine_exercises', function (Blueprint $table) {
    $table->string('guide_video_url')->nullable()->after('notes');
    $table->string('guide_video_source', 20)->default('youtube')->after('guide_video_url');
    $table->string('guide_video_file_path')->nullable()->after('guide_video_source');
    $table->text('guide_description')->nullable()->after('guide_video_file_path');
    $table->text('guide_tips')->nullable()->after('guide_description');
    $table->text('guide_common_mistakes')->nullable()->after('guide_tips');
});
```

### 1.6 Admin Program Routine Form — Extender

**Archivo:** `resources/views/admin/contenido/programas/rutinas/form.blade.php`

En la sección "Agregar ejercicio" (el `<details>` existente), agregar campos de guía:
- Checkbox "Usar guía personalizada para este programa"
- Si está marcado: campos de descripción, tips, errores comunes, fuente de video + URL/archivo
- Si no está marcado: hereda todo del Exercise (comportamiento actual)

En la tabla de ejercicios existentes, agregar un indicador visual si tiene overrides.

**Controller ProgramRoutineController** (`app/Http/Controllers/Admin/ProgramRoutineController.php`):
- En `agregarEjercicio()`: agregar validación de campos de guía
- Nuevo método `editarEjercicio()` para modificar overrides de un ejercicio ya agregado (con ruta y autorización)

---

## FASE 2: Modal de Ejercicio en "Mi Rutina"

### 2.1 Cliente RoutineController — Sin cambios necesarios

**Archivo:** `app/Http/Controllers/Cliente/RoutineController.php`

Ya carga `days.exercises.exercise` lo cual trae todos los campos. No necesita cambios en la query.

### 2.2 Vista cliente/rutina.blade.php — Extender

**Archivo:** `resources/views/cliente/rutina.blade.php`

Cambios:

1. **Agregar Alpine state para el modal de ejercicio** en cada día:
   ```html
   x-data="{ 
       abierta: {{ $loop->first ? 'true' : 'false' }}, 
       ejercicioModal: null,
       abrirEjercicio(ej) { this.ejercicioModal = ej },
       cerrarEjercicio() { this.ejercicioModal = null }
   }"
   ```

2. **En cada tarjeta de ejercicio** (`rutina-ejercicio`), agregar un botón "Ver guía" condicional:
   ```html
   @if ($re->exercise->description || $re->exercise->video_url)
       <button class="btn btn--desnudo btn--pequeno" 
               @click="abrirEjercicio({
                   nombre: @js($re->exercise->name),
                   video: @js($re->effective_video_embed),
                   descripcion: @js($re->effective_description),
                   tips: @js($re->effective_tips),
                   errores: @js($re->effective_common_mistakes),
                   musculos: @js($re->exercise->muscle_groups),
                   equipo: @js($re->exercise->equipment),
                   nivel: @js($re->exercise->level),
                   imagen: @js($re->exercise->image_path ? asset('storage/' . $re->exercise->image_path) : null)
               })">
           <x-icono nombre="youtube" /> Ver guía
       </button>
   @endif
   ```

3. **Agregar el modal al final de la vista** (antes del `@endsection`), usando la estructura CSS `.video` de la landing:
   ```html
   {{-- Modal de guía de ejercicio --}}
   <div class="video" x-cloak x-show="ejercicioModal" 
        @keydown.escape.window="cerrarEjercicio()"
        role="dialog" aria-modal="true" aria-label="Guía de ejercicio">
       <div class="video__fondo" @click="cerrarEjercicio()"></div>
       <div class="video__caja">
           <button type="button" class="video__cerrar" @click="cerrarEjercicio()" aria-label="Cerrar">
               <x-icono nombre="cerrar" />
           </button>
           <h3 class="video__titulo" x-text="ejercicioModal?.nombre"></h3>
           
           {{-- Video (solo si existe) --}}
           <template x-if="ejercicioModal?.video">
               <div class="video__marco">
                   <iframe :src="ejercicioModal?.video" title="Video tutorial" loading="lazy" allowfullscreen
                           allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
               </div>
           </template>
           
           {{-- Imagen si no hay video --}}
           <template x-if="!ejercicioModal?.video && ejercicioModal?.imagen">
               <img :src="ejercicioModal?.imagen" :alt="ejercicioModal?.nombre" 
                    style="width:100%;border-radius:var(--r-md);object-fit:cover">
           </template>
           
           {{-- Detalles --}}
           <div class="ejercicio-detalle">
               <template x-if="ejercicioModal?.descripcion">
                   <div class="ejercicio-detalle__seccion">
                       <h4>Descripción</h4>
                       <p x-text="ejercicioModal?.descripcion"></p>
                   </div>
               </template>
               
               <template x-if="ejercicioModal?.tips">
                   <div class="ejercicio-detalle__seccion ejercicio-detalle__seccion--tips">
                       <h4>Hazlo así</h4>
                       <p x-text="ejercicioModal?.tips"></p>
                   </div>
               </template>
               
               <template x-if="ejercicioModal?.errores">
                   <div class="ejercicio-detalle__seccion ejercicio-detalle__seccion--errores">
                       <h4>Evita</h4>
                       <p x-text="ejercicioModal?.errores"></p>
                   </div>
               </template>
               
               <div class="ejercicio-detalle__meta">
                   <template x-if="ejercicioModal?.musculos?.length">
                       <div>
                           <h4>Músculos</h4>
                           <div class="ejercicio-detalle__musculos">
                               <template x-for="m in ejercicioModal?.musculos" :key="m">
                                   <span class="etiqueta" x-text="m"></span>
                               </template>
                           </div>
                       </div>
                   </template>
                   <template x-if="ejercicioModal?.equipo">
                       <div><h4>Equipo</h4><p x-text="ejercicioModal?.equipo"></p></div>
                   </template>
               </div>
           </div>
       </div>
   </div>
   ```

### 2.3 CSS para el modal de ejercicio

**Archivo:** `resources/css/panel.css`

Agregar al final de la sección de rutina (después de línea ~600):

```css
/* Modal de guía de ejercicio (Mi Rutina) */
.ejercicio-detalle { display: grid; gap: var(--e-4); }
.ejercicio-detalle__seccion h4 {
    font-family: var(--f-mono);
    font-size: var(--t-xs);
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--ceniza);
    margin-bottom: var(--e-2);
}
.ejercicio-detalle__seccion p { color: var(--hueso); line-height: 1.6; }
.ejercicio-detalle__seccion--tips {
    border-left: 3px solid var(--brasa);
    padding-left: var(--e-4);
}
.ejercicio-detalle__seccion--errores {
    border-left: 3px solid var(--sangre);
    padding-left: var(--e-4);
}
.ejercicio-detalle__meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: var(--e-4);
    padding-top: var(--e-4);
    border-top: 1px solid var(--acero);
}
.ejercicio-detalle__musculos { display: flex; flex-wrap: wrap; gap: var(--e-2); }
.btn--pequeno { font-size: var(--t-xs); padding: var(--e-2) var(--e-3); }
```

---

## FASE 3: Recomendaciones de Programa

### 3.1 Migración

**Archivo nuevo:** `database/migrations/2026_08_17_000003_add_recommendations_to_programs_table.php`

```php
Schema::table('programs', function (Blueprint $table) {
    $table->json('nutrition_tips')->nullable()->after('highlights');
    $table->json('recovery_tips')->nullable()->after('nutrition_tips');
    $table->json('hydration_tips')->nullable()->after('recovery_tips');
    $table->json('supplements_tips')->nullable()->after('hydration_tips');
});
```

**Por qué JSON y no tabla nueva:** Son arrays de strings simples (cada tip es un texto corto). No necesitan relaciones, timestamps, ni orden complejo. Los campos JSON ya son un patrón establecido en el proyecto (`highlights`, `muscle_groups`, `certifications`, `features`).

### 3.2 Modelo Program — Extender

**Archivo:** `app/Models/Program.php`

```php
protected $fillable = [
    // ... existentes ...
    'nutrition_tips', 'recovery_tips', 'hydration_tips', 'supplements_tips',
];

protected function casts(): array
{
    return [
        // ... existentes ...
        'highlights'       => 'array',
        'nutrition_tips'   => 'array',
        'recovery_tips'    => 'array',
        'hydration_tips'   => 'array',
        'supplements_tips' => 'array',
    ];
}
```

### 3.3 Admin Program Form — Extender

**Archivo:** `resources/views/admin/contenido/programas/index.blade.php` (modal editor inline)

Agregar una sección colapsable "Recomendaciones" dentro del modal de crear/editar programa, con 4 bloques textarea:
- **Alimentación**: uno por línea (se convierte a array JSON, mismo patrón que `highlights`)
- **Recuperación**: uno por línea
- **Hidratación**: uno por línea
- **Suplementos**: uno por línea

**Controller ProgramController** (`app/Http/Controllers/Admin/ProgramController.php`):
- En `validarDatos()`: agregar validación de los 4 campos (nullable, array)
- En `store()` y `update()`: convertir de newline-separated text a array JSON (mismo patrón que `highlights`)

### 3.4 Vista cliente — Mostrar recomendaciones

**Archivo:** `resources/views/cliente/rutina.blade.php`

Después de la tarjeta de información de la rutina (línea 38) y antes de los días, agregar una sección condicional:

```html
@if ($rutinaActiva->program)
    @php
        $tieneRecomendaciones = collect([
            $rutinaActiva->program->nutrition_tips,
            $rutinaActiva->program->recovery_tips,
            $rutinaActiva->program->hydration_tips,
            $rutinaActiva->program->supplements_tips,
        ])->filter()->isNotEmpty();
    @endphp
    
    @if ($tieneRecomendaciones)
        <div class="tarjeta recomendaciones" data-revelar>
            <h3 style="font-family:var(--f-display);font-size:var(--t-lg)">
                Recomendaciones del programa
            </h3>
            
            @if ($rutinaActiva->program->nutrition_tips)
                <div class="recomendacion">
                    <h4><x-icono nombre="comida" /> Alimentación</h4>
                    <ul>
                        @foreach ($rutinaActiva->program->nutrition_tips as $tip)
                            <li>{{ $tip }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            
            @if ($rutinaActiva->program->recovery_tips)
                <div class="recomendacion">
                    <h4><x-icono nombre="descanso" /> Recuperación</h4>
                    <ul>
                        @foreach ($rutinaActiva->program->recovery_tips as $tip)
                            <li>{{ $tip }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            
            @if ($rutinaActiva->program->hydration_tips)
                <div class="recomendacion">
                    <h4><x-icono nombre="gota" /> Hidratación</h4>
                    <ul>
                        @foreach ($rutinaActiva->program->hydration_tips as $tip)
                            <li>{{ $tip }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            
            @if ($rutinaActiva->program->supplements_tips)
                <div class="recomendacion">
                    <h4><x-icono nombre="pastilla" /> Suplementos</h4>
                    <ul>
                        @foreach ($rutinaActiva->program->supplements_tips as $tip)
                            <li>{{ $tip }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
@endif
```

### 3.5 CSS para recomendaciones

**Archivo:** `resources/css/panel.css`

```css
/* Recomendaciones del programa */
.recomendaciones { border-left: 3px solid var(--brasa); }
.recomendacion { margin-top: var(--e-4); }
.recomendacion h4 {
    font-family: var(--f-mono);
    font-size: var(--t-xs);
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--brasa);
    margin-bottom: var(--e-3);
    display: flex;
    align-items: center;
    gap: var(--e-2);
}
.recomendacion ul {
    list-style: none;
    padding: 0;
    display: grid;
    gap: var(--e-2);
}
.recomendacion li {
    color: var(--hueso);
    font-size: var(--t-sm);
    padding-left: var(--e-4);
    position: relative;
}
.recomendacion li::before {
    content: '•';
    position: absolute;
    left: 0;
    color: var(--brasa);
}
```

---

## FASE 4: Seeders de Demo

**Archivo:** `database/seeders/ProgramRoutineSeeder.php`

- Agregar `guide_video_url` con URLs de YouTube reales de demostración en al menos 4-6 ejercicios
- Agregar `guide_description`, `guide_tips`, `guide_common_mistakes` personalizados en 2-3 ejercicios por programa

**Archivo:** `database/seeders/ProgramSeeder.php`

- Agregar recomendaciones de demo para ambos programas:
  - "Ganar masa muscular": tips de alimentación rica en proteína, descanso 7-8h, hidratación 3L/día, creatina
  - "Perder grasa corporal": déficit calórico moderado, recuperación activa, agua antes de cada comida, BCAA opcional

**Archivo:** `database/seeders/ExerciseSeeder.php`

- Actualizar ejercicios que ya tienen `video_url` para que también tengan `video_source = 'youtube'`

---

## Archivos a Modificar (Resumen)

| # | Archivo | Tipo | Cambio |
|---|---------|------|--------|
| 1 | `database/migrations/2026_08_17_000001_add_video_source_to_exercises_table.php` | **Nuevo** | video_source, video_file_path |
| 2 | `database/migrations/2026_08_17_000002_add_guide_overrides_to_program_routine_exercises_table.php` | **Nuevo** | Campos de override de guía |
| 3 | `database/migrations/2026_08_17_000003_add_recommendations_to_programs_table.php` | **Nuevo** | Tips JSON de recomendaciones |
| 4 | `app/Models/Exercise.php` | **Editar** | fillable, atributo `video_url_embedable`, método `convertGDriveToEmbed` |
| 5 | `app/Models/ProgramRoutineExercise.php` | **Editar** | fillable de guía, atributos `effective_*` |
| 6 | `app/Models/Program.php` | **Editar** | fillable y casts de recomendaciones |
| 7 | `app/Http/Controllers/Admin/ExerciseController.php` | **Editar** | Validar video_source, método `guardarVideo()` |
| 8 | `app/Http/Controllers/Admin/ProgramRoutineController.php` | **Editar** | Validar campos de guía, nuevo `editarEjercicio()` |
| 9 | `app/Http/Controllers/Admin/ProgramController.php` | **Editar** | Validar y convertir recomendaciones |
| 10 | `resources/views/cliente/rutina.blade.php` | **Editar** | Modal de guía + sección de recomendaciones |
| 11 | `resources/views/admin/contenido/ejercicios/index.blade.php` | **Editar** | Select video_source, campo archivo, preview |
| 12 | `resources/views/admin/contenido/ejercicios/form.blade.php` | **Editar** | Mismos cambios que index |
| 13 | `resources/views/admin/contenido/programas/index.blade.php` | **Editar** | Sección de recomendaciones en modal |
| 14 | `resources/views/admin/contenido/programas/rutinas/form.blade.php` | **Editar** | Campos de override de guía |
| 15 | `resources/css/panel.css` | **Editar** | Estilos modal ejercicio, recomendaciones |
| 16 | `database/seeders/ProgramRoutineSeeder.php` | **Editar** | Datos demo de guías |
| 17 | `database/seeders/ProgramSeeder.php` | **Editar** | Datos demo de recomendaciones |
| 18 | `database/seeders/ExerciseSeeder.php` | **Editar** | Agregar video_source |

---

## Compatibilidad y Seguridad

### Compatibilidad con datos existentes
- `video_source` tiene default `'youtube'` → todos los ejercicios actuales siguen funcionando sin cambios
- `video_file_path` es nullable → no afecta registros existentes
- Los campos de override en `program_routine_exercises` son nullable → si están vacíos, se hereda del Exercise (comportamiento actual)
- Las recomendaciones en `programs` son nullable → programas sin recomendaciones no muestran la sección
- `getVideoEmbedAttribute()` se mantiene sin cambios → la landing sigue funcionando exactamente igual

### Seguridad
- Los campos de video_url se validan con `url` rule en todos los controladores
- Los archivos de video subidos se guardan en `storage/app/public/` (mismo disco que las imágenes)
- No se permiten scripts en las URLs de video (validación de patrones conocidos)
- La ruta `editarEjercicio()` incluye `autorizar()` para verificar que el ejercicio pertenece a la rutina del programa correcto

### Eager-loading
- El controller del cliente ya carga `days.exercises.exercise` → todos los campos de guía están disponibles
- No es necesario modificar la query del controller

---

## Pruebas Necesarias

### Unitarias
1. `Exercise::getVideoUrlEmbedableAttribute()` — test con cada tipo de fuente (youtube, vimeo, gdrive, url, upload)
2. `Exercise::convertGDriveToEmbed()` — test con URLs de Google Drive válidas e inválidas
3. `ProgramRoutineExercise::getEffectiveVideoEmbedAttribute()` — test de override y fallback
4. `ProgramRoutineExercise::getEffectiveDescriptionAttribute()` — test de override y fallback
5. `Program::nutrition_tips` — test de cast a array

### Feature/HTTP
6. Admin ejercicios — crear/editar con cada tipo de fuente de video
7. Admin ejercicios — subir archivo de video
8. Admin program rutinas — agregar ejercicio con guía personalizada
9. Admin program rutinas — agregar ejercicio sin guía (hereda del Exercise)
10. Admin programas — crear/editar con recomendaciones
11. Cliente rutina — abrir modal de ejercicio con video YouTube
12. Cliente rutina — abrir modal de ejercicio sin video (solo descripción)
13. Cliente rutina — verificar que recomendaciones se muestran cuando existen
14. Cliente rutina — verificar que no se muestra la sección cuando no hay recomendaciones

---

## Orden de Implementación Recomendado

1. **Migraciones** (1.1, 1.5, 3.1) → primero la base de datos
2. **Modelos** (1.2, 1.4, 3.2) → después los modelos
3. **Controladores admin** (1.3, 1.6, 3.3) → luego la lógica del admin
4. **Vistas admin** (1.3, 1.6, 3.3) → interfaz del admin
5. **Vista cliente** (2.2) → interfaz del cliente
6. **CSS** (2.3, 3.5) → estilos
7. **Seeders** (4) → datos demo
8. **Verificación** → `php artisan migrate:fresh --seed` y revisar admin + cliente
