<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ventas — {{ ucfirst($tipo) }}</title>
    <style>
        /* Estilos inline: dompdf no carga hojas externas, así que este
           documento no puede tirar de tokens.css. Los colores están fijos
           a mano, tomados 1:1 de --grafito/--acero/--humo del sistema de
           diseño para que el PDF no desentone con el panel. */
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
    <p class="meta">{{ $gym->name ?? 'Sparta Gym' }} · {{ $desde }} al {{ $hasta }} · {{ $ventas->count() }} registros</p>

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
                        <td>{{ $venta->metodo_legible }}</td>
                        <td class="total">S/ {{ number_format($venta->total, 2) }}</td>
                        <td>{{ $venta->soldBy?->name ?? '—' }}</td>
                        <td>{{ ucfirst($venta->status) }}</td>
                    @else
                        <td>{{ $venta->member?->full_name ?? '—' }}</td>
                        <td>{{ $venta->concept }}</td>
                        <td class="total">S/ {{ number_format($venta->total, 2) }}</td>
                        <td>{{ $venta->metodo_legible }}</td>
                        <td>{{ $venta->soldBy?->name ?? '—' }}</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;color:#999">Sin ventas en este rango.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                {{-- Ambos tipos tienen 4 columnas antes de "Total": el plan
                     original traía un 5 aquí que desalineaba el pie en
                     membresías. --}}
                <td colspan="4" style="text-align:right;font-weight:bold">Total:</td>
                <td class="total">S/ {{ number_format($ventas->sum('total'), 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
