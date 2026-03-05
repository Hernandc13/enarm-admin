<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimulatorAttemptQuestion extends Model
{
    protected $fillable = [
        'attempt_id',
        'question_id',
        'position',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(SimulatorAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
