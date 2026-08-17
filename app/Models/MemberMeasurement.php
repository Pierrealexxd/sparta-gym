<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una toma de medidas.
 *
 * El IMC no se guarda: es peso/altura², y almacenarlo abriría la puerta a que
 * quede desincronizado del dato que lo produce. Se calcula al leer.
 */
class MemberMeasurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id', 'recorded_by', 'measured_at', 'weight_kg', 'height_cm',
        'body_fat_pct', 'muscle_mass_kg', 'chest_cm', 'waist_cm', 'hip_cm',
        'arm_cm', 'thigh_cm', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'measured_at'    => 'date',
            'weight_kg'      => 'decimal:2',
            // height_cm es unsignedSmallInteger en la migración, no decimal:
            // castearlo a decimal:1 lo devolvería como "170.0" (el cast
            // decimal de Laravel produce string) y getAlturaAttribute()
            // declara ?int. Se castea a integer, que es su tipo real.
            'height_cm'      => 'integer',
            'body_fat_pct'   => 'decimal:1',
            'muscle_mass_kg' => 'decimal:2',
            // Perímetros: decimal(5,1) en la migración, igual que body_fat_pct.
            'chest_cm'       => 'decimal:1',
            'waist_cm'       => 'decimal:1',
            'hip_cm'         => 'decimal:1',
            'arm_cm'         => 'decimal:1',
            'thigh_cm'       => 'decimal:1',
        ];
    }

    public function member(): BelongsTo     { return $this->belongsTo(Member::class); }
    public function recordedBy(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }

    /** Altura de esta toma; si no se anotó, la de la ficha del socio. */
    public function getAlturaAttribute(): ?int
    {
        return $this->height_cm ?? $this->member?->height_cm;
    }

    public function getBmiAttribute(): ?float
    {
        $altura = $this->altura;

        if (! $altura || ! $this->weight_kg) {
            return null;
        }

        return round((float) $this->weight_kg / (($altura / 100) ** 2), 1);
    }

    /** Categoría OMS, para etiquetar el dato sin repetir los cortes por ahí. */
    public function getBmiCategoryAttribute(): ?string
    {
        return match (true) {
            $this->bmi === null => null,
            $this->bmi < 18.5   => 'Bajo peso',
            $this->bmi < 25     => 'Normal',
            $this->bmi < 30     => 'Sobrepeso',
            default             => 'Obesidad',
        };
    }
}
