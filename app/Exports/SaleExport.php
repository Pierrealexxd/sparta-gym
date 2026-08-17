<?php

namespace App\Exports;

use App\Models\Sale;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta el mismo listado que ya se ve en /admin/ventas a .xlsx. Las
 * columnas cambian según $tipo porque "producto" y "membresía" muestran
 * datos distintos en la tabla (ver admin/ventas/index.blade.php).
 */
class SaleExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private readonly Collection $ventas,
        private readonly string $tipo,
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
                $venta->metodo_legible,
                (float) $venta->total,
                $venta->soldBy?->name ?? '—',
                ucfirst($venta->status),
            ];
        }

        return [
            $venta->number,
            $venta->sold_at->format('d/m/Y H:i'),
            $venta->member?->full_name ?? '—',
            $venta->concept,
            (float) $venta->total,
            $venta->metodo_legible,
            $venta->soldBy?->name ?? '—',
        ];
    }

    /**
     * Encabezado en negrita con fondo oscuro (--grafito del sistema de
     * diseño, en hex fijo: PhpSpreadsheet no lee variables CSS).
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'F2EFEA']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '16171A']],
            ],
        ];
    }
}
