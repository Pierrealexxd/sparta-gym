# Multi-sede: selector de gimnasio + vista agregada

> **Estado: Parte A ✅ completa e implementada.** Migración corrida,
> permisos seeded, middleware registrado y probado por tinker, CRUD de
> sedes funcionando (`npm run build` y `php artisan view:cache` limpios).
> **Partes B, C y D implementadas** (selector de sede, dashboard agregado
> con checkboxes, columna de sede en Socios/Pagos). Pendiente: verificación
> visual del dueño en navegador y A.6 (dar de alta Sparta 2/3 y adjuntar
> cuentas) — sin eso, el selector no aparece por tener sólo una sede.
> La base que necesitan (`User::sedesDisponibles()`, `GymContext` resuelto
> por sesión, permiso `sedes.ver-todas`) está lista y verificada.

Plan dividido en 4 partes para repartir entre agentes/desarrolladores.
Modelo elegido (confirmado con el dueño): **una sola cuenta** puede
cambiar entre sedes (Sparta 1 / 2 / 3) y ver todas juntas — no cuentas
separadas por sede.

## Hallazgo importante antes de empezar

`GymContext` (`app/Support/GymContext.php`) existe desde el día uno, pero
**hoy no lo fija nadie por usuario**: se resuelve una sola vez por
`config('sparta.gym_slug')`, siempre el mismo gimnasio, sin importar quién
inició sesión. El aislamiento de datos (`BelongsToGym`) ya funciona a
nivel de consulta — lo que falta es la capa de arriba: quién decide *qué*
gimnasio es "el activo" en cada request. Esa es exactamente la Parte A.

## Orden de ejecución (no negociable — cada parte depende de la anterior)

1. **Parte A** primero, completa, probada. Toca aislamiento de datos —
   si se hace mal, un recepcionista podría llegar a ver socios de otra
   sede. No se paraleliza con las demás.
2. **Partes B, C y D** pueden repartirse entre dos personas *después* de
   que A esté mergeado, porque todas dependen de que `GymContext` ya
   resuelva bien por sesión.
3. Antes de tocar nada: `php artisan migrate` debe estar al día y
   `npm run build` / `php artisan view:cache` deben pasar limpio como
   punto de partida (así se sabe que cualquier fallo posterior es del
   cambio nuevo, no de algo preexistente).

---

## Parte A — Fundamento de datos y seguridad

*(Recomendado: que la haga la persona/agente con más contexto del
proyecto — es la única parte que, si sale mal, compromete el aislamiento
entre gimnasios.)*

### A.1 — Migración: tabla puente `gym_user`

Hoy `users.gym_id` es una sola sede fija por persona (columna FK
`nullable` no, `not null`). La dejamos tal cual —es la "sede de origen"
de cada trabajador de planta (recepción, entrenador, cliente)— y
añadimos una tabla puente **sólo** para las cuentas que necesitan
alternar entre varias sedes (el dueño, y quien él decida).

```php
Schema::create('gym_user', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
    $table->unique(['user_id', 'gym_id']);
});
```

`php artisan make:migration create_gym_user_table` y pegar el `up()`.

### A.2 — Relación en `User`

```php
public function gyms(): BelongsToMany
{
    return $this->belongsToMany(Gym::class)->withTimestamps();
}

/** Todas las sedes a las que puede entrar: la de origen + las de la tabla puente. */
public function sedesDisponibles(): \Illuminate\Support\Collection
{
    return $this->gyms->push($this->gym)->unique('id')->sortBy('name')->values();
}
```

### A.3 — Permiso nuevo (`RolePermissionSeeder`)

En el grupo `'Sistema'` de `PERMISOS`, añadir:

```php
'sedes.ver-todas' => 'Ver el panel con todas las sedes a la vez',
'sedes.gestionar' => 'Crear y editar sedes',
```

No hace falta asignarlo a `recepcion`/`entrenador`/`cliente` en
`ASIGNACIONES` — el administrador ya lo tiene todo por definición (línea
61 de `User::tienePermiso()`). Si más adelante quieres que un gerente de
sede específico también pueda alternar entre un par de sedes sin ser
admin, ahí sí se le asigna el permiso a su rol.

