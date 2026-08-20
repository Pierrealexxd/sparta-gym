<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use App\Support\GymContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Member extends Model
{
    use HasFactory, SoftDeletes, BelongsToGym;

    protected $fillable = [
        'gym_id', 'user_id', 'code', 'qr_token',
        'first_name', 'last_name', 'document', 'email', 'phone',
        'birth_date', 'gender', 'photo_path',
        'emergency_contact', 'emergency_phone', 'medical_notes',
        'height_cm', 'joined_at', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'joined_at'  => 'date',
            'height_cm'  => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // El token del QR nunca lo escribe un formulario: se genera aquí.
        static::creating(function (Member $socio) {
            $socio->qr_token ??= (string) Str::uuid();
            $socio->joined_at ??= now()->toDateString();
        });
    }

    /**
     * Bypasea el global scope 'gym' al resolver por route model binding.
     * Sin esto, un admin en sede "cruceta" no podría ver un cliente de
     * otra sede (el scope filtraría por gym_id y el findOrFail 404earía).
     * La autorización real la hace cada controlador, no el scope.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->withoutGlobalScope('gym')
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }

    /* ---------------------------------------------------------- */
    /* Relaciones                                                 */
    /* ---------------------------------------------------------- */

    public function user(): BelongsTo         { return $this->belongsTo(User::class); }
    public function memberships(): HasMany    { return $this->hasMany(Membership::class); }
    public function sales(): HasMany          { return $this->hasMany(Sale::class); }
    public function attendances(): HasMany    { return $this->hasMany(Attendance::class); }
    public function measurements(): HasMany   { return $this->hasMany(MemberMeasurement::class); }
    public function goals(): HasMany          { return $this->hasMany(MemberGoal::class); }
    public function mealLogs(): HasMany       { return $this->hasMany(MealLog::class); }
    public function savedMeals(): HasMany     { return $this->hasMany(SavedMeal::class); }
    public function routines(): HasMany       { return $this->hasMany(Routine::class); }
    public function assignments(): HasMany    { return $this->hasMany(TrainerAssignment::class); }
    public function testimonial(): HasOne     { return $this->hasOne(Testimonial::class); }

    /** Membresía vigente: la de fin más lejano entre las activas. */
    public function currentMembership(): HasOne
    {
        return $this->hasOne(Membership::class)
            ->where('status', 'activa')
            ->latest('ends_at');
    }

    public function latestMeasurement(): HasOne
    {
        return $this->hasOne(MemberMeasurement::class)->latest('measured_at');
    }

    /** Entrenador actual: la asignación que aún no ha terminado. */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(TrainerAssignment::class)
            ->whereNull('ended_at')
            ->latest('assigned_at');
    }

    /* ---------------------------------------------------------- */
    /* Consultas frecuentes                                       */
    /* ---------------------------------------------------------- */

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('status', 'activo');
    }

    /** Socios cuya membresía ya venció (o que nunca tuvieron una). */
    public function scopeConMembresiaVencida(Builder $q): Builder
    {
        return $q->whereDoesntHave('memberships', function (Builder $m) {
            $m->where('status', 'activa')->whereDate('ends_at', '>=', now());
        });
    }

    /** Socios a los que les vence la membresía en los próximos N días. */
    public function scopePorVencer(Builder $q, int $dias = 7): Builder
    {
        return $q->whereHas('memberships', function (Builder $m) use ($dias) {
            $m->where('status', 'activa')
              ->whereBetween('ends_at', [now()->toDateString(), now()->addDays($dias)->toDateString()]);
        });
    }

    public function scopeBuscar(Builder $q, ?string $termino): Builder
    {
        if (blank($termino)) {
            return $q;
        }

        $t = '%' . trim($termino) . '%';

        return $q->where(function (Builder $sub) use ($t) {
            $sub->where('first_name', 'like', $t)
                ->orWhere('last_name', 'like', $t)
                ->orWhere('code', 'like', $t)
                ->orWhere('document', 'like', $t)
                ->orWhere('phone', 'like', $t)
                ->orWhere('email', 'like', $t);
        });
    }

    /** SP-XXXX correlativo y legible en recepción, sin colisiones dentro del gimnasio. */
    public static function generarCodigo(): string
    {
        do {
            $codigo = 'SP-' . random_int(1000, 9999);
        } while (static::where('gym_id', GymContext::id())->where('code', $codigo)->exists());

        return $codigo;
    }

    /* ---------------------------------------------------------- */
    /* Atributos derivados                                        */
    /* ---------------------------------------------------------- */

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getInitialsAttribute(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1) . mb_substr($this->last_name, 0, 1));
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }

    /** ¿Puede entrenar hoy? Es la pregunta que hace recepción en el torno. */
    public function getIsUpToDateAttribute(): bool
    {
        $m = $this->relationLoaded('currentMembership')
            ? $this->currentMembership
            : $this->currentMembership()->first();

        return $m !== null && $m->ends_at->endOfDay()->isFuture();
    }

    public function getDaysLeftAttribute(): ?int
    {
        $m = $this->currentMembership;

        return $m ? now()->startOfDay()->diffInDays($m->ends_at, false) : null;
    }

    /**
     * Referencia diaria de porciones de mano, por comida — Fase 1 del plan
     * de nutrición (ver PLAN_NUTRICION_PROGRESO.md, Fase 0 para el método).
     * No se guarda: se deriva de género + tipo de meta cada vez que se
     * pide, igual que el IMC se deriva del peso — así nunca queda
     * desincronizada del dato que la produce.
     *
     * Base (Precision Nutrition): hombre 2 de cada porción por comida,
     * mujer 1. 'O' o sin género cae al lado conservador (mujer). Perder
     * peso resta 1 cuenco + 1 pulgar (~-250 kcal/día); ganar músculo suma
     * lo mismo — el resto de metas (fuerza, resistencia, salud, otro) usan
     * la base sin ajuste, porque el método no dice nada sobre ellas.
     */
    public function porcionesPara(MemberGoal $meta): array
    {
        $base = $this->gender === 'M'
            ? ['palma' => 2, 'puno' => 2, 'cuenco' => 2, 'pulgar' => 2]
            : ['palma' => 1, 'puno' => 1, 'cuenco' => 1, 'pulgar' => 1];

        return match ($meta->type) {
            'perder_peso' => [
                ...$base,
                'cuenco' => max(0, $base['cuenco'] - 1),
                'pulgar' => max(0, $base['pulgar'] - 1),
            ],
            'ganar_musculo' => [
                ...$base,
                'cuenco' => $base['cuenco'] + 1,
                'pulgar' => $base['pulgar'] + 1,
            ],
            default => $base,
        };
    }

    /**
     * Referencia DIARIA (no por comida) para comparar contra lo registrado
     * en el diario de comidas (Fase 2) — la guía dice "por comida (~4
     * comidas)", así que el total del día es la referencia de la meta
     * activa × 4. Usa la primera meta activa; sin ninguna, la base según
     * género (sin ajuste de meta).
     */
    public function porcionesDiarias(): array
    {
        $meta = $this->goals()->activos()->first();
        $porComida = $this->porcionesPara($meta ?? new MemberGoal(['type' => 'salud']));

        return array_map(fn ($n) => $n * 4, $porComida);
    }
}
