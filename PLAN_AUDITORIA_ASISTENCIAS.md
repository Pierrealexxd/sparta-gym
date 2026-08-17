# Plan Técnico — Auditoría y Mejora del Módulo de Asistencias del Administrador

> **Fecha:** 17-08-2026
> **Alcance:** Diagnóstico completo + plan de implementación
> **Regla:** Este archivo NO modifica el proyecto. Es un documento de planificación.

---

## PARTE 1 — DIAGNÓSTICO (Auditoría)

### 1.1 Estado actual del esquema de base de datos

Hay **dos tablas** de asistencia, con propósitos distintos:

| Tabla | Propósito | Modelo |
|-------|-----------|--------|
| `attendances` | Torno de entrada de **socios/clientes** (QR, código, búsqueda, manual) | `App\Models\Attendance` |
| `staff_attendances` | Fichaje **laboral del staff** (entrenadores marcan entrada/salida de trabajo) | `App\Models\StaffAttendance` |

**El módulo "Asistencia" del administrador SOLO muestra `staff_attendances`** (marcaciones laborales). La entrada de socios solo aparece como KPI en el dashboard.

#### Tabla `staff_attendances` (la que importa para este plan)

| Columna | Tipo | Nullable | Notas |
|---------|------|----------|-------|
| `id` | bigint | NO | PK |
| `gym_id` | FK → gyms | NO | CASCADE DELETE |
| `user_id` | FK → users | NO | CASCADE DELETE (el entrenador) |
| `clocked_in_at` | datetime | NO | Momento de entrada |
| `clocked_out_at` | datetime | SÍ | NULL = sigue en turno |
| `turno` | enum(manana,tarde,doble) | NO | default 'manana' |
| `method` | enum(manual,qr,geo) | NO | Cómo se registró |
| `location_lat` | decimal(10,8) | SÍ | Latitud GPS |
| `location_lng` | decimal(11,8) | SÍ | Longitud GPS |
| `created_at/updated_at` | timestamp | — | |

**Índices:**
- `['gym_id', 'clocked_in_at']`
- `['user_id', 'clocked_in_at']`

#### Tabla `attendances` (socios) — NO tiene GPS

| Columna | Tipo | Notas |
|---------|------|-------|
| `gym_id` | FK | gym |
| `member_id` | FK → members | socio |
| `registered_by` | FK → users (nullable) | quién registró |
| `checked_in_at` | datetime | entrada |
| `checked_out_at` | datetime (nullable) | salida |
| `method` | enum(qr,codigo,busqueda,manual) | método |
| `attended_on` | date (GENERATED) | `DATE(checked_in_at)`, column stored |

**NO tiene:** `location_lat`, `location_lng`, `trainer_id`, `qr_data`.

### 1.2 Estado actual del módulo Admin → Asistencia

**Archivos involucrados:**

| Archivo | Propósito |
|---------|-----------|
| `app/Http/Controllers/Admin/AttendanceController.php` | Un solo método: `calendario()` |
| `app/Http/Controllers/Admin/AttendanceEditRequestController.php` | Cola de correcciones (aprobar/rechazar) |
| `resources/views/admin/asistencia/calendario.blade.php` | Vista calendario de marcaciones laborales |
| `resources/views/admin/asistencia/solicitudes.blade.php` | Tabla de solicitudes de corrección |
| `resources/views/admin/asistencia/_pestanas.blade.php` | Navegación Calendario / Solicitudes |
| `resources/views/components/calendario.blade.php` | Componente calendario reutilizable |
| `routes/admin.php` (líneas 73-86) | Rutas del módulo |

**Rutas actuales:**

| Método | URL | Nombre | Permiso |
|--------|-----|--------|---------|
| GET | `/admin/asistencia/calendario` | `admin.asistencia.calendario` | `asistencia.ver` |
| GET | `/admin/asistencia/solicitudes` | `admin.asistencia.solicitudes.index` | `asistencia.aprobar` |
| GET | `.../solicitudes/pendientes.json` | `admin.asistencia.solicitudes.pendientes-json` | `asistencia.aprobar` |
| POST | `.../solicitudes/{solicitud}/aprobar` | `admin.asistencia.solicitudes.aprobar` | `asistencia.aprobar` |
| POST | `.../solicitudes/{solicitud}/rechazar` | `admin.asistencia.solicitudes.rechazar` | `asistencia.aprobar` |

