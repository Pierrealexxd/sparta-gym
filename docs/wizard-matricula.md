# Wizard de matrícula — spec de implementación

Guía completa para que otra persona del equipo implemente esto sin
supervisión directa. Sigue el orden tal cual: cada paso deja el proyecto en
un estado que compila y no rompe nada de lo existente.

## Qué es esto y por qué

Hoy, matricular a alguien nuevo son tres pantallas separadas: "Nuevo socio"
→ guardar → abrir la ficha → pestaña "Membresías" → llenar otro formulario.
Recepción hace esto decenas de veces al día.

El wizard junta las tres cosas (datos del socio, plan, pago) en un solo
flujo de 3 pasos, en una sola pantalla, con un resumen antes de confirmar.
Por dentro sigue creando exactamente los mismos registros
(`Member` → `Membership` → `Payment`) que el flujo actual — no cambia el
modelo de datos ni las reglas de negocio, sólo la experiencia de captura.

## Decisión de diseño: no tocar los controladores existentes

`MemberController`, `MembershipController` y `PaymentController` **no se
modifican**. El wizard vive en un controlador nuevo
(`MatriculaController`) que reutiliza la misma lógica de creación
(duplicada a propósito, ver más abajo). Esto es deliberado: así, si algo
sale mal en el wizard, el flujo clásico (botón "Nuevo socio" en cada
pantalla) sigue intacto como red de seguridad, y el diff completo es
"archivos nuevos + 3 líneas en 2 archivos existentes".

## Orden de ejecución

1. Crear rama desde la última versión de `main`.
2. Mover `generarCodigo()` de `MemberController` a `Member` (paso 1 abajo)
   — es el único cambio en un archivo existente antes del controlador
   nuevo, y es un refactor puro (mover método, no cambiar comportamiento).
   Correr `php artisan test` después: no debería romper nada porque el
   comportamiento es idéntico.
3. Crear `MatriculaController` (paso 2).
4. Añadir las 3 rutas en `routes/admin.php` (paso 3).
5. Crear la vista `admin/matricula/create.blade.php` (paso 4).
6. Añadir las clases CSS nuevas al final de `panel.css` (paso 5).
7. Añadir el botón de acceso en `socios/index.blade.php` y en el menú
   lateral (paso 6) — los únicos dos archivos existentes que cambian de
   verdad.
8. Probar manualmente (checklist al final).
9. `npm run build && php artisan view:clear && php artisan view:cache` —
   ambos deben terminar sin error antes de abrir el PR.

---

## Paso 1 — Mover `generarCodigo()` a `Member`

Hoy vive como método privado en `MemberController`. El wizard también
necesita generar el código `SP-XXXX` al crear un socio nuevo, y duplicar
ese método en dos controladores es exactamente el tipo de cosa que se
desincroniza con el tiempo.

En `app/Models/Member.php`, añadir:

```php
use App\Support\GymContext;

/** SP-XXXX correlativo y legible en recepción, sin colisiones dentro del gimnasio. */
public static function generarCodigo(): string
{
    do {
        $codigo = 'SP-' . random_int(1000, 9999);
    } while (static::where('gym_id', GymContext::id())->where('code', $codigo)->exists());

    return $codigo;
}
```

En `app/Http/Controllers/Admin/MemberController.php`:

- Borrar el método privado `generarCodigo()`.
- En `store()`, cambiar `$datos['code'] = $this->generarCodigo();` por
  `$datos['code'] = Member::generarCodigo();`.
- Quitar el `use App\Support\GymContext;` si ya no se usa en ese archivo
  (revisar con `grep -n GymContext MemberController.php`).

Correr `php artisan test` antes de seguir. Si algo falla aquí, parar y no
avanzar al paso 2 — significa que el refactor no fue realmente neutro.

---

## Paso 2 — `app/Http/Controllers/Admin/MatriculaController.php` (nuevo)

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Matrícula guiada: socio + plan + pago en un solo trámite. Junta lo que
 * hoy son tres pantallas (MemberController::create, ficha del socio,
 * MembershipController::store) sin tocar ninguna de ellas — ver
 * docs/wizard-matricula.md para el porqué.
 */
