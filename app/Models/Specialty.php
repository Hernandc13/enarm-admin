<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Specialty extends Model
{
    protected $fillable = ['name', 'slug', 'is_active'];

    protected static function booted(): void
    {
        static::saving(function (self $m) {
            if (filled($m->name)) {
                $m->name = trim(preg_replace('/\s+/', ' ', $m->name));
            }

            // ✅ Solo generar slug si viene vacío (no lo regeneramos en ediciones)
            if (blank($m->slug) && filled($m->name)) {
                $m->slug = Str::slug($m->name);
            }
        });
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