**Lo que muestra hoy:**
- Vista Calendario: cuadrícula mensual con conteo de marcaciones por día. Click en día → modal con lista de marcaciones mostrando: nombre del entrenador, turno, método, hora de salida, icono 📍 si tiene ubicación, sede (modo multi-gym).
- **NO tiene vista Lista.**
- **NO tiene detalle expandido por marcación** (solo la info resumida en el modal del día).
- **NO muestra coordenadas ni mapa** en ningún punto.

### 1.3 Estado actual del flujo QR (captura de GPS)

**Archivos involucrados:**

| Archivo | Propósito |
|---------|-----------|
| `resources/js/escaneo-qr.js` | Lógica de cámara + decodificación + GPS + POST |
| `resources/views/entrenador/asistencia/_escaneo-qr.blade.php` | Modal Alpine.js del escáner |
| `app/Http/Controllers/Entrenador/AttendanceController.php:228-284` | Endpoint `marcarPorQr()` |
| `app/Services/AsistenciaService.php:72-132` | Lógica de negocio `marcarStaff()` |
| `routes/entrenador.php:72-78` | Rutas `estado` y `qr` |

**Flujo completo de captura GPS en el QR:**

```
Entrenador escanea QR
  → jsQR() decodifica token UUID
  → Cámara se detiene
  → Estado cambia a 'ubicando'
  → navigator.geolocation.getCurrentPosition() se ejecuta
    → OPCIÓN A: GPS aceptado → { lat, lng }
    → OPCIÓN B: GPS deniado/no disponible → null
  → POST a /entrenador/asistencia/qr con { token, turno, lat, lng }
  → Backend resuelve token → gym_id, valida, crea StaffAttendance con location_lat/location_lng
  → Respuesta JSON: { ok, tipo, hora, sede, turno, nombre, dni }
```

**Configuración de geolocalización:**
```javascript
{
    enableHighAccuracy: true,   // GPS del dispositivo, no WiFi
    timeout: 8000,              // 8 segundos máximo
    maximumAge: 60000,          // Cache de 1 minuto
}
```

**Cuando el usuario rechaza GPS:**
- `obtenerUbicacion()` es una Promise que **nunca rechaza**: resuelve `null` en cualquier error
- La marcación **igual se procesa** con `location_lat = NULL` y `location_lng = NULL`
- **No se muestra error** al usuario sobre GPS
- **No se ofrece retry** de GPS

**Validación backend (líneas 238-241 del controlador):**
```php
'lat' => ['nullable', 'numeric', 'between:-90,90'],
'lng' => ['nullable', 'numeric', 'between:-180,180'],
```

**Almacenamiento (AsistenciaService línea 127-128):**
```php
'location_lat' => $lat,
'location_lng' => $lng,
```

**Al cierre de turno (salida), se preservan las coordenadas anteriores si las nuevas son null:**
```php
'location_lat' => $lat ?? $abierta->location_lat,
'location_lng' => $lng ?? $abierta->location_lng,
```

### 1.4 El componente `<x-alterna-vista>` (referencia clave)

Ya existe un componente para alternar entre vista Lista y Calendario:

**Archivo:** `resources/views/components/alterna-vista.blade.php`

```blade
@props(['clave', 'defecto' => 'lista'])
<div x-data="{ vista: localStorage.getItem('vista:{{ $clave }}') || '{{ $defecto }}' }"
     x-effect="localStorage.setItem('vista:{{ $clave }}', vista)">
    <nav class="pestanas__nav">
        <button @click="vista = 'lista'">Lista</button>
        <button @click="vista = 'calendario'">Calendario</button>
    </nav>
    <div x-show="vista === 'lista'">{{ $lista }}</div>
    <div x-show="vista === 'calendario'">{{ $calendario }}</div>
</div>
```

- Persiste preferencia en `localStorage` por clave de módulo
- Actualmente **solo se usa** en `entrenador/asistencia/mi-marcacion.blade.php`
- Es el componente perfecto para reutilizar

### 1.5 Componente `<x-calendario>` (reutilizable)

