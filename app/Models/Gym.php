<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * El inquilino. No usa BelongsToGym: es la raíz del aislamiento, no un dato
 * aislado por él.
 */
class Gym extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'tagline', 'description', 'logo_path',
        'email', 'phone', 'whatsapp', 'address', 'city', 'country',
        'latitude', 'longitude', 'schedule', 'socials', 'settings',
        'currency', 'timezone', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'schedule'  => 'array',
            'socials'   => 'array',
            'settings'  => 'array',
            'is_active' => 'boolean',
            'latitude'  => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function users(): HasMany       { return $this->hasMany(User::class); }
    public function members(): HasMany     { return $this->hasMany(Member::class); }
    public function plans(): HasMany       { return $this->hasMany(Plan::class); }
    public function trainers(): HasMany    { return $this->hasMany(Trainer::class); }
    public function sales(): HasMany       { return $this->hasMany(Sale::class); }
    public function attendances(): HasMany { return $this->hasMany(Attendance::class); }
    public function testimonials(): HasMany{ return $this->hasMany(Testimonial::class); }
    public function facilities(): HasMany  { return $this->hasMany(Facility::class); }
    public function faqs(): HasMany        { return $this->hasMany(Faq::class); }
    public function gallery(): HasMany     { return $this->hasMany(GalleryImage::class); }
    public function qrCodes(): HasMany     { return $this->hasMany(GymQrCode::class); }

    // Resto de tablas con gym_id que no tenían su inversa declarada. Se
    // omiten a propósito member_goals y meal_logs (cuelgan del socio, no
    // llevan gym_id) y recipe_categories (no existe ese modelo).
    public function programs(): HasMany       { return $this->hasMany(Program::class); }
    public function products(): HasMany       { return $this->hasMany(Product::class); }
    public function conversations(): HasMany  { return $this->hasMany(Conversation::class); }
    public function contactMessages(): HasMany{ return $this->hasMany(ContactMessage::class); }
    public function cashClosings(): HasMany   { return $this->hasMany(CashClosing::class); }
    public function staffAttendances(): HasMany       { return $this->hasMany(StaffAttendance::class); }
    public function attendanceEditRequests(): HasMany { return $this->hasMany(AttendanceEditRequest::class); }
    public function recipes(): HasMany        { return $this->hasMany(Recipe::class); }

    /** Horario de hoy, listo para pintar en la landing. */
    public function horarioDeHoy(): ?array
    {
        $dia = strtolower(now($this->timezone)->locale('es')->dayName);

        return collect($this->schedule ?? [])
            ->first(fn (array $h) => str_starts_with(strtolower($h['dia'] ?? ''), substr($dia, 0, 3)));
    }
}