Correr `php artisan db:seed --class=RolePermissionSeeder` (es idempotente).

### A.4 — Middleware `EstablecerSedeActiva`

```php
<?php

namespace App\Http\Middleware;

use App\Support\GymContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija el gimnasio activo de la petición según lo que el usuario tenga
 * guardado en sesión, validando que de verdad tenga acceso a esa sede.
 * Corre ANTES de 'rol' para que BelongsToGym ya filtre correctamente en
 * cualquier consulta que el controlador haga.
 */
class EstablecerSedeActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $seleccion = $request->session()->get('sede_activa_id');

        // "todas" sólo es válido si el usuario tiene el permiso — si no,
        // se ignora silenciosamente y cae a su sede de origen.
        if ($seleccion === 'todas' && $user->tienePermiso('sedes.ver-todas')) {
            GymContext::set(null);
            return $next($request);
        }

        $sedes = $user->sedesDisponibles();
        $sede  = $sedes->firstWhere('id', $seleccion) ?? $user->gym;

        GymContext::set($sede);

        return $next($request);
    }
}
```

Registrar en `bootstrap/app.php`, junto a `rol` y `permiso`, y añadirlo al
`middleware('auth')` de `routes/web.php` (el grupo que envuelve `admin.php`,
`entrenador.php`, `cliente.php`) — **antes** de que se resuelva cualquier
ruta de esos tres archivos:

```php
Route::middleware(['auth', 'sede.activa'])->group(function () {
    require __DIR__ . '/admin.php';
    require __DIR__ . '/entrenador.php';
    require __DIR__ . '/cliente.php';
});
```

(el alias `'sede.activa' => EstablecerSedeActiva::class` va en el mismo
array de `bootstrap/app.php` donde están `'rol'` y `'permiso'`.)

### A.5 — CRUD mínimo de sedes

Hoy no existe ninguna pantalla para crear un gimnasio nuevo — `Gym` no
tiene controlador. Sin esto, Sparta 2 y 3 sólo podrían crearse por
`tinker`, lo cual no escala si el dueño abre una cuarta sede el año que
viene.

- `php artisan make:controller Admin/GymController --resource`
- Ruta: `Route::resource('sedes', GymController::class)->except(['show'])`
  dentro de `routes/admin.php`, con `->middleware('permiso:sedes.gestionar')`
  en el grupo.
- Campos mínimos del formulario: `name`, `slug`, `address`, `phone`,
  `is_active`. Reusar exactamente el patrón de `PlanController` /
  `admin/planes/form.blade.php` — es el CRUD más simple que ya existe en
  el proyecto, cópialo como plantilla.
- Vista `admin/sedes/index.blade.php` y `form.blade.php`, mismo patrón que
  `admin/planes/*`.

### A.6 — Dar de alta las sedes existentes (una sola vez, manual)

Esto **no va en un seeder** — son datos reales de producción, no datos de
demo. Una vez que A.5 esté listo, el dueño mismo crea "Sparta 2" y
"Sparta 3" desde `/panel/sedes` con su cuenta de administrador, y luego
se adjunta a sí mismo (y a quien más necesite el selector) a las 3 sedes:

```php
// php artisan tinker
$owner = \App\Models\User::where('email', 'admin@spartagym.pe')->first();
$owner->gyms()->sync(\App\Models\Gym::pluck('id'));
```

### Checklist Parte A

- [x] Migración corrida, `gym_user` existe
- [x] `User::gyms()` / `sedesDisponibles()` probados por tinker: el admin
      ve su sede de origen, y al adjuntarle una sede nueva por
      `$user->gyms()->syncWithoutDetaching([...])` aparece en la lista
- [x] Permisos `sedes.ver-todas` y `sedes.gestionar` confirmados (el admin
      los tiene por definición, sin enumerarlos)
- [x] `/panel/sedes` permite crear/editar/desactivar una sede, con slug
      único generado automáticamente (evita colisión entre "Sparta Norte"
      creada dos veces)
- [x] `npm run build` y `php artisan view:cache` sin error
- [ ] Falta por hacer (dueño o quien administre): entrar a `/panel/sedes`
      y crear ahí mismo "Sparta 2" y "Sparta 3" con sus datos reales, y
      adjuntar las cuentas que deban alternar entre sedes vía
      `$user->gyms()->sync([...])` (A.6) — esto es dato real de negocio,
      no algo que un agente deba inventar por su cuenta.