class MatriculaController extends Controller
{
    public function create(): View
    {
        return view('admin.matricula.create', [
            'planes' => Plan::activos()->orderBy('price')->get(),
        ]);
    }

    /** Autocompletar del paso 1 ("socio existente"). Devuelve como mucho 8. */
    public function buscarSocio(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q'));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $socios = Member::buscar($q)->with('currentMembership')->take(8)->get();

        return response()->json($socios->map(fn (Member $m) => [
            'id'                => $m->id,
            'full_name'         => $m->full_name,
            'code'              => $m->code,
            'document'          => $m->document,
            'tiene_vigente'     => (bool) $m->currentMembership?->esta_vigente,
            'vence'             => $m->currentMembership?->ends_at?->translatedFormat('d M Y'),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $esNuevo = $request->boolean('socio_nuevo');

        $reglas = [
            'plan_id'        => ['required', 'exists:plans,id'],
            'starts_at'      => ['required', 'date'],
            'discount'       => ['nullable', 'numeric', 'min:0'],
            'method'         => ['required', 'in:efectivo,transferencia,yape,plin,tarjeta,otro'],
            'reference'      => ['nullable', 'string', 'max:120'],
            'registrar_pago' => ['nullable', 'boolean'],
        ];

        if ($esNuevo) {
            $reglas += [
                'first_name' => ['required', 'string', 'max:80'],
                'last_name'  => ['required', 'string', 'max:120'],
                'document'   => ['nullable', 'string', 'max:20'],
                'phone'      => ['nullable', 'string', 'max:40'],
                'email'      => ['nullable', 'email', 'max:180'],
            ];
        } else {
            $reglas += ['socio_id' => ['required', 'exists:members,id']];
        }

        $datos = $request->validate($reglas);
        $plan  = Plan::findOrFail($datos['plan_id']);

        $socio = DB::transaction(function () use ($request, $datos, $plan, $esNuevo) {
            $socio = $esNuevo
                ? Member::create([
                    'first_name' => $datos['first_name'],
                    'last_name'  => $datos['last_name'],
                    'document'   => $datos['document'] ?? null,
                    'phone'      => $datos['phone'] ?? null,
                    'email'      => $datos['email'] ?? null,
                    'status'     => 'activo',
                    'code'       => Member::generarCodigo(),
                ])
                : Member::findOrFail($datos['socio_id']);

            $anterior = $socio->currentMembership;

            $membresia = $socio->memberships()->create([
                'plan_id'      => $plan->id,
                'created_by'   => $request->user()->id,
                'renewed_from' => $anterior?->id,
                'plan_name'    => $plan->name,
                'price'        => $plan->price,
                'discount'     => $datos['discount'] ?? 0,
                'starts_at'    => $datos['starts_at'],
                'ends_at'      => Carbon::parse($datos['starts_at'])->addDays($plan->duration_days),
                'status'       => 'activa',
            ]);

            $anterior?->update(['status' => 'vencida']);

            if ($request->boolean('registrar_pago', true)) {
                Payment::create([
                    'member_id'     => $socio->id,
                    'membership_id' => $membresia->id,
                    'registered_by' => $request->user()->id,
                    'concept'       => "Membresía {$plan->name}",
                    'amount'        => $plan->price - ($datos['discount'] ?? 0),
                    'method'        => $datos['method'],
                    'reference'     => $datos['reference'] ?? null,
                    'status'        => 'pagado',
                    'paid_at'       => now(),
                ]);
            }

            if ($socio->status !== 'activo') {
                $socio->update(['status' => 'activo']);
            }

            return $socio;
        });

        return redirect()
            ->route('admin.socios.show', $socio)
            ->with('exito', "Matrícula registrada. Código {$socio->code}.");
    }
}
```

**Nota sobre `Member::buscar()`** — ya existe como scope (se usa en
`admin.socios.index`); no hace falta crearlo. Confirmar el nombre exacto
con `grep -n "scopeBuscar" app/Models/Member.php` antes de usarlo — si el
nombre real difiere, ajustar la llamada.

---

## Paso 3 — Rutas (`routes/admin.php`)

Añadir el `use` junto a los demás controladores:

```php
use App\Http\Controllers\Admin\MatriculaController;
```

Y dentro del grupo `Route::prefix('panel')->name('admin.')->middleware('rol:admin,recepcion')->group(...)`,
antes o después del bloque de `socios` (no importa el orden exacto):

```php
Route::get('matricula', [MatriculaController::class, 'create'])
    ->name('matricula.create')->middleware('permiso:socios.crear');
Route::post('matricula', [MatriculaController::class, 'store'])
    ->name('matricula.store')->middleware('permiso:socios.crear');
Route::get('matricula/buscar-socio', [MatriculaController::class, 'buscarSocio'])
    ->name('matricula.buscar-socio')->middleware('permiso:socios.crear');
```

Reutiliza el permiso `socios.crear` que ya existe en el seeder — no hace
falta tocar `RolePermissionSeeder`.

---

## Paso 4 — Vista `resources/views/admin/matricula/create.blade.php` (nueva)

Estructura: un `x-data` de Alpine con `paso` (1–3), `modo`
(`'nuevo'`/`'existente'`), y los campos del formulario. Los tres pasos
viven en el mismo `<form>` — Alpine sólo muestra/oculta con `x-show`, el
`submit` real ocurre una sola vez en el paso 3. Esto evita tener que
persistir estado entre requests.

```blade
@extends('layouts.panel')

@section('titulo', 'Nueva matrícula')

@section('contenido')
<div class="tarjeta wizard" style="padding:var(--e-6)"
     x-data="matricula()" x-init="init()">

    {{-- Cabecera de pasos --}}
    <nav class="wizard__pasos" aria-label="Progreso de matrícula">
        <button type="button" class="wizard__paso" :class="{ 'is-activo': paso === 1, 'is-hecho': paso > 1 }" @click="irA(1)">
            <span>1</span> Socio
        </button>
        <button type="button" class="wizard__paso" :class="{ 'is-activo': paso === 2, 'is-hecho': paso > 2 }" @click="irA(2)">
            <span>2</span> Plan
        </button>
        <button type="button" class="wizard__paso" :class="{ 'is-activo': paso === 3 }" @click="irA(3)">
            <span>3</span> Pago y confirmación
        </button>
    </nav>

    <form method="POST" action="{{ route('admin.matricula.store') }}" @submit="enviando = true">
        @csrf
        <input type="hidden" name="socio_nuevo" :value="modo === 'nuevo' ? 1 : 0">
        <input type="hidden" name="socio_id" :value="socioExistente?.id ?? ''">

        {{-- ---------- PASO 1: SOCIO ---------- --}}
        <div x-show="paso === 1" x-cloak class="formulario-panel">
            <div style="display:flex;gap:var(--e-3);margin-bottom:var(--e-4)">
                <button type="button" class="btn" :class="modo === 'existente' ? 'btn--fuego' : 'btn--vidrio'" @click="modo = 'existente'">Socio existente</button>
                <button type="button" class="btn" :class="modo === 'nuevo' ? 'btn--fuego' : 'btn--vidrio'" @click="modo = 'nuevo'">Socio nuevo</button>
            </div>

            {{-- Buscador: sólo visible en modo "existente" --}}
            <div x-show="modo === 'existente'" x-cloak style="position:relative">
                <label class="campo">
                    <span class="campo__etiqueta">Buscar por nombre, código o documento</span>
                    <input class="campo__control" type="text" x-model="busqueda" @input.debounce.300ms="buscar()" autocomplete="off">
                </label>

                <div class="tabla-envoltorio" x-show="resultados.length" style="margin-top:var(--e-3)">
                    <table class="tabla">
                        <tbody>
                            <template x-for="r in resultados" :key="r.id">
                                <tr style="cursor:pointer" @click="elegirSocio(r)">
                                    <td class="es-fuerte" x-text="r.full_name"></td>
                                    <td style="font-family:var(--f-mono)" x-text="r.code"></td>
                                    <td x-text="r.document ?? '—'"></td>
                                    <td x-show="r.tiene_vigente" style="color:#FF8A6B">Ya tiene membresía vigente (vence <span x-text="r.vence"></span>)</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="aviso" style="margin-top:var(--e-4)" x-show="socioExistente" x-cloak>
                    Seleccionado: <b x-text="socioExistente?.full_name"></b> (<span x-text="socioExistente?.code"></span>)
                    <button type="button" class="btn btn--desnudo" @click="socioExistente = null" style="margin-left:var(--e-3)">Cambiar</button>
                </div>
            </div>

            {{-- Alta rápida: sólo visible en modo "nuevo" --}}
            <div x-show="modo === 'nuevo'" x-cloak class="formulario-panel__fila">
                <label class="campo"><span class="campo__etiqueta">Nombres</span>
                    <input class="campo__control" type="text" name="first_name" x-model="nuevo.first_name"></label>
                <label class="campo"><span class="campo__etiqueta">Apellidos</span>
                    <input class="campo__control" type="text" name="last_name" x-model="nuevo.last_name"></label>
                <label class="campo"><span class="campo__etiqueta">Documento</span>
                    <input class="campo__control" type="text" name="document" x-model="nuevo.document"></label>
                <label class="campo"><span class="campo__etiqueta">Teléfono</span>
                    <input class="campo__control" type="text" name="phone" x-model="nuevo.phone"></label>
                <label class="campo"><span class="campo__etiqueta">Correo</span>
                    <input class="campo__control" type="email" name="email" x-model="nuevo.email"></label>
            </div>

            <div class="formulario-panel__acciones">
                <button type="button" class="btn btn--fuego" @click="siguiente()" :disabled="!puedeAvanzarPaso1()">Siguiente</button>
            </div>
        </div>

        {{-- ---------- PASO 2: PLAN ---------- --}}
        <div x-show="paso === 2" x-cloak class="formulario-panel">
            <div class="formulario-panel__fila">
                @foreach ($planes as $plan)
                    <label class="tarjeta tarjeta--interactiva" style="padding:var(--e-4);cursor:pointer"
                           :style="planId == {{ $plan->id }} ? 'border-color:var(--sangre-viva)' : ''">
                        <input type="radio" name="plan_id" value="{{ $plan->id }}" x-model="planId" style="display:none">
                        <b style="display:block;font-family:var(--f-display);font-size:var(--t-lg)">{{ $plan->name }}</b>
                        <span style="color:var(--bronce);font-family:var(--f-mono)">S/ {{ number_format($plan->price, 0) }}</span>
                        <span style="display:block;color:var(--humo);font-size:var(--t-sm)">{{ $plan->duracion_legible }}</span>
                    </label>
                @endforeach
            </div>

            <div class="formulario-panel__fila">
                <label class="campo"><span class="campo__etiqueta">Inicio</span>
                    <input class="campo__control" type="date" name="starts_at" x-model="startsAt"></label>
                <label class="campo"><span class="campo__etiqueta">Descuento (S/)</span>
                    <input class="campo__control" type="number" step="0.01" name="discount" x-model="discount"></label>
            </div>

            <div class="formulario-panel__acciones">
                <button type="button" class="btn btn--vidrio" @click="paso = 1">Atrás</button>
                <button type="button" class="btn btn--fuego" @click="siguiente()" :disabled="!planId">Siguiente</button>
            </div>
        </div>

        {{-- ---------- PASO 3: PAGO Y CONFIRMACIÓN ---------- --}}
        <div x-show="paso === 3" x-cloak class="formulario-panel">
            <label class="campo"><span class="campo__etiqueta">
                <input type="checkbox" name="registrar_pago" value="1" x-model="registrarPago"> Registrar pago ahora
            </span></label>

            <div x-show="registrarPago" x-cloak class="formulario-panel__fila">
                <label class="campo"><span class="campo__etiqueta">Método de pago</span>
                    <select class="campo__control" name="method" x-model="method">
                        @foreach (config('sparta.metodos_pago') as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                    </select></label>
                <label class="campo"><span class="campo__etiqueta">Referencia</span>
                    <input class="campo__control" type="text" name="reference" x-model="reference"></label>
            </div>

            {{-- Resumen final: nada de sorpresas al confirmar --}}
            <div class="tarjeta" style="padding:var(--e-5);background:var(--metal)">
                <div class="ficha__dato"><span>Socio</span><span x-text="modo === 'nuevo' ? (nuevo.first_name + ' ' + nuevo.last_name) : socioExistente?.full_name"></span></div>
                <div class="ficha__dato"><span>Plan</span><span x-text="nombrePlan()"></span></div>
                <div class="ficha__dato"><span>A pagar hoy</span><span x-text="registrarPago ? ('S/ ' + montoFinal()) : 'No se registra pago'"></span></div>
            </div>

            <div class="formulario-panel__acciones">
                <button type="button" class="btn btn--vidrio" @click="paso = 2">Atrás</button>
                <button class="btn btn--fuego btn--bloque" type="submit" :disabled="enviando">
                    <span x-show="!enviando">Confirmar matrícula</span>
                    <span x-show="enviando" x-cloak>Guardando…</span>
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function matricula() {
    return {
        paso: 1, modo: 'existente', enviando: false,
        busqueda: '', resultados: [], socioExistente: null,
        nuevo: { first_name: '', last_name: '', document: '', phone: '', email: '' },
        planId: null, startsAt: new Date().toISOString().slice(0, 10), discount: 0,
        registrarPago: true, method: 'efectivo', reference: '',
        planes: @json($planes->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'price' => (float) $p->price])),

        init() {},

        async buscar() {
            if (this.busqueda.length < 2) { this.resultados = []; return; }
            const res = await fetch(`{{ route('admin.matricula.buscar-socio') }}?q=${encodeURIComponent(this.busqueda)}`);
            this.resultados = await res.json();
        },
        elegirSocio(r) { this.socioExistente = r; this.resultados = []; this.busqueda = ''; },

        puedeAvanzarPaso1() {
            return this.modo === 'existente'
                ? !!this.socioExistente
                : this.nuevo.first_name.trim() && this.nuevo.last_name.trim();
        },
        siguiente() { if (this.paso < 3) this.paso++; },
        irA(n) { if (n < this.paso || (n === 2 && this.puedeAvanzarPaso1()) || (n === 3 && this.planId)) this.paso = n; },

        nombrePlan() { return this.planes.find(p => p.id == this.planId)?.name ?? '—'; },
        montoFinal() {
            const plan = this.planes.find(p => p.id == this.planId);
            const precio = plan ? plan.price : 0;
            return (precio - (Number(this.discount) || 0)).toFixed(2);
        },
    };
}
</script>
@endpush
```

> **`@push('scripts')` / `@stack('scripts')`** — confirmar que
> `layouts/panel.blade.php` tiene un `@stack('scripts')` antes de
> `</body>`. Si no lo tiene, añadirlo (una línea) o, más simple, mover el
> `<script>` a un `<script>` normal al final del bloque `@section('contenido')`
> — Blade lo compila igual, solo es cuestión de gusto. Cualquiera de las
> dos formas es válida; no dupliques Alpine ni GSAP, ya se cargan en
> `app.js`.

---

## Paso 5 — CSS nuevo (`resources/css/panel.css`)

Añadir al final del archivo:

```css
/* ---------- Wizard de matrícula ---------- */
.wizard__pasos { display: flex; gap: var(--e-2); margin-bottom: var(--e-6); border-bottom: 1px solid var(--acero); padding-bottom: var(--e-4); }
.wizard__paso {
    display: flex; align-items: center; gap: var(--e-2);
    font-family: var(--f-mono); font-size: var(--t-xs); letter-spacing: .06em; text-transform: uppercase;
    color: var(--humo);
}
.wizard__paso span {
    width: 24px; height: 24px; display: grid; place-items: center;
    border-radius: 50%; border: 1px solid var(--acero-claro); font-size: .68rem;
}
.wizard__paso.is-activo { color: var(--hueso); }
.wizard__paso.is-activo span { background: var(--fuego); border-color: transparent; color: #fff; }
.wizard__paso.is-hecho { color: var(--ceniza); }
.wizard__paso.is-hecho span { background: var(--grafito-alto); border-color: var(--acero-claro); }
```

Ningún color ni tamaño literal — todo sale de `tokens.css`, como pide
`AGENTS.md`.

---

## Paso 6 — Enganchar el wizard a la navegación

**`resources/views/admin/socios/index.blade.php`** — junto al botón
"Nuevo socio" existente, añadir uno nuevo (no reemplazar el viejo: alguien
puede seguir queriendo crear un socio sin matricularlo todavía, p. ej.
alguien que sólo viene a preguntar precios):

```blade
@section('acciones')
    <div style="display:flex;gap:var(--e-3)">
        <a class="btn btn--vidrio" href="{{ route('admin.socios.create') }}">
            <x-icono nombre="agregar" /> Nuevo socio
        </a>
        <a class="btn btn--fuego" href="{{ route('admin.matricula.create') }}">
            <x-icono nombre="agregar" /> Nueva matrícula
        </a>
    </div>
@endsection
```

**`resources/views/layouts/partials/panel-nav.blade.php`** — añadir el
enlace directo bajo "Socios":

```blade
<a class="panel__enlace" href="{{ route('admin.matricula.create') }}" aria-current="{{ request()->routeIs('admin.matricula.*') ? 'true' : 'false' }}">
    <x-icono nombre="agregar" /> Nueva matrícula
</a>
```

---

## Checklist de prueba manual (antes de abrir el PR)

- [ ] `php artisan test` pasa después de mover `generarCodigo()` (paso 1).
- [ ] `/panel/matricula` carga y muestra los 3 pasos.
- [ ] Buscar un socio existente por nombre, por código y por documento —
      los 3 encuentran resultados.
- [ ] Elegir un socio con membresía vigente muestra el aviso de "ya tiene
      membresía vigente" pero **no bloquea** continuar (renovar antes de
      vencer es un caso válido).
- [ ] Flujo completo con socio **nuevo**: se crea el `Member`, la
      `Membership` y el `Payment`; termina en la ficha del socio con el
      mensaje de éxito.
- [ ] Flujo completo con socio **existente**: no duplica el socio, sí crea
      `Membership` y `Payment` nuevos, y la membresía anterior (si había)
      queda en estado `vencida`.
- [ ] Desmarcar "Registrar pago ahora" — se crea la membresía sin `Payment`.
- [ ] Validaciones: intentar avanzar de paso sin elegir socio/plan no deja
      avanzar (botón deshabilitado).
- [ ] Error del servidor (p. ej. quitar temporalmente un campo requerido)
      no rompe la página — Laravel redirige de vuelta con los errores;
      confirmar que al menos se ven listados arriba del formulario
      (Blade ya maneja esto por defecto vía `$errors`, pero como el wizard
      oculta pasos con `x-show`, un error en el paso 1 mientras se está
      viendo el paso 3 quedaría invisible — **si esto pasa en la prueba,
      añadir un bloque `@if ($errors->any())` fijo arriba del wizard, o un
      `x-init` que lea `@json($errors->keys())` y salte al paso
      correspondiente**).
- [ ] `npm run build` y `php artisan view:cache` terminan sin error.

## Fuera de alcance de este documento

No toca: el listado de socios existente, la ficha del socio (`show.blade.php`),
ni el formulario clásico de "Nuevo socio". Esos tres siguen funcionando
exactamente igual que hoy — el wizard es un camino adicional, no un
reemplazo.
