<?php

namespace App\Models;

use App\Models\Concerns\HasExerciseGuideOverrides;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineExercise extends Model
{
    use HasFactory, HasExerciseGuideOverrides;

    protected $fillable = [
        'routine_day_id', 'exercise_id', 'sort_order', 'sets', 'reps',
        'weight_kg', 'time_seconds', 'rest_seconds', 'notes',
        // Mismos overrides de guía que ProgramRoutineExercise: se copian acá
        // al clonar la rutina (Cliente\ProgramController::asignar) para que
        // "Mi Rutina" —que lee esta tabla, no la plantilla— los vea.
        'guide_video_url', 'guide_video_source', 'guide_video_file_path',
        'guide_description', 'guide_tips', 'guide_common_mistakes',
    ];

    protected function casts(): array
    {
        return ['weight_kg' => 'decimal:2'];
    }

    public function day(): BelongsTo      { return $this->belongsTo(RoutineDay::class, 'routine_day_id'); }
    public function exercise(): BelongsTo { return $this->belongsTo(Exercise::class); }

    /** Prescripción en una línea: "4 x 8-10 · 60 kg · 90 s". */
    public function getPrescripcionAttribute(): string
    {
        $partes = [];

        if ($this->reps)         $partes[] = "{$this->sets} x {$this->reps}";
        if ($this->time_seconds) $partes[] = "{$this->time_seconds} s";
        if ($this->weight_kg)    $partes[] = rtrim(rtrim((string) $this->weight_kg, '0'), '.') . ' kg';
        if ($this->rest_seconds) $partes[] = "descanso {$this->rest_seconds} s";

        return implode(' · ', $partes);
    }
}