- [ ] `php artisan test` sigue con la misma falla preexistente de antes
      de este trabajo (`SQLSTATE: no such table: gyms` en SQLite en
      memoria) — no relacionada con multi-sede, ver
      `docs/estado-mejoras-panel.md`.

---

## Parte B — Selector de sede en la interfaz

*(Puede hacerla otra persona en paralelo con C, una vez A esté mergeado.)*

### B.1 — Ruta para cambiar de sede

```php
// routes/web.php, dentro del middleware('auth')
Route::post('/sede-activa', function (\Illuminate\Http\Request $request) {
    $valor = $request->input('sede_id'); // id numérico o el string "todas"
    $request->session()->put('sede_activa_id', $valor === 'todas' ? 'todas' : (int) $valor);
    return back();
})->name('sede.activar');
```

(si se prefiere más ceremonia, esto puede vivir en un
`SedeActivaController@store` en vez de una closure — el proyecto ya tiene
ese patrón en todos los demás controladores, seguirlo por consistencia.)

### B.2 — El selector, en `layouts/partials/panel-nav.blade.php` o en la cabecera

Debajo de `.panel__marca`, antes del `<nav>`:

```blade
@if (auth()->user()->sedesDisponibles()->count() > 1)
    <div class="panel__sede" x-data="{ abierto: false }">
        <button type="button" class="panel__sede-boton" @click="abierto = !abierto">
            <x-icono nombre="ubicacion" />
            {{ \App\Support\GymContext::current()?->name ?? 'Todas las sedes' }}
        </button>
        <div class="panel__sede-menu" x-show="abierto" x-cloak @click.outside="abierto = false">
            @foreach (auth()->user()->sedesDisponibles() as $sede)
                <form method="POST" action="{{ route('sede.activar') }}">
                    @csrf
                    <button type="submit" name="sede_id" value="{{ $sede->id }}">{{ $sede->name }}</button>
                </form>
            @endforeach
            @if (auth()->user()->tienePermiso('sedes.ver-todas'))
                <form method="POST" action="{{ route('sede.activar') }}">
                    @csrf
                    <button type="submit" name="sede_id" value="todas">Todas las sedes</button>
                </form>
            @endif
        </div>
    </div>
@endif
```

### B.3 — CSS nuevo (`panel.css`)

Seguir el mismo lenguaje visual que `.panel__enlace` / `.modal__caja` —
nada de colores nuevos, todo `tokens.css`:

```css
.panel__sede { padding: 0 var(--e-4) var(--e-4); position: relative; }
.panel__sede-boton {
    width: 100%; display: flex; align-items: center; gap: var(--e-2);
    padding: var(--e-3); border-radius: var(--r-md);
    background: var(--grafito-alto); border: 1px solid var(--acero);
    color: var(--hueso); font-size: var(--t-sm);
}
.panel__sede-menu {
    position: absolute; top: 100%; left: var(--e-4); right: var(--e-4);
    z-index: var(--z-nav); margin-top: var(--e-2);
    background: var(--grafito-alto); border: 1px solid var(--acero-claro);
    border-radius: var(--r-md); box-shadow: var(--s-lg); overflow: hidden;
}
.panel__sede-menu form button {
    width: 100%; text-align: left; padding: var(--e-3) var(--e-4);
    font-size: var(--t-sm); color: var(--ceniza);
}
.panel__sede-menu form button:hover { background: rgba(255,255,255,.04); color: var(--hueso); }
```

### Checklist Parte B

- [ ] El selector sólo aparece si el usuario tiene más de una sede
      disponible (recepción/entrenador/cliente de una sola sede no lo ven)
- [ ] Cambiar de sede recarga la página actual con los datos de la sede
      nueva (probar en Socios, Pagos, Dashboard)
- [ ] "Todas las sedes" sólo aparece para quien tiene `sedes.ver-todas`

---

## Parte C — Dashboard agregado

*(Depende de A y B ya mergeados. Es la parte más pedida por el dueño:
"que se vaya acumulando todo de todas las sedes, o que yo elija cuáles".)*

