<?php

namespace App\Models;

use App\Notifications\CustomResetPasswordNotification;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'last_name',
        'email',
        'password',
        'is_admin',

        'origin_university',
        'origin_municipality',
        'desired_specialty',
        'whatsapp_number',

        'moodle_user_id',
        'is_from_moodle',
        'has_app_access',
        'granted_at',
        'revoked_at',
        'synced_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'moodle_user_id',
    ];

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

    public function getFullnameAttribute(): string
    {
        return trim(($this->name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin_enarm') {
            return (bool) ($this->is_admin ?? false);
        }

        return false;
    }

    public function isManualAppUser(): bool
    {
        return ! (bool) ($this->is_admin ?? false)
            && ! (bool) ($this->is_from_moodle ?? false)
            && empty($this->moodle_user_id);
    }

    /**
     * Personaliza el correo de recuperación de contraseña.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomResetPasswordNotification($token));
    }
}