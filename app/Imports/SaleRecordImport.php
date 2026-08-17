<?php

namespace App\Imports;

use App\Models\Member;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Importa REGISTROS (ventas que no son de mostrador) desde un Excel/CSV,
 * pensado para la pestaña "Registros" de Ventas. Al contrario que
 * SaleImport, esto NUNCA toca productos ni stock: cada fila es un registro
 * genérico (sale_type = 'servicio' por defecto, 'otro' si el archivo lo
 * indica) con concepto, total, método y fecha. Es la separación de contexto
 * pedida: un archivo subido desde "Registros" solo puede crear registros.
 *
 * Seguridad: mismas reglas que SaleImport — WithValidation valida cada fila
 * ANTES de que collection() la vea, y dentro del import el cliente se
 * resuelve solo con where() parametrizado (nunca SQL concatenado).
 */
class SaleRecordImport implements ToCollection, WithHeadingRow, WithValidation
{
    /** Filas saltadas (cliente inexistente), para el mensaje de resultado. */
    public int $saltadas = 0;

    /** Registros creados con éxito. */
    public int $creadas = 0;

    public function __construct(
        private readonly ?int $gymId,
        private readonly int $userId,
    ) {}

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $codigoCliente = trim((string) ($row['cliente_codigo'] ?? ''));
                $tipo = trim((string) ($row['tipo'] ?? ''));

                $memberId = null;
                if ($codigoCliente !== '') {
                    $memberId = Member::where('code', $codigoCliente)
                        ->when($this->gymId, fn ($q) => $q->where('gym_id', $this->gymId))
                        ->value('id');

                    // Cliente inexistente: se salta la fila, nunca se crea
                    // un registro a medias ni un cliente fantasma.
                    if (! $memberId) {
                        $this->saltadas++;

                        continue;
                    }
                }

                $total = round((float) $row['total'], 2);

                Sale::create([
                    'gym_id'     => $this->gymId,
                    'member_id'  => $memberId,
                    'sale_type'  => $tipo === 'otro' ? 'otro' : 'servicio',
                    'sold_by'    => $this->userId,
                    'number'     => Sale::siguienteNumero(),
                    'subtotal'   => $total,
                    'discount'   => 0,
                    'total'      => $total,
                    'concept'    => trim((string) ($row['concepto'] ?? '')),
                    'method'     => $row['metodo_pago'],
                    'status'     => 'completada',
                    'notes'      => trim((string) ($row['notas'] ?? '')) ?: null,
                    'sold_at'    => Carbon::createFromFormat('d/m/Y H:i', trim((string) $row['fecha'])),
                ]);

                $this->creadas++;
            }
        });
    }

    public function rules(): array
    {
        return [
            'fecha'          => ['required', 'date_format:d/m/Y H:i'],
            'cliente_codigo' => ['nullable', 'string', 'max:30'],
            'concepto'       => ['required', 'string', 'max:200'],
            'total'          => ['required', 'numeric', 'min:0'],
            'metodo_pago'    => ['required', 'in:' . implode(',', array_keys(config('sparta.metodos_pago')))],
            'notas'          => ['nullable', 'string', 'max:500'],
            'tipo'           => ['nullable', 'string', 'in:servicio,otro'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'fecha.date_format' => 'La columna fecha debe tener el formato d/m/Y H:i (ej: 15/08/2026 14:30).',
            'metodo_pago.in'    => 'El método de pago no es válido.',
            'tipo.in'           => 'La columna tipo solo admite servicio u otro.',
        ];
    }
}