### C.1 — Modo "todas las sedes"

En `DashboardController`, cuando `GymContext::id()` es `null` (modo
"todas"), las consultas actuales siguen funcionando igual —
`BelongsToGym` ya deja de filtrar solo con que `GymContext::id()` sea
null, no hace falta `sinFiltroDeGimnasio()` en este caso concreto. Lo que
hay que añadir es el desglose por sede:

```php
if (GymContext::id() === null) {
    $porSede = \App\Models\Gym::query()
        ->withCount(['members as socios_activos' => fn ($q) => $q->where('status', 'activo')])
        ->get()
        ->map(fn ($gym) => [
            'nombre'   => $gym->name,
            'socios'   => $gym->socios_activos,
            'ingresos' => \App\Models\Payment::sinFiltroDeGimnasio()
                ->where('gym_id', $gym->id)->cobrados()->delMes()->sum('amount'),
        ]);

    // pasar $porSede a la vista, mostrar una tarjeta por sede + fila de total
}
```

Nota: dentro de este `if`, los modelos con `BelongsToGym` **ya no están
filtrados** (porque `GymContext::id()` es null), así que técnicamter no
hace falta `sinFiltroDeGimnasio()` — está puesto arriba sólo para dejar
explícito en el código que ese query cruza sedes a propósito, tal como
pide la convención del proyecto (ver `AGENTS.md`, sección "Multi-gimnasio:
la regla que no se rompe").

### C.2 — Vista previa de sedes seleccionadas (lo que pidió el dueño)

En vez de sólo "todas" u "una", un tercer modo: elegir un subconjunto.
Más simple implementarlo como **filtro en la propia vista del dashboard**
que en el selector de sesión:

- Checkboxes con las sedes del usuario, en un contenedor plegable al pie
  del dashboard (tal como lo describió: "clic ahí abajo y me da la
  previsualización").
- Al marcar/desmarcar, un formulario `GET` recarga
  `/panel?sedes[]=1&sedes[]=3` — sin necesidad de tocar la sesión ni el
  middleware. El controlador simplemente hace
  `whereIn('gym_id', $request->array('sedes'))` sobre las consultas
  agregadas de C.1 cuando el query string trae `sedes`.
- Este modo sólo tiene sentido si `GymContext::id()` es null (modo
  "todas"); si el usuario está en una sede específica, no se muestra el
  contenedor.

### Checklist Parte C

- [ ] En modo "todas las sedes", el dashboard muestra una tarjeta por
      sede + el total combinado
- [ ] El filtro de "algunas sedes" (checkboxes) recalcula sólo con las
      marcadas, sin recargar toda la sesión
- [ ] En modo "una sede", el dashboard se ve exactamente igual que hoy
      (cero regresión para recepción/entrenador que nunca cambian de sede)

---

## Parte D — Pulido visual

*(La más liviana; buen punto de entrada para quien tenga menos contexto
del backend.)*

- Cuando el modo es "todas las sedes", añadir una columna/etiqueta de
  sede en las tablas que hoy no la tienen (Socios, Pagos) — si no, es
  imposible saber a cuál pertenece cada fila. Reusar el componente
  `.estado` ya existente (`resources/views/components/`) con el nombre de
  la sede en vez de un estado.
- Confirmar contraste y legibilidad del selector de sede en móvil (el
  panel lateral ya colapsa por debajo de 960px — el selector debe
  colapsar con él, no quedar flotando).
- Revisar que ningún color/tamaño nuevo se haya escrito literal —
  todo debe salir de `tokens.css`, como en el resto del proyecto.

---

## Fuera de alcance de este documento

- Asistencia de **trabajadores** (staff clock-in/out) — no existe hoy,
  ni esto lo agrega. Es una feature aparte si se necesita.
- Landing pública por sede (hoy la web pública sigue sirviendo un solo
  gimnasio vía `config('sparta.gym_slug')`) — no se toca.
- Permisos distintos por sede para un mismo usuario (p. ej. admin en
  Sparta 1 pero sólo recepción en Sparta 2) — el modelo actual asume el
  mismo rol en todas las sedes a las que la cuenta tiene acceso. Si se
  necesita eso, es un rediseño de permisos más grande, no cabe aquí.