**Archivo:** `resources/views/components/calendario.blade.php`

- Cuadrícula mensual de 7 columnas con CSS Grid
- Navegación mes anterior/siguiente
- Días con actividad se resaltan con `calendario__celda--con-actividad`
- Click en día abre modal con `$slot` (contenido inyectado por el padre)
- Alpine.js: `x-data="{ diaAbierto: null, itemAbierto: null }"`
- Props: `ruta`, `anterior`, `siguiente`, `celdas`, `contadorTexto`, `filtros`

### 1.6 CSS existente para calendario y tablas

En `resources/css/panel.css` (líneas 1264-1371) ya existe:
- `.calendario__nav`, `.calendario__titulo`, `.calendario` (grid)
- `.calendario__cabecera`, `.calendario__celda`, `.calendario__celda--vacia`
- `.calendario__celda--con-actividad` (con gradiente sangre/brasa)
- `.calendario__numero`, `.calendario__contador`
- `.calendario__lista`, `.calendario__rutina`, `.calendario__meta`, `.calendario__hora`
- `.calendario__detalle`, `.calendario__detalle-lista` (para detalle expandido)
- `.tabla`, `.tabla--tarjetas`, `.tabla-envoltorio` (para vista lista)

### 1.7 Resumen de hallazgos

| Pregunta | Respuesta |
|----------|-----------|
| ¿Se captura GPS al escanear QR? | **SÍ** — `navigator.geolocation.getCurrentPosition()` después de leer el QR |
| ¿Se almacena en BD? | **SÍ** — en `staff_attendances.location_lat/location_lng` (decimal 10,8 / 11,8) |
| ¿Qué pasa si el usuario niega GPS? | Silenciosamente se guarda NULL, la marcación prosigue |
| ¿Se muestra en el admin? | **Parcialmente** — solo el icono 📍 si `location_lat` existe, sin coordenadas ni mapa |
| ¿Hay vista Lista en admin? | **NO** — solo vista Calendario |
| ¿Hay modal de detalle por marcación? | **NO** — solo el resumen en el modal del día |
| ¿Existe componente de alternancia Lista/Calendario? | **SÍ** — `<x-alterna-vista>` (solo usado en entrenador) |

---

## PARTE 2 — PLAN DE IMPLEMENTACIÓN

### 2.1 Objetivo

1. Agregar **vista Lista** intercambiable con la Calendario en el módulo admin de asistencias
2. Crear **modal de detalle** por marcación que muestre: entrenador, fecha, hora, turno, método, sede, y **ubicación GPS** (coordenadas + mapa)
3. No alterar el flujo QR de captura de GPS (ya funciona correctamente)

### 2.2 Archivos a modificar

| # | Archivo | Cambio |
|---|---------|--------|
| 1 | `app/Http/Controllers/Admin/AttendanceController.php` | Agregar método `lista()` + modificar `calendario()` para pasar datos a ambos modos |
| 2 | `routes/admin.php` | Agregar ruta GET `asistencia/lista` |
| 3 | `resources/views/admin/asistencia/calendario.blade.php` | Reestructurar para envolver en `<x-alterna-vista>` con slots lista y calendario |
| 4 | `resources/views/admin/asistencia/_lista.blade.php` | **NUEVO** — vista tabla de marcaciones laborales con paginación |
| 5 | `resources/views/admin/asistencia/_detalle-marcacion.blade.php` | **NUEVO** — modal de detalle de una marcación individual |
| 6 | `resources/css/panel.css` | Agregar estilos para el mapa embebido en el modal de detalle (si es necesario) |

### 2.3 Detalle de cambios por archivo

---

#### 2.3.1 `app/Http/Controllers/Admin/AttendanceController.php`

**Cambios necesarios:**

1. **Nuevo método `lista(Request $request): View`** — Devuelve vista de tabla paginada con los mismos filtros que el calendario (entrenador, método, mes, año). Datos: `StaffAttendance::with(['user', 'gym'])`, filtrado por `GymContext`, con paginación de 20 registros.

2. **Nuevo método `detalle(StaffAttendance $marcacion): JsonResponse`** — Devuelve JSON con los datos completos de una marcación individual (incluidas coordenadas). Endpoint para que el modal de detalle los consuma vía AJAX.

