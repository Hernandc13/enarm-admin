<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimulatorAttempt extends Model
{
    protected $fillable = [
        'simulator_id',
        'user_id',
        'started_at',
        'finished_at',
        'expires_at',
        'total_questions',
        'correct_count',
        'score',
        'status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function simulator(): BelongsTo
    {
        return $this->belongsTo(Simulator::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attemptQuestions(): HasMany
    {
        return $this->hasMany(SimulatorAttemptQuestion::class, 'attempt_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SimulatorAttemptAnswer::class, 'attempt_id');
    }
}
