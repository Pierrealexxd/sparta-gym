# Plan: Módulo de Ventas + Clientes/Membresías

> **Proyecto:** Sparta Gym — Laravel 12 · Blade · Alpine · GSAP
> **Fecha:** 2026-08-16
> **Objetivo:** Agregar exportación PDF/Excel e importación Excel en ventas, y botón de recordatorio WhatsApp para membresías por vencer en el detalle del cliente.

---

## Índice

1. [Resumen ejecutivo](#1-resumen-ejecutivo)
2. [Estado actual](#2-estado-actual)
3. [Parte 1 — Exportar ventas (PDF + Excel)](#3-parte-1--exportar-ventas-pdf--excel)
4. [Parte 2 — Importar ventas desde Excel](#4-parte-2--importar-ventas-desde-excel)
5. [Parte 3 — Botón WhatsApp de membresía por vencer](#5-parte-3--botón-whatsapp-de-membresía-por-vencer)
6. [Parte 4 — Verificación de registro de ventas por empleados](#6-parte-4--verificación-de-registro-de-ventas-por-empleados)
7. [Dependencias a instalar](#7-dependencias-a-instalar)
8. [Archivos a crear/modificar](#8-archivos-a-crearmodificar)
9. [Criterios de aceptación](#9-criterios-de-aceptación)
10. [Orden de ejecución](#10-orden-de-ejecución)

---

## 1. Resumen ejecutivo

### Qué se pide

| # | Funcionalidad | Dónde |
|---|--------------|-------|
| 1 | Exportar ventas a PDF y Excel | Módulo de ventas (`/admin/ventas`) |
| 2 | Importar ventas desde Excel | Módulo de ventas (`/admin/ventas`) |
| 3 | Botón de WhatsApp para membresías por vencer | Detalle del cliente (`/admin/clientes/{id}`, pestaña Membresías) |
| 4 | Verificar que el registro de ventas por empleados funciona correctamente | Módulo de ventas + detalle de cliente |

### Qué NO se toca

- No se modifica la lógica de negocio existente de ventas ni membresías
- No se rompe el responsive actual
- No se cambia el esquema de BD (solo se agregan paquetes)
- No se altera el flujo de matrícula existente

---

## 2. Estado actual

### Ventas (`/admin/ventas`)

- **Vista:** `resources/views/admin/ventas/index.blade.php` (215 líneas)
- **Controlador:** `app/Http/Controllers/Admin/SaleController.php`
- **Rutas:** index, store, anular, buscar-cliente (4 rutas)
- **Funcionalidad:** KPIs, filtro por rango de fechas, pestañas Productos/Registros, tabla con paginación, modal para registrar venta de productos, anulación
- **Exportar:** No existe. No hay paquetes PDF/Excel instalados
- **Importar:** No existe

### Detalle de cliente (`/admin/clientes/{id}`)

- **Vista:** `resources/views/admin/clientes/show.blade.php` (212 líneas)
- **Controlador:** `app/Http/Controllers/Admin/MemberController.php` (253 líneas)
- **Pestañas:** Resumen, Medidas, Membresías, Pagos, Asistencia
- **Pestaña Membresías:** Formulario para registrar nueva membresía + tabla de historial
- **WhatsApp:** No hay ningún enlace de WhatsApp en esta vista
- **Días restantes:** Se muestran como `$cliente->days_left` en un badge, pero sin alerta visual de vencimiento

### Paquetes instalados

- Solo `laravel/framework` y `laravel/tinker`. **No hay dompdf, maatwebsite/excel, ni similares.**

---

## 3. Parte 1 — Exportar ventas (PDF + Excel)

### 3.1 Instalar dependencias

```bash
composer require barryvdh/laravel-dompdf
composer require maatwebsite/excel
```

- `barryvdh/laravel-dompdf` → genera PDF con diseño fiel al panel
- `maatwebsite/excel` → exporta a .xlsx con estilos y formato

### 3.2 Ruta de exportación

Agregar en `routes/admin.php`:

```php
Route::get('ventas/exportar', [SaleController::class, 'exportar'])
    ->name('ventas.exportar')->middleware('permiso:reportes.exportar');
```

El permiso `reportes.exportar` **ya existe** en el seeder (`RolePermissionSeeder.php` línea 57). No hay que crearlo.

### 3.3 Método `exportar` en `SaleController`

```php
public function exportar(Request $request): Response|StreamedResponse
{
    $tipo = $request->input('tipo', 'producto');
    $desde = $request->input('desde', now()->startOfMonth()->toDateString());
    $hasta = $request->input('hasta', now()->toDateString());

    $ventas = Sale::with(['items', 'member', 'soldBy'])
        ->where('gym_id', GymContext::current()->id)
        ->where('sale_type', $tipo)
        ->whereDate('sold_at', '>=', $desde)
        ->whereDate('sold_at', '<=', $hasta)
        ->orderBy('sold_at', 'desc')
        ->get();

    $formato = $request->input('formato', 'excel'); // 'excel' o 'pdf'

    if ($formato === 'pdf') {
        $pdf = Pdf::loadView('admin.ventas.pdf', [
            'ventas' => $ventas, 'tipo' => $tipo,
            'desde' => $desde, 'hasta' => $hasta,
            'gym' => GymContext::current(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("ventas-{$tipo}-{$desde}-{$hasta}.pdf");
    }

    return Excel::download(
        new SaleExport($ventas, $tipo),
        "ventas-{$tipo}-{$desde}-{$hasta}.xlsx"
    );
}
```

### 3.4 Clase `SaleExport`

Crear `app/Exports/SaleExport.php`:

```php
class SaleExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private Collection $ventas,
        private string $tipo,
    ) {}

    public function collection(): Collection
    {
        return $this->ventas;
    }

    public function headings(): array
    {
        return $this->tipo === 'producto'
            ? ['N°', 'Fecha', 'Productos', 'Método', 'Total (S/)', 'Vendido por', 'Estado']
            : ['N°', 'Fecha', 'Cliente', 'Concepto', 'Total (S/)', 'Método', 'Vendido por'];
    }

    public function map($venta): array
    {
        if ($this->tipo === 'producto') {
            return [
                $venta->number,
                $venta->sold_at->format('d/m/Y H:i'),
                $venta->items->map(fn ($i) => $i->quantity . '× ' . $i->product_name)->join(', '),
                config("sparta.metodos_pago.{$venta->method}", $venta->method),
                $venta->total,
                $venta->soldBy?->name ?? '—',
                ucfirst($venta->status),
            ];
        }

        return [
            $venta->number,
            $venta->sold_at->format('d/m/Y H:i'),
            $venta->member?->full_name ?? '—',
            $venta->concept,
            $venta->total,
            config("sparta.metodos_pago.{$venta->method}", $venta->method),
            $venta->soldBy?->name ?? '—',
        ];
    }

    public function styles(Sheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']]],
            1 => ['fill' => ['fillType' => 'solid', 'color' => ['rgb' => '16171A']]],
        ];
    }
}
```

### 3.5 Vista PDF

Crear `resources/views/admin/ventas/pdf.blade.php`:

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ventas — {{ ucfirst($tipo) }}</title>
    <style>
        /* Estilos inline para PDF (dompdf no soporta archivos externos) */
        body { font-family: sans-serif; font-size: 10px; color: #1a1a1a; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { color: #666; margin-bottom: 20px; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #16171A; color: #fff; padding: 8px 6px; text-align: left; font-size: 9px; text-transform: uppercase; }
        td { padding: 7px 6px; border-bottom: 1px solid #e5e5e5; }
        tr:nth-child(even) td { background: #f9f9f9; }
        .total { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Ventas de {{ ucfirst($tipo) }}</h1>
    <p class="meta">{{ $gym->name }} · {{ $desde }} al {{ $hasta }} · {{ $ventas->count() }} registros</p>

    <table>
        <thead>
            <tr>
                @if ($tipo === 'producto')
                    <th>N°</th><th>Fecha</th><th>Productos</th><th>Método</th><th>Total</th><th>Vendedor</th><th>Estado</th>
                @else
                    <th>N°</th><th>Fecha</th><th>Cliente</th><th>Concepto</th><th>Total</th><th>Método</th><th>Vendedor</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($ventas as $venta)
                <tr>
                    <td>{{ $venta->number }}</td>
                    <td>{{ $venta->sold_at->format('d/m/Y H:i') }}</td>
                    @if ($tipo === 'producto')
                        <td>{{ $venta->items->map(fn ($i) => $i->quantity . '× ' . $i->product_name)->join(', ') }}</td>
                    @else
                        <td>{{ $venta->member?->full_name ?? '—' }}</td>
                        <td>{{ $venta->concept }}</td>
                    @endif
                    <td>{{ config("sparta.metodos_pago.{$venta->method}", $venta->method) }}</td>
                    <td class="total">S/ {{ number_format($venta->total, 2) }}</td>
                    @if ($tipo === 'producto')
                        <td>{{ $venta->soldBy?->name ?? '—' }}</td>
                        <td>{{ ucfirst($venta->status) }}</td>
                    @else
                        <td>{{ $venta->soldBy?->name ?? '—' }}</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;color:#999">Sin ventas en este rango.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="{{ $tipo === 'producto' ? 4 : 5 }}" style="text-align:right;font-weight:bold">Total:</td>
                <td class="total">S/ {{ number_format($ventas->sum('total'), 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
```

### 3.6 Botones de exportación en la vista

Agregar en `resources/views/admin/ventas/index.blade.php`, **debajo del formulario de filtro** (`panel__toolbar`) y **encima de las KPIs o la tabla**:

```html
<div class="toolbar-acciones" data-revelar>
    <span class="toolbar-acciones__label">Exportar:</span>
    <a class="btn btn--vidrio btn--sm"
       href="{{ route('admin.ventas.exportar', ['tipo' => $tipo, 'desde' => $desde, 'hasta' => $hasta, 'formato' => 'excel']) }}">
        <x-icono nombre="descargar" /> Excel
    </a>
    <a class="btn btn--vidrio btn--sm"
       href="{{ route('admin.ventas.exportar', ['tipo' => $tipo, 'desde' => $desde, 'hasta' => $hasta, 'formato' => 'pdf']) }}">
        <x-icono nombre="descargar" /> PDF
    </a>
</div>
```

### 3.7 CSS para las acciones de exportación

```css
/* Agregar en panel.css */
.toolbar-acciones {
    display: flex; align-items: center; gap: var(--e-3);
    margin-bottom: var(--e-4);
}

.toolbar-acciones__label {
    font-size: var(--t-xs); text-transform: uppercase;
    letter-spacing: .06em; color: var(--humo);
}

.btn--sm {
    padding: var(--e-2) var(--e-3);
    font-size: var(--t-xs);
}

@media (max-width: 740px) {
    .toolbar-acciones {
        flex-wrap: wrap;
    }
}
```

### 3.8 Responsive

- **Desktop:** Botones en línea al lado del label "Exportar:"
- **Mobile:** Botones se envuelven debajo del label, cada uno ocupa su ancho natural
- El `btn--vidrio btn--sm` ya es consistente con los botones secundarios del panel

---

## 4. Parte 2 — Importar ventas desde Excel

### 4.1 Ruta de importación

```php
Route::post('ventas/importar', [SaleController::class, 'importar'])
    ->name('ventas.importar')->middleware('permiso:reportes.exportar');
```

### 4.2 Formato del archivo Excel a importar

El archivo debe tener estas columnas (orden importa):

| Columna | Tipo | Requerido | Ejemplo |
|---------|------|-----------|---------|
| fecha | date (d/m/Y H:i) | Sí | 15/08/2026 14:30 |
| cliente_codigo | string | No | SP-0042 |
| cliente_nombre | string | No | Juan Pérez |
| producto_nombre | string | Sí | Proteína Whey 1kg |
| cantidad | int | Sí | 2 |
| precio_unitario | decimal | Sí | 120.00 |
| metodo_pago | string | Sí | efectivo |
| descuento | decimal | No | 10.00 |
| notas | string | No | Venta por defecto |

### 4.3 Clase `SaleImport`

Crear `app/Imports/SaleImport.php`:

```php
class SaleImport implements ToCollection, WithHeadingRow, WithValidation
{
    public ?int $memberId = null;
    public int $gymId;
    public int $userId;

    public function __construct(int $gymId, int $userId)
    {
        $this->gymId = $gymId;
        $this->userId = $userId;
    }

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                // 1. Buscar o ignorar cliente
                $memberId = null;
                if (!empty($row['cliente_codigo'])) {
                    $member = Member::where('gym_id', $this->gymId)
                        ->where('code', $row['cliente_codigo'])->first();
                    $memberId = $member?->id;
                }

                // 2. Buscar producto (por nombre exacto)
                $producto = Product::where('gym_id', $this->gymId)
                    ->where('name', $row['producto_nombre'])
                    ->where('is_active', true)->first();

                if (!$producto) continue; // Saltar fila si producto no existe

                $cantidad = (int) $row['cantidad'];
                $precioUnitario = (float) $row['precio_unitario'];
                $descuento = (float) ($row['descuento'] ?? 0);
                $total = ($precioUnitario * $cantidad) - $descuento;

                // 3. Crear venta
                $venta = Sale::create([
                    'gym_id'     => $this->gymId,
                    'member_id'  => $memberId,
                    'sale_type'  => 'producto',
                    'sold_by'    => $this->userId,
                    'number'     => Sale::siguienteNumero(),
                    'subtotal'   => $precioUnitario * $cantidad,
                    'discount'   => $descuento,
                    'total'      => $total,
                    'concept'    => "Importación: {$producto->name}",
                    'method'     => $row['metodo_pago'],
                    'status'     => 'completada',
                    'notes'      => $row['notas'] ?? null,
                    'sold_at'    => Carbon::createFromFormat('d/m/Y H:i', $row['fecha']),
                ]);

                // 4. Crear items
                SaleItem::create([
                    'sale_id'      => $venta->id,
                    'product_id'   => $producto->id,
                    'product_name' => $producto->name,
                    'quantity'     => $cantidad,
                    'unit_price'   => $precioUnitario,
                    'total'        => $precioUnitario * $cantidad,
                ]);

                // 5. Mover stock
                $producto->decrement('stock', $cantidad);
                StockMovement::create([
                    'product_id'      => $producto->id,
                    'user_id'         => $this->userId,
                    'type'            => 'salida',
                    'quantity'        => $cantidad,
                    'stock_after'     => $producto->stock,
                    'reason'          => "Importación venta {$venta->number}",
                    'reference_type'  => Sale::class,
                    'reference_id'    => $venta->id,
                ]);
            }
        });
    }

    public function rules(): array
    {
        return [
            'fecha'             => 'required|date_format:d/m/Y H:i',
            'cliente_codigo'    => 'nullable|string',
            'producto_nombre'   => 'required|string',
            'cantidad'          => 'required|integer|min:1',
            'precio_unitario'   => 'required|numeric|min:0',
            'metodo_pago'       => 'required|in:' . implode(',', array_keys(config('sparta.metodos_pago'))),
            'descuento'         => 'nullable|numeric|min:0',
            'notas'             => 'nullable|string|max:500',
        ];
    }
}
```

### 4.4 Método `importar` en `SaleController`

```php
public function importar(Request $request): RedirectResponse
{
    $request->validate([
        'archivo' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
    ]);

    Excel::import(
        new SaleImport(GymContext::current()->id, $request->user()->id),
        $request->file('archivo')
    );

    return back()->with('exito', 'Ventas importadas correctamente.');
}
```

### 4.5 Botón de importación en la vista

Agregar un **botón "Importar Excel"** en la sección de acciones (`@section('acciones')`), al lado de "Registrar venta":

```blade
@section('acciones')
    @if ($tipo === 'producto' && auth()->user()->tienePermiso('ventas.registrar'))
        <button class="btn btn--fuego" type="button" x-data @click="$dispatch('abrir-modal-venta')">
            <x-icono nombre="agregar" /> Registrar venta
        </button>
    @endif

    @if (auth()->user()->tienePermiso('reportes.exportar'))
        <button class="btn btn--vidrio" type="button" x-data @click="$dispatch('abrir-modal-importar')">
            <x-icono nombre="subir" /> Importar Excel
        </button>
    @endif
@endsection
```

### 4.6 Modal de importación

Agregar al final de la vista (antes del `@endsection`), patrón `modal__fondo`:

```html
<div class="modal__fondo"
     x-data="{ abierto: false }"
     x-show="abierto" x-cloak
     @abrir-modal-importar.window="abierto = true"
     @keydown.escape.window="abierto = false">
    <div class="tarjeta modal__caja" @click.outside="abierto = false">
        <div class="modal__cabecera">
            <h3 style="font-size:var(--t-lg)">Importar ventas desde Excel</h3>
            <button class="modal__cerrar" type="button" @click="abierto = false">
                <x-icono nombre="cerrar" />
            </button>
        </div>

        <form class="formulario-panel" method="POST"
              action="{{ route('admin.ventas.importar') }}"
              enctype="multipart/form-data">
            @csrf

            <div class="aviso aviso--info">
                <p><b>Formato esperado del archivo:</b></p>
                <p style="font-size:var(--t-xs);color:var(--humo)">
                    fecha (d/m/Y H:i) · cliente_codigo (opcional) · producto_nombre · cantidad · precio_unitario · metodo_pago · descuento (opcional) · notas (opcional)
                </p>
            </div>

            <label class="campo">
                <span class="campo__etiqueta">Archivo Excel (.xlsx, .xls, .csv)</span>
                <input class="campo__control" type="file" name="archivo"
                       accept=".xlsx,.xls,.csv" required>
            </label>

            <div class="formulario-panel__acciones">
                <button class="btn btn--fuego btn--bloque" type="submit">
                    <x-icono nombre="subir" /> Importar ventas
                </button>
            </div>
        </form>
    </div>
</div>
```

### 4.7 CSS del aviso informativo

```css
/* Agregar en panel.css si no existe */
.aviso--info {
    background: rgba(45, 125, 210, .08);
    border: 1px solid rgba(45, 125, 210, .2);
    border-radius: var(--r-md);
    padding: var(--e-3) var(--e-4);
    color: var(--ceniza);
    font-size: var(--t-sm);
}
```

### 4.8 Icono `subir`

Crear `resources/svg/subir.svg` — flecha hacia arriba (para importar):

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
    <path d="M12 19V5M5 12l7-7 7 7"/>
</svg>
```

### 4.9 Responsive

- **Desktop:** Botones en la barra de acciones (misma línea que "Registrar venta")
- **Mobile:** Los botones se apilan verticalmente en la barra de acciones (el flex-wrap ya existente lo maneja)
- El modal de importación usa `modal__caja` que ya es responsive (width: min(34rem, 100%))

---

## 5. Parte 3 — Botón WhatsApp de membresía por vencer

### 5.1 Concepto

En la pestaña **Membresías** del detalle del cliente (`show.blade.php`), debajo del formulario "Registrar membresía y pago", se muestra un **botón condicional** que aparece cuando la membresía actual está por vencer (últimos 7 días o ya vencida).

Al presionarlo, se abre WhatsApp con un **mensaje prellenado** que menciona los días restantes (o que ya venció). El admin/recepcionista solo tiene que dar "Enviar" en WhatsApp.

### 5.2 Lógica de visualización

El botón aparece cuando:

```php
$membership = $cliente->currentMembership;
$showWhatsApp = $membership && $membership->diasRestantes <= config('sparta.aviso_vencimiento_dias');
// diasRestantes <= 7 (incluye negativos = vencida)
```

### 5.3 Mensajes prellenados

El mensaje cambia según los días restantes:

| Días restantes | Mensaje |
|---------------|---------|
| 7-5 días | "Hola {nombre}, tu membresía de {plan} vence en {días} días ({fecha}). ¿Deseas renovarla? 💪" |
| 4-3 días | "Hola {nombre}, te recordamos que tu membresía de {plan} vence en {días} días ({fecha}). ¡No la dejes pasar! 💪" |
| 2-1 días | "Hola {nombre}, tu membresía de {plan} vence MAÑANA ({fecha}). ¡Agenda tu renovación! 💪" |
| 0 (hoy) | "Hola {nombre}, tu membresía de {plan} vence HOY. ¡Ven a renovarla! 💪" |
| < 0 (vencida) | "Hola {nombre}, tu membresía de {plan} venció hace {abs(días)} días. ¡Te esperamos para renovar! 💪" |

### 5.4 Construcción del enlace WhatsApp

```php
// En el controlador o en la vista
$telefono = preg_replace('/\D+/', '', $cliente->phone ?? '');
$mensaje = urlencode($mensajeGenerado);
$urlWhatsApp = "https://wa.me/{$telefono}?text={$mensaje}";
```

### 5.5 Modificar la vista `show.blade.php`

En la pestaña de membresías (líneas 128-171), agregar **debajo del formulario** y **encima de la tabla**:

```html
{{-- Botón WhatsApp: aparece cuando la membresía está por vencer o vencida --}}
@php
    $membresiaActual = $cliente->currentMembership;
    $diasRestantes = $membresiaActual?->diasRestantes;
    $umbralWhatsApp = config('sparta.aviso_vencimiento_dias', 7);
    $mostrarWhatsApp = $membresiaActual && $diasRestantes !== null && $diasRestantes <= $umbralWhatsApp;
@endphp

@if ($mostrarWhatsApp && $cliente->phone)
    @php
        $nombreCliente = $cliente->first_name;
        $planNombre = $membresiaActual->plan_name;
        $fechaVencimiento = $membresiaActual->ends_at->translatedFormat('d \\d\\e F');

        $mensajeWhatsApp = match(true) {
            $diasRestantes > 4  => "Hola {$nombreCliente}, tu membresía de {$planNombre} vence en {$diasRestantes} días ({$fechaVencimiento}). ¿Deseas renovarla? 💪",
            $diasRestantes > 2  => "Hola {$nombreCliente}, te recordamos que tu membresía de {$planNombre} vence en {$diasRestantes} días ({$fechaVencimiento}). ¡No la dejes pasar! 💪",
            $diasRestantes > 0  => "Hola {$nombreCliente}, tu membresía de {$planNombre} vence MAÑANA ({$fechaVencimiento}). ¡Agenda tu renovación! 💪",
            $diasRestantes === 0 => "Hola {$nombreCliente}, tu membresía de {$planNombre} vence HOY. ¡Ven a renovarla! 💪",
            default => "Hola {$nombreCliente}, tu membresía de {$planNombre} venció hace " . abs($diasRestantes) . " días ({$fechaVencimiento}). ¡Te esperamos para renovar! 💪",
        };

        $urlWhatsApp = 'https://wa.me/' . preg_replace('/\D+/', '', $cliente->phone)
                      . '?text=' . urlencode($mensajeWhatsApp);
    @endphp

    <div class="aviso aviso--whatsapp"
         x-data="{ abierto: false }"
         x-init="$watch('abierto', v => { if (v) setTimeout(() => abierto = false, 3000) })">
        <x-icono nombre="whatsapp" />
        <div class="aviso__texto">
            @if ($diasRestantes > 0)
                <b>Faltan {{ $diasRestantes }} días</b> para que venza la membresía
            @elseif ($diasRestantes === 0)
                <b>La membresía vence hoy</b>
            @else
                <b>La membresía venció hace {{ abs($diasRestantes) }} días</b>
            @endif
        </div>
        <a class="btn btn--whatsapp" href="{{ $urlWhatsApp }}" target="_blank" rel="noopener">
            <x-icono nombre="whatsapp" /> Enviar WhatsApp
        </a>
    </div>
@endif
```

### 5.6 Icono WhatsApp

El proyecto **ya tiene** un manejo de WhatsApp en el sistema de mensajes. Se puede reusar el icono existente. Si no existe como SVG dedicado, crear `resources/svg/whatsapp.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
    <path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21"/>
    <path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1zm0 0a5 5 0 0 0 5 5m0 0a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1h1z"/>
</svg>
```

### 5.7 CSS del aviso WhatsApp

```css
/* Agregar en panel.css */
.aviso--whatsapp {
    display: flex; align-items: center; gap: var(--e-3);
    padding: var(--e-3) var(--e-4);
    border-radius: var(--r-md);
    background: rgba(37, 211, 102, .08);
    border: 1px solid rgba(37, 211, 102, .2);
    flex-wrap: wrap;
}

.aviso--whatsapp svg {
    width: 20px; height: 20px; color: #25D366; flex-shrink: 0;
}

.aviso--whatsapp .aviso__texto {
    flex: 1; font-size: var(--t-sm); color: var(--ceniza);
    min-width: 200px;
}

.aviso--whatsapp .aviso__texto b {
    color: var(--hueso);
}

.btn--whatsapp {
    display: inline-flex; align-items: center; gap: var(--e-2);
    padding: var(--e-2) var(--e-4);
    background: #25D366; color: #fff;
    border: none; border-radius: var(--r-md);
    font-size: var(--t-sm); font-weight: 500;
    text-decoration: none; white-space: nowrap;
    transition: background var(--v-medio) var(--curva);
}

.btn--whatsapp:hover {
    background: #1ebe5b;
}

.btn--whatsapp svg {
    width: 16px; height: 16px; color: #fff;
}

@media (max-width: 740px) {
    .aviso--whatsapp {
        flex-direction: column;
        align-items: flex-start;
    }
    .btn--whatsapp {
        width: 100%; justify-content: center;
    }
}
```

### 5.8 Responsive

- **Desktop:** El aviso muestra icono + texto + botón en línea
- **Mobile:** El aviso se apila: icono + texto arriba, botón debajo a ancho completo
- El `flex-wrap` y `min-width: 200px` en el texto fuerzan el apilado natural

### 5.9 Verificar el teléfono

El botón **solo aparece si `$cliente->phone` no es nulo/vacío**. Si el cliente no tiene teléfono registrado, no se muestra el botón (ya está contemplado en el `@if`).

---

## 6. Parte 4 — Verificación de registro de ventas por empleados

### 6.1 Flujo actual (verificar que funciona)

El flujo de ventas por empleados ya está implementado. Verificar estos puntos:

#### 6.1.1 — Venta de productos desde el admin

- **Ruta:** `POST /admin/ventas` → `SaleController::store`
- **Permiso:** `ventas.registrar`
- **Modal:** El formulario en `index.blade.php` (líneas 128-213) permite:
  - Buscar cliente (opcional)
  - Agregar múltiples productos con cantidad
  - Seleccionar método de pago
  - Aplicar descuento
- **Stock:** El controlador decrementa stock y crea `StockMovement`
- **Número correlativo:** `Sale::siguienteNumero()` genera V-000001

#### 6.1.2 — Venta de membresía desde el detalle del cliente

- **Ruta:** `POST /admin/clientes/{member}/membresias` → `MembershipController::store`
- **Llama a:** `MatriculaService::renovarMembresia()` → `registrarVenta()`
- **Crea:** Membership + Sale (tipo membresía) + movimiento de stock si aplica

#### 6.1.3 — Nueva matrícula (wizard 3 pasos)

- **Ruta:** `POST /admin/matricula` → `MatriculaController::store`
- **Crea:** Member + Membership + Sale (opcional) + User login (opcional)

### 6.2 Puntos a verificar (checklist)

- [ ] Al registrar una venta de producto, el stock se decrementa correctamente
- [ ] Al anular una venta, el stock se repone
- [ ] El número correlativo es único por gimnasio
- [ ] El método de pago se valida contra `config('sparta.metodos_pago')`
- [ ] El campo `sold_at` se respeta (no se sobreescribe con `created_at`)
- [ ] Al renovar membresía desde el detalle del cliente, se crea la venta automáticamente
- [ ] El `sale_type` se asigna correctamente ('producto' o 'membresia')
- [ ] El permiso `ventas.registrar` solo está en roles admin y recepción
- [ ] El entrenador solo puede ver sus propias ventas (si aplica)

### 6.3 Posibles mejoras menores (opcionales)

Si se detecta algún problema durante la verificación:

1. **Validar stock antes de registrar venta:** Agregar validación en `SaleController::store` que verifique que `quantity <= product->stock` antes de crear la venta
2. **Mostrar stock insuficiente:** Si el stock es 0, deshabilitar el producto en el select del modal
3. **Referenciar membresía en venta:** Verificar que `membership_id` se guarda correctamente al renovar

---

## 7. Dependencias a instalar

```bash
# PDF
composer require barryvdh/laravel-dompdf

# Excel
composer require maatwebsite/excel
```

Después de instalar, publicar la configuración de Excel:

```bash
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config
```

### Archivos de configuración a revisar

- `config/excel.php` — configuración de Maatwebsite Excel (export/import defaults)
- `config/dompdf.php` — configuración de dompdf (font, paper size)

---

## 8. Archivos a crear/modificar

### 8.1 Archivos NUEVOS

| Archivo | Tipo | Descripción |
|---------|------|-------------|
| `app/Exports/SaleExport.php` | Export | Clase de exportación Excel |
| `app/Imports/SaleImport.php` | Import | Clase de importación Excel |
| `resources/views/admin/ventas/pdf.blade.php` | Blade | Vista para PDF de ventas |
| `resources/svg/subir.svg` | SVG | Icono de importar/subir |
| `resources/svg/whatsapp.svg` | SVG | Icono de WhatsApp |

### 8.2 Archivos a MODIFICAR

| Archivo | Cambios |
|---------|---------|
| `composer.json` | Agregar dompdf + maatwebsite/excel |
| `routes/admin.php` | Agregar rutas exportar + importar |
| `app/Http/Controllers/Admin/SaleController.php` | Agregar métodos `exportar()` e `importar()` |
| `resources/views/admin/ventas/index.blade.php` | Agregar botones exportar + modal importar |
| `resources/views/admin/clientes/show.blade.php` | Agregar botón WhatsApp en pestaña membresías |
| `resources/css/panel.css` | Agregar estilos de `.toolbar-acciones`, `.aviso--whatsapp`, `.btn--whatsapp`, `.aviso--info` |
| `resources/svg/iconos.svg` | Agregar iconos `subir` y `whatsapp` al sprite |

---

## 9. Criterios de aceptación

### Exportar ventas

- [ ] El botón "Excel" descarga un archivo .xlsx con los datos filtrados
- [ ] El botón "PDF" descarga un PDF con diseño limpio y tabla formateada
- [ ] Los botones respetan el filtro de fechas y tipo (producto/membresía) activo
- [ ] El PDF incluye encabezado con nombre del gimnasio, rango de fechas y total
- [ ] El Excel tiene headers en negrita con fondo oscuro (estilo Sparta)
- [ ] Los botones solo aparecen para usuarios con permiso `reportes.exportar`
- [ ] Responsive: botones se envuelven en mobile

### Importar ventas

- [ ] El botón "Importar Excel" abre un modal con instrucciones del formato
- [ ] Se puede seleccionar un archivo .xlsx, .xls o .csv
- [ ] El archivo se valida (formato, columnas requeridas, métodos de pago válidos)
- [ ] Las ventas se crean con `sale_type = 'producto'`
- [ ] El stock se decrementa por cada producto importado
- [ ] Se crean los `StockMovement` correspondientes
- [ ] Si un producto no existe, la fila se salta (no falla toda la importación)
- [ ] Si un cliente no existe, la venta se crea sin cliente (no falla)
- [ ] Se muestra mensaje de éxito después de importar
- [ ] El botón solo aparece para usuarios con permiso `reportes.exportar`

### WhatsApp membresía

- [ ] El botón aparece solo cuando la membresía está por vencer (≤7 días) o vencida
- [ ] El botón aparece solo si el cliente tiene teléfono registrado
- [ ] El mensaje prellenado incluye: nombre, plan, días restantes, fecha de vencimiento
- [ ] El mensaje cambia de tono según urgencia (7d → 3d → 1d → hoy → vencida)
- [ ] Al presionar, se abre WhatsApp Web/app con el mensaje listo para enviar
- [ ] El botón se cierra automáticamente después de 3 segundos (feedback visual)
- [ ] En mobile, el botón ocupa todo el ancho
- [ ] El estilo usa colores WhatsApp (#25D366) sin romper la paleta del panel

### Verificación de ventas

- [ ] El registro de ventas por empleados funciona correctamente
- [ ] El stock se decrementa y repone al anular
- [ ] Los números correlativos son únicos
- [ ] La renovación de membresía crea la venta automáticamente
- [ ] Los permisos se respetan (solo admin/recepción pueden registrar)

### General

- [ ] No se rompe el responsive existente en ninguna vista
- [ ] No se corrompen las vistas de móvil, tablet o desktop
- [ ] No se altera la lógica de negocio existente
- [ ] `npm run build` compila sin errores
- [ ] Los formularios tienen `@csrf`

---

## 10. Orden de ejecución

```
┌─────────────────────────────────────────────────────────┐
│  PASO 0 — Instalar dependencias                         │
│  ├── composer require barryvdh/laravel-dompdf           │
│  ├── composer require maatwebsite/excel                 │
│  ├── php artisan vendor:publish --provider="..."        │
│  └── Verificar que compila                               │
├─────────────────────────────────────────────────────────┤
│  PASO 1 — Exportar ventas                               │
│  ├── Crear app/Exports/SaleExport.php                   │
│  ├── Crear resources/views/admin/ventas/pdf.blade.php   │
│  ├── Agregar ruta en routes/admin.php                   │
│  ├── Agregar método exportar() en SaleController        │
│  ├── Agregar botones en index.blade.php                 │
│  ├── Agregar CSS en panel.css                           │
│  └── Probar: descargar Excel + PDF                      │
├─────────────────────────────────────────────────────────┤
│  PASO 2 — Importar ventas                               │
│  ├── Crear app/Imports/SaleImport.php                   │
│  ├── Crear icono subir.svg                              │
│  ├── Agregar ruta en routes/admin.php                   │
│  ├── Agregar método importar() en SaleController        │
│  ├── Agregar botón + modal en index.blade.php           │
│  ├── Agregar CSS en panel.css                           │
│  └── Probar: importar archivo de prueba                 │
├─────────────────────────────────────────────────────────┤
│  PASO 3 — WhatsApp membresía por vencer                 │
│  ├── Crear icono whatsapp.svg                           │
│  ├── Modificar show.blade.php (pestaña membresías)     │
│  ├── Agregar CSS en panel.css                           │
│  └── Probar: simular membresía a 3 días, vencida, etc.  │
├─────────────────────────────────────────────────────────┤
│  PASO 4 — Verificación de ventas                        │
│  ├── Revisar SaleController::store                      │
│  ├── Revisar StockMovement creation                     │
│  ├── Revisar MembershipController::store                │
│  ├── Revisar MatriculaController::store                 │
│  └── Corregir cualquier problema encontrado             │
├─────────────────────────────────────────────────────────┤
│  PASO 5 — QA final                                     │
│  ├── npm run build                                      │
│  ├── Probar responsive: 320px → 1440px                  │
│  ├── Probar permisos: admin vs recepción vs entrenador  │
│  └── Verificar que nada existente se rompió             │
└─────────────────────────────────────────────────────────┘
```

### Tiempo estimado por paso

| Paso | Horas estimadas |
|------|----------------|
| Paso 0 — Dependencias | 0.5h |
| Paso 1 — Exportar | 2h |
| Paso 2 — Importar | 2h |
| Paso 3 — WhatsApp | 1h |
| Paso 4 — Verificación | 1h |
| Paso 5 — QA final | 0.5h |
| **Total** | **~7h** |

---

## Notas para el equipo de ejecución

1. **No inventar iconos.** El sprite `iconos.svg` ya tiene `descargar`, `agregar`, `papelera`, `lapiz`. Reutilizar `descargar` para exportar. Crear solo `subir` y `whatsapp`.
2. **El patrón de modal ya existe.** Copiar la estructura del modal de venta (líneas 128-213 de `index.blade.php`) para el modal de importación.
3. **WhatsApp es solo un enlace `wa.me`.** No hay integración con la API de WhatsApp Business. El admin copia el mensaje y lo envía manualmente.
4. **El permiso `reportes.exportar` ya existe.** No crear uno nuevo. Solo verificar que esté asignado a admin y recepción.
5. **El PDF usa dompdf.** No soporta CSS externo. Todo el estilo va inline en la vista PDF.
6. **La importación usa transacciones.** Si falla una fila, toda la importación se revierte. Considerar cambiar a importación fila por fila si se desea tolerancia a errores.
7. **El botón de WhatsApp solo funciona si el cliente tiene teléfono.** Si no tiene, simplemente no se muestra. No mostrar error.
8. **No tocar el flujo de matrícula.** El wizard de 3 pasos funciona correctamente. No agregarle exportar/importar.