3. **Modificar `calendario()` existente** — Sin cambios sustanciales; solo asegurar que `$porDia` tenga `location_lat`/`location_lng` disponibles (ya los trae porque `StaffAttendance` los tiene en `$fillable`). El eager loading `with(['user', 'gym'])` ya está.

**Estructura propuesta del controlador:**

```php
class AttendanceController extends Controller
{
    public function __construct(private readonly AsistenciaService $asistencias) {}

    // EXISTENTE — sin cambios significativos
    public function calendario(Request $request): View { ... }

    // NUEVO — vista lista con paginación
    public function lista(Request $request): View
    {
        $mes  = (int) $request->integer('mes', now()->month);
        $anio = (int) $request->integer('anio', now()->year);
        $entrenador = $request->integer('entrenador') ?: null;
        $metodo     = $request->string('metodo')->trim()->toString() ?: null;

        $inicio = Carbon::create($anio, $mes, 1)->startOfMonth();
        $fin    = $inicio->copy()->endOfMonth();

        $marcaciones = StaffAttendance::with(['user', 'gym'])
            ->when(GymContext::id(), fn ($q, $gymId) => $q->where('gym_id', $gymId))
            ->whereBetween('clocked_in_at', [$inicio, $fin])
            ->when($entrenador, fn ($q, $id) => $q->where('user_id', $id))
            ->when(in_array($metodo, ['manual', 'qr'], true), fn ($q, $m) => $q->where('method', $m))
            ->latest('clocked_in_at')
            ->paginate(20)
            ->withQueryString();

        $entrenadores = User::whereIn('id', StaffAttendance::select('user_id')
            ->whereBetween('clocked_in_at', [$inicio, $fin])->distinct())
            ->orderBy('name')->get();

        return view('admin.asistencia.lista', compact(
            'marcaciones', 'mes', 'anio', 'entrenadores', 'entrenador', 'metodo', 'inicio', 'fin',
        ));
    }

    // NUEVO — detalle individual (JSON para modal)
    public function detalle(StaffAttendance $marcacion): JsonResponse
    {
        $marcacion->load(['user', 'gym']);

        return response()->json([
            'id'            => $marcacion->id,
            'entrenador'    => $marcacion->user?->name ?? '—',
            'dni'           => $marcacion->user?->dni,
            'sede'          => $marcacion->gym?->name ?? '—',
            'turno'         => $marcacion->turno_legible,
            'metodo'        => $marcacion->method_legible,
            'entrada'       => $marcacion->clocked_in_at->format('d/m/Y H:i'),
            'salida'        => $marcacion->clocked_out_at?->format('d/m/Y H:i'),
            'duracion'      => $marcacion->clocked_out_at
                ? $marcacion->clocked_in_at->diffInMinutes($marcacion->clocked_out_at) . ' min'
                : null,
            'lat'           => $marcacion->location_lat,
            'lng'           => $marcacion->location_lng,
            'tiene_ubicacion' => $marcacion->location_lat !== null && $marcacion->location_lng !== null,
        ]);
    }
}
```

---

#### 2.3.2 `routes/admin.php`

**Agregar después de la ruta de calendario (línea ~79):**

```php
Route::get('asistencia/lista', [AttendanceController::class, 'lista'])
    ->name('asistencia.lista')->middleware('permiso:asistencia.ver');

Route::get('asistencia/{marcacion}/detalle', [AttendanceController::class, 'detalle'])
    ->name('asistencia.detalle')->middleware('permiso:asistencia.ver')
    ->whereNumber('marcacion');
```

Las rutas usan el **mismo permiso** `asistencia.ver` que el calendario.

---

#### 2.3.3 `resources/views/admin/asistencia/calendario.blade.php`

**Reestructurar** para envolver el contenido actual en `<x-alterna-vista>`:

```blade
@extends('layouts.panel')

@section('titulo', 'Asistencia')
@section('subtitulo', 'Marcaciones laborales de los entrenadores — por QR, con ubicación')

@section('contenido')
    @include('admin.asistencia._pestanas')

    {{-- Toolbar de filtros — compartido por ambas vistas --}}
    <form class="panel__toolbar" method="GET">
        <select class="campo__control" name="entrenador" style="max-width:220px">
            <option value="">Todos los entrenadores</option>
            @foreach ($entrenadores as $e)
                <option value="{{ $e->id }}" @selected($entrenador === $e->id)>{{ $e->name }}</option>
            @endforeach
        </select>
        <select class="campo__control" name="metodo" style="max-width:160px">
            <option value="">Todos los métodos</option>
            <option value="manual" @selected($metodo === 'manual')>Manual</option>
            <option value="qr" @selected($metodo === 'qr')>QR</option>
        </select>
        <button class="btn btn--vidrio" type="submit">Filtrar</button>
    </form>

    <x-alterna-vista clave="admin-asistencia" defecto="calendario">
        <x-slot:lista>
            @include('admin.asistencia._lista')
        </x-slot:lista>

        <x-slot:calendario>
            {{-- CONTENIDO ACTUAL DEL CALENDARIO (sin cambios) --}}
            <x-calendario ruta="admin.asistencia.calendario" :anterior="$anterior" :siguiente="$siguiente"
                          :celdas="$celdas" contador-texto="marcación" :filtros="$filtros">
                @foreach ($porDia as $fecha => $lista)
                    <div x-show="diaAbierto === '{{ $fecha }}'" x-cloak class="calendario__lista">
                        @foreach ($lista->sortByDesc('clocked_in_at') as $m)
                            <article class="calendario__rutina">
                                <div>
                                    <b class="es-fuerte" style="color:var(--hueso)">{{ $m->user?->name ?? '—' }}</b>
                                    <span class="calendario__meta">
                                        {{ $m->turno_legible }}
                                        · <span class="estado">{{ $m->method_legible }}</span>
                                        {{ $m->clocked_out_at ? '· Salió ' . $m->clocked_out_at->format('H:i') : '· En turno' }}
                                        @if ($m->location_lat) · <span class="estado" title="Marcado con ubicación">📍</span> @endif
                                        @if ($modoTodas) · <span class="estado">{{ $m->gym?->name ?? '—' }}</span> @endif
                                    </span>
                                </div>
                                <time class="calendario__hora">{{ $m->clocked_in_at->format('H:i') }}</time>
                            </article>
                        @endforeach
                    </div>
                @endforeach
            </x-calendario>
        </x-slot:calendario>
    </x-alterna-vista>

    {{-- Modal de detalle individual --}}
    @include('admin.asistencia._detalle-marcacion')
@endsection
```

**Nota:** El `<x-calendario>` maneja su propio modal de día. El `<x-alterna-vista>` simplemente muestra/oculta los dos slots. No hay conflicto entre los modales porque son independientes.

---

#### 2.3.4 `resources/views/admin/asistencia/_lista.blade.php` (NUEVO)

Vista de tabla paginada. Cada fila es clickeable para abrir el modal de detalle.

```blade
<div class="tabla-envoltorio" data-revelar>
    <table class="tabla tabla--tarjetas">
        <thead>
            <tr>
                <th>Entrenador</th>
                <th>Fecha</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Turno</th>
                <th>Método</th>
                @if ($modoTodas) <th>Sede</th> @endif
                <th>📍</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($marcaciones as $m)
                <tr class="tarjeta--interactiva" style="cursor:pointer"
                    @click="$dispatch('abrir-detalle', { url: '{{ route('admin.asistencia.detalle', $m) }}' })">
                    <td class="es-fuerte" data-etiqueta="Entrenador">{{ $m->user?->name ?? '—' }}</td>
                    <td data-etiqueta="Fecha">{{ $m->clocked_in_at->format('d/m/Y') }}</td>
                    <td data-etiqueta="Entrada">{{ $m->clocked_in_at->format('H:i') }}</td>
                    <td data-etiqueta="Salida">{{ $m->clocked_out_at?->format('H:i') ?? 'En curso' }}</td>
                    <td data-etiqueta="Turno">{{ $m->turno_legible }}</td>
                    <td data-etiqueta="Método"><span class="estado">{{ $m->method_legible }}</span></td>
                    @if ($modoTodas)
                        <td data-etiqueta="Sede" style="color:var(--ceniza)">{{ $m->gym?->name ?? '—' }}</td>
                    @endif
                    <td data-etiqueta="GPS">
                        @if ($m->location_lat)
                            <span class="estado" style="color:var(--ok)">📍</span>
                        @else
                            <span style="color:var(--humo)">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $modoTodas ? 8 : 7 }}" style="text-align:center;color:var(--humo)">
                        Sin marcaciones para este mes.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="paginacion">{{ $marcaciones->links() }}</div>
```

