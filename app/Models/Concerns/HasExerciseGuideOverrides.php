<?php

namespace App\Models\Concerns;

use App\Models\Exercise;

/**
 * FASE 1.4 de PLAN-GUIAS-EJERCICIO.md, extraído a trait porque el mismo
 * override de guía existe en dos tablas: `program_routine_exercises` (la
 * plantilla que edita el admin) y `routine_exercises` (la copia real que ve
 * el socio, clonada en Cliente\ProgramController::asignar). Sin el trait,
 * la lógica de "si no hay override, hereda del Exercise" quedaría duplicada
 * y fácil de desincronizar entre ambos modelos.
 *
 * Requiere que el modelo que lo use declare exercise(): BelongsTo y tenga
 * las columnas guide_video_url / guide_video_source / guide_video_file_path
 * / guide_description / guide_tips / guide_common_mistakes.
 */
trait HasExerciseGuideOverrides
{
    public function getEffectiveVideoEmbedAttribute(): ?string
    {
        if ($this->guide_video_url) {
            return $this->resolveGuideEmbed($this->guide_video_url, $this->guide_video_source, $this->guide_video_file_path);
        }

        return $this->exercise?->video_url_embedable;
    }

    public function getEffectiveDescriptionAttribute(): ?string
    {
        return $this->guide_description ?? $this->exercise?->description;
    }

    public function getEffectiveTipsAttribute(): ?string
    {
        return $this->guide_tips ?? $this->exercise?->tips;
    }

    public function getEffectiveCommonMistakesAttribute(): ?string
    {
        return $this->guide_common_mistakes ?? $this->exercise?->common_mistakes;
    }

    /** true si este ejercicio tiene alguna guía personalizada para este día/rutina. */
    public function getTieneGuiaPersonalizadaAttribute(): bool
    {
        return (bool) ($this->guide_video_url || $this->guide_description || $this->guide_tips || $this->guide_common_mistakes);
    }

    /** Mismo resolvedor que Exercise::getVideoUrlEmbedableAttribute(), pero sobre los campos de override. */
    private function resolveGuideEmbed(string $url, ?string $source, ?string $filePath): ?string
    {
        return match ($source) {
            'vimeo'  => Exercise::extraerIdVimeo($url) ? 'https://player.vimeo.com/video/' . Exercise::extraerIdVimeo($url) : null,
            'gdrive' => Exercise::convertirGDriveAEmbed($url),
            'url'    => $url,
            'upload' => $filePath ? asset('storage/' . $filePath) : null,
            default  => Exercise::youtubeEmbedDesdeUrl($url),
        };
    }
}
