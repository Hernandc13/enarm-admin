<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityStat extends Model
{
    protected $fillable = [
        'user_id',
        'first_login_at',
        'last_login_at',
    ];

    protected $casts = [
        'first_login_at' => 'datetime',
        'last_login_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}