---

#### 2.3.5 `resources/views/admin/asistencia/_detalle-marcacion.blade.php` (NUEVO)

Modal de detalle que carga datos vía AJAX. Incluye coordenadas y mapa con OpenStreetMap (Leaflet.js CDN, sin dependencia npm).

```blade
{{-- Modal de detalle de una marcación laboral. Se abre desde la vista Lista
     o desde el calendario (futuro). Carga datos vía JSON y muestra mapa con
     Leaflet (CDN, sin npm). --}}
<div class="modal__fondo" x-data="detalleMarcacion()" x-show="abierto" x-cloak
     @keydown.escape.window="cerrar()" @abrir-detalle.window="abrir($event.detail.url)">
    <div class="tarjeta modal__caja" style="max-width:32rem" @click.outside="cerrar()">
        <div class="modal__cabecera">
            <h3 style="font-size:var(--t-lg)">Detalle de marcación</h3>
            <button class="modal__cerrar" type="button" @click="cerrar()"><x-icono nombre="cerrar" /></button>
        </div>

        <template x-if="cargando">
            <p style="color:var(--ceniza)">Cargando…</p>
        </template>

        <template x-if="!cargando && datos">
            <div style="display:grid;gap:var(--e-4)">
                {{-- Datos principales --}}
                <div class="calendario__detalle-lista">
                    <div>
                        <dt>Entrenador</dt>
                        <dd x-text="datos.entrenador"></dd>
                    </div>
                    <div>
                        <dt>Sede</dt>
                        <dd x-text="datos.sede"></dd>
                    </div>
                    <div>
                        <dt>Entrada</dt>
                        <dd x-text="datos.entrada"></dd>
                    </div>
                    <div>
                        <dt>Salida</dt>
                        <dd x-text="datos.salida || 'En curso'"></dd>
                    </div>
                    <div>
                        <dt>Duración</dt>
                        <dd x-text="datos.duracion || '—'"></dd>
                    </div>
                    <div>
                        <dt>Turno</dt>
                        <dd x-text="datos.turno"></dd>
                    </div>
                    <div>
                        <dt>Método</dt>
                        <dd x-text="datos.metodo"></dd>
                    </div>
                </div>

                {{-- Ubicación GPS --}}
                <template x-if="datos.tiene_ubicacion">
                    <div style="border-top:1px solid var(--acero);padding-top:var(--e-4)">
                        <p style="font-size:var(--t-sm);color:var(--ceniza);margin-bottom:var(--e-2)">
                            Ubicación GPS de la marcación
                        </p>
                        <div class="calendario__detalle-lista" style="margin-bottom:var(--e-3)">
                            <div>
                                <dt>Latitud</dt>
                                <dd x-text="datos.lat?.toFixed(8)"></dd>
                            </div>
                            <div>
                                <dt>Longitud</dt>
                                <dd x-text="datos.lng?.toFixed(8)"></dd>
                            </div>
                            <div>
                                <dt>Enlace</dt>
                                <dd>
                                    <a :href="'https://www.google.com/maps?q=' + datos.lat + ',' + datos.lng"
                                       target="_blank" rel="noopener"
                                       style="color:var(--brasa);text-decoration:underline">
                                        Abrir en Google Maps
                                    </a>
                                </dd>
                            </div>
                        </div>

                        {{-- Mapa Leaflet --}}
                        <div id="mapa-detalle" style="height:200px;border-radius:var(--r-md);border:1px solid var(--acero)"></div>
                    </div>
                </template>

                {{-- Sin ubicación --}}
                <template x-if="!datos.tiene_ubicacion">
                    <p style="color:var(--humo);font-size:var(--t-sm);border-top:1px solid var(--acero);padding-top:var(--e-3)">
                        Esta marcación no tiene ubicación GPS registrada.
                    </p>
                </template>
            </div>
        </template>
    </div>
</div>

{{-- Leaflet CSS + JS (CDN, solo una vez por página) --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9/dist/leaflet.js"></script>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('detalleMarcacion', () => ({
        abierto: false,
        cargando: false,
        datos: null,
        mapa: null,
        marker: null,

        async abrir(url) {
            this.abierto = true;
            this.cargando = true;
            this.datos = null;

            try {
                const res = await fetch(url);
                this.datos = await res.json();
            } catch {
                this.datos = null;
            }

            this.cargando = false;

            // Renderizar mapa después de que Alpine inserte el DOM
            this.$nextTick(() => {
                if (this.datos?.tiene_ubicacion) {
                    this.renderMapa();
                }
            });
        },

        renderMapa() {
            const container = document.getElementById('mapa-detalle');
            if (!container || !window.L) return;

            // Destruir mapa anterior si existe
            if (this.mapa) {
                this.mapa.remove();
                this.mapa = null;
            }

            this.mapa = L.map('mapa-detalle').setView([this.datos.lat, this.datos.lng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
                maxZoom: 19,
            }).addTo(this.mapa);
            this.marker = L.marker([this.datos.lat, this.datos.lng]).addTo(this.mapa)
                .bindPopup(this.datos.entrenador).openPopup();

            // Forzar recálculo de tamaño (necesario porque el contenedor estaba oculto)
            setTimeout(() => this.mapa.invalidateSize(), 100);
        },

        cerrar() {
            if (this.mapa) {
                this.mapa.remove();
                this.mapa = null;
            }
            this.abierto = false;
            this.datos = null;
        },
    }));
});
</script>
```

