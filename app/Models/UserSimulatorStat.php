<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSimulatorStat extends Model
{
    protected $fillable = [
        'user_id',
        'simulator_id',
        'attempts_count',
        'sum_scores',
        'avg_score',
        'best_score',
        'last_score',
        'last_attempt_at',
    ];

    protected $casts = [
        'avg_score'       => 'float',
        'last_attempt_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function simulator(): BelongsTo
    {
        return $this->belongsTo(Simulator::class, 'simulator_id');
    }
}