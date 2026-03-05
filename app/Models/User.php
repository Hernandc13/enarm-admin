<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        // Base
        'name',
        'last_name',
        'email',
        'password',
        'is_admin',

        // ✅ Nuevos campos (solo manual/excel)
        'origin_university',     // universidad de origen
        'origin_municipality',   // municipio de procedencia
        'desired_specialty',     // especialidad deseada
        'whatsapp_number',       // numero de whatsapp

        // Moodle / App access
        'moodle_user_id',
        'is_from_moodle',
        'has_app_access',
        'granted_at',
        'revoked_at',
        'synced_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',

        // si devuelves el modelo completo en /me, evita exponerlo por error:
        'moodle_user_id',
    ];

    /**
     * Casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',

            'is_admin' => 'boolean',
            'is_from_moodle' => 'boolean',
            'has_app_access' => 'boolean',

            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * Accessor: nombre completo.
     */
    public function getFullnameAttribute(): string
    {
        return trim(($this->name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    /**
     * Solo admins pueden acceder al panel admin_enarm.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin_enarm') {
            return (bool) ($this->is_admin ?? false);
        }

        return false;
    }

    /**
     * Helper: es usuario manual/excel (NO Moodle, NO admin)
     */
    public function isManualAppUser(): bool
    {
        return ! (bool) ($this->is_admin ?? false)
            && ! (bool) ($this->is_from_moodle ?? false)
            && empty($this->moodle_user_id);
    }
}