**Nota sobre Leaflet:** Se usa CDN (sin npm) porque es la única vista que lo necesita. Si en el futuro se requiere en más lugares, se puede agregar como dependencia npm. Leaflet + OpenStreetMap es gratuito, sin API key, y funciona sin conexión a internet una vez cargado.

---

#### 2.3.6 `resources/css/panel.css`

Solo si es necesario, agregar estilos para asegurar que el mapa Leaflet no tenga conflictos con el layout del modal. Probablemente no se necesite nada porque Leaflet trae sus propios estilos. Verificar después de implementar.

---

### 2.4 Manejo de permisos

| Permiso | Uso actual | Cambio necesario |
|---------|------------|-----------------|
| `asistencia.ver` | Calendario admin | **NINGUNO** — la nueva ruta `lista` y `detalle` usan el mismo permiso |
| `asistencia.aprobar` | Solicitudes de corrección | **NINGUNO** — no se modifica |
| `asistencia.registrar` | Entrenador marca entrada/salida | **NINGUNO** — el flujo QR no cambia |

---

### 2.5 Manejo de GPS / Geolocalización

**No hay cambios en la captura ni almacenamiento.** El flujo actual ya funciona correctamente:

1. `escaneo-qr.js:obtenerUbicacion()` captura GPS después de leer el QR
2. El POST envía `{ token, turno, lat, lng }`
3. `AsistenciaService::marcarStaff()` almacena `location_lat` y `location_lng`
4. Las coordenadas son `decimal(10,8)` y `decimal(11,8)` — precisión de ~1mm

**El único cambio es la REPRESENTACIÓN VISUAL** en el admin:
- Vista Lista: columna 📍 con indicador de presencia
- Modal de detalle: coordenadas exactas + enlace a Google Maps + mapa Leaflet embebido

**Si no hay GPS (usuario rechazó):**
- `location_lat = NULL`, `location_lng = NULL`
- Vista Lista muestra "—" en columna 📍
- Modal de detalle muestra "Esta marcación no tiene ubicación GPS registrada"

---

### 2.6 Manejo de errores y edge cases

| Caso | Comportamiento |
|------|----------------|
| Marcación sin GPS | Coordenadas NULL, se muestra "sin ubicación" |
| Marcación con GPS | Coordenadas en tabla, detalle y mapa |
| Leaflet no carga (sin internet) | El mapa no se muestra, las coordenadas y el enlace a Google Maps siguen visibles |
| Filtro sin resultados | Tabla muestra "Sin marcaciones para este mes" |
| Multi-gym (modoTodas) | Columna "Sede" visible tanto en Lista como en Calendario |
| Modal de detalle con GPS en cero (0,0) | Se muestra igualmente (caso improbable con `enableHighAccuracy`) |

