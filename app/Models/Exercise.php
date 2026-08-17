<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Biblioteca de ejercicios.
 *
 * No usa BelongsToGym: un ejercicio con gym_id nulo es de la biblioteca
 * compartida y debe verse desde todos los gimnasios. El scope `disponibles`
 * expresa esa regla, que un filtro automático no sabría hacer.
 */
class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id', 'name', 'slug', 'description', 'muscle_groups', 'level',
        'equipment', 'category', 'image_path', 'video_url',
        'video_source', 'video_file_path',
        'common_mistakes', 'tips', 'is_active',
    ];

    protected function casts(): array
    {
        return ['muscle_groups' => 'array', 'is_active' => 'boolean'];
    }

    public function gym(): BelongsTo { return $this->belongsTo(Gym::class); }

    // Inversas de las tablas que apuntan a exercise_id: sirven para saber si
    // un ejercicio está en uso antes de desactivarlo o borrarlo.
    public function routineExercises(): HasMany
    {
        return $this->hasMany(RoutineExercise::class);
    }

    public function programRoutineExercises(): HasMany
    {
        return $this->hasMany(ProgramRoutineExercise::class);
    }

    /** Los globales más los propios del gimnasio indicado. */
    public function scopeDisponibles(Builder $q, ?int $gymId = null): Builder
    {
        $gymId ??= \App\Support\GymContext::id();

        return $q->where('is_active', true)
                 ->where(fn (Builder $s) => $s->whereNull('gym_id')->orWhere('gym_id', $gymId));
    }

    public function scopeDeGrupo(Builder $q, string $musculo): Builder
    {
        return $q->whereJsonContains('muscle_groups', $musculo);
    }

    /** ID de YouTube si video_url es un enlace válido (watch, youtu.be o embed). */
    public function getVideoIdAttribute(): ?string
    {
        if (! $this->video_url) {
            return null;
        }

        $query = parse_url($this->video_url, PHP_URL_QUERY);

        if ($query) {
            parse_str($query, $parametros);
            $id = $parametros['v'] ?? null;
        } else {
            $id = basename((string) parse_url($this->video_url, PHP_URL_PATH));
        }

        return is_string($id) && strlen($id) === 11 ? $id : null;
    }

    /**
     * URL incrustable (sin cookies) para el reproductor de la landing.
     * Se mantiene sin cambios a propósito (FASE 1 de PLAN-GUIAS-EJERCICIO.md):
     * la landing solo soportó siempre YouTube y sigue funcionando igual.
     */
    public function getVideoEmbedAttribute(): ?string
    {
        return $this->video_id
            ? "https://www.youtube-nocookie.com/embed/{$this->video_id}"
            : null;
    }

    /**
     * URL incrustable según `video_source` (FASE 1): YouTube sigue el
     * camino de siempre (video_embed), y se suman Vimeo, Google Drive, URL
     * genérica y archivo subido. Con `video_source` default 'youtube', un
     * ejercicio existente cae directo al primer caso sin cambiar nada.
     */
    public function getVideoUrlEmbedableAttribute(): ?string
    {
        return match ($this->video_source) {
            'vimeo'  => self::extraerIdVimeo((string) $this->video_url) ? 'https://player.vimeo.com/video/' . self::extraerIdVimeo((string) $this->video_url) : null,
            'gdrive' => self::convertirGDriveAEmbed($this->video_url),
            'url'    => $this->video_url,
            'upload' => $this->video_file_path ? asset('storage/' . $this->video_file_path) : null,
            default  => $this->video_embed,
        };
    }

    /** ID de Vimeo a partir de una URL tipo vimeo.com/123456789. */
    public static function extraerIdVimeo(string $url): ?string
    {
        return preg_match('~vimeo\.com/(?:.*/)?(\d+)~', $url, $m) ? $m[1] : null;
    }

    /**
     * Convierte una URL de Google Drive (…/file/d/{ID}/view o
     * …/open?id={ID}) a su versión incrustable (…/file/d/{ID}/preview).
     * Devuelve null si la URL no trae un ID de archivo reconocible, en vez
     * de incrustar un enlace roto.
     */
    public static function convertirGDriveAEmbed(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (preg_match('~drive\.google\.com/file/d/([^/]+)~', $url, $m)) {
            return "https://drive.google.com/file/d/{$m[1]}/preview";
        }

        if (preg_match('~[?&]id=([^&]+)~', $url, $m)) {
            return "https://drive.google.com/file/d/{$m[1]}/preview";
        }

        return null;
    }

    /** Mismo cálculo de getVideoEmbedAttribute() pero para una URL arbitraria (overrides de guía). */
    public static function youtubeEmbedDesdeUrl(string $url): ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if ($query) {
            parse_str($query, $parametros);
            $id = $parametros['v'] ?? null;
        } else {
            $id = basename((string) parse_url($url, PHP_URL_PATH));
        }

        return (is_string($id) && strlen($id) === 11)
            ? "https://www.youtube-nocookie.com/embed/{$id}"
            : null;
    }
}
