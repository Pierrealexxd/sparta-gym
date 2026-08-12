<?php

namespace App\Models\Concerns;

use App\Models\Gym;
use App\Support\GymContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aísla los datos de cada gimnasio.
 *
 * Es la pieza que hace viable el salto a SaaS: cualquier modelo que use este
 * trait queda filtrado por el gimnasio activo sin que las consultas tengan
 * que acordarse de hacerlo. Olvidar un `where('gym_id', ...)` deja de ser
 * una fuga de datos posible.
 *
 * Para tareas que legítimamente cruzan gimnasios (informes globales, comandos
 * de mantenimiento) está `sinFiltroDeGimnasio()`, que es explícito a propósito:
 * saltarse el aislamiento debe verse en el código.
 */
trait BelongsToGym
{
    protected static function bootBelongsToGym(): void
    {
        static::addGlobalScope('gym', function (Builder $query) {
            if ($gymId = GymContext::id()) {
                $query->where($query->getModel()->getTable() . '.gym_id', $gymId);
            }
        });

        // Al crear, el gimnasio se hereda del contexto: ningún formulario
        // necesita enviar gym_id, y por tanto nadie puede falsearlo.
        static::creating(function ($model) {
            if (empty($model->gym_id) && $gymId = GymContext::id()) {
                $model->gym_id = $gymId;
            }
        });
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /** Consulta que atraviesa todos los gimnasios. Usar con intención. */
    public function scopeSinFiltroDeGimnasio(Builder $query): Builder
    {
        return $query->withoutGlobalScope('gym');
    }
}