---

### 2.7 Reutilización de componentes existentes

| Componente existente | Se reutiliza | Para qué |
|---------------------|-------------|----------|
| `<x-alterna-vista>` | **SÍ** | Alternar Lista/Calendario en el admin |
| `<x-calendario>` | **SÍ** (sin cambios) | Cuadrícula mensual existente |
| `<x-icono>` | **SÍ** | Iconos en la tabla y el modal |
| CSS `.calendario__detalle-lista` | **SÍ** | Lista clave-valor en el modal de detalle |
| CSS `.tabla--tarjetas` | **SÍ** | Tabla con diseño de tarjetas |
| CSS `.tarjeta--interactiva` | **SÍ** | Filas clickeables |
| CSS `.paginacion` | **SÍ** | Links de paginación |
| CSS `.modal__fondo/modal__caja` | **SÍ** | Modal existente |

---

### 2.8 Orden de implementación

1. **Controlador** — Agregar `lista()` y `detalle()` a `Admin\AttendanceController`
2. **Rutas** — Agregar las dos rutas nuevas en `routes/admin.php`
3. **Vista `_lista.blade.php`** — Crear la tabla paginada
4. **Vista `_detalle-marcacion.blade.php`** — Crear el modal con Leaflet
5. **Modificar `calendario.blade.php`** — Envolver en `<x-alterna-vista>`
6. **Probar** — Verificar ambas vistas, filtros, paginación, detalle con y sin GPS
7. **CSS** — Ajustar estilos si es necesario (probablemente no)

---

### 2.9 Lo que NO se toca

| Archivo | Razón |
|---------|-------|
| `resources/js/escaneo-qr.js` | La captura GPS funciona correctamente |
| `resources/views/entrenador/asistencia/_escaneo-qr.blade.php` | No se modifica el flujo QR |
| `app/Services/AsistenciaService.php` | La lógica de negocio no cambia |
| `app/Models/StaffAttendance.php` | Ya tiene `location_lat`/`location_lng` |
| `app/Models/Attendance.php` | No aplica (es para socios, no tiene GPS) |
| `database/migrations/*` | No hay nuevas columnas necesarias |
| `resources/views/components/alterna-vista.blade.php` | Se reutiliza tal cual |
| `resources/views/components/calendario.blade.php` | Se reutiliza tal cual |

---

### 2.10 Pruebas necesarias

1. **Vista Lista** — Verificar que muestra todas las marcaciones del mes, con filtros funcionando
2. **Paginación** — Verificar que funciona con filtros aplicados (parámetros en query string)
3. **Modal de detalle** — Verificar que carga datos vía AJAX y muestra todos los campos
4. **Mapa** — Verificar que Leaflet renderiza correctamente cuando hay GPS
5. **Sin GPS** — Verificar que el modal muestra el mensaje apropiado
6. **Alternancia** — Verificar que la preferencia se persiste en `localStorage`
7. **Multi-gym** — Verificar que la columna Sede aparece/desaparece según el modo
8. **Permisos** — Verificar que `asistencia.ver` permite acceso a las nuevas rutas
9. **Responsive** — Verificar que la tabla y el modal funcionan en móvil

---

### 2.11 Impacto y riesgo

| Aspecto | Nivel | Detalle |
|---------|-------|---------|
| Riesgo de datos | **Muy bajo** | Solo se lee, no se escribe nada nuevo |
| Riesgo de regresión | **Muy bajo** | El calendario existente no cambia lógica, solo se envuelve |
| Riesgo de performance | **Bajo** | La paginación controla el volumen; Leaflet es CDN |
| Dependencias nuevas | **Leaflet.js** (CDN) | Sin npm, sin build, solo 2 líneas de `<link>` + `<script>` |
| Rutas nuevas | **2** | `lista` y `detalle` — no afectan rutas existentes |
| Archivos nuevos | **2** | `_lista.blade.php` y `_detalle-marcacion.blade.php` |
| Archivos modificados | **2** | `AttendanceController.php` y `calendario.blade.php` |
