<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Simulator extends Model
{
    public const MODE_STUDY = 'study';
    public const MODE_EXAM  = 'exam';

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'mode',
        'available_from',
        'available_until',
        'max_attempts',
        'time_limit_seconds',
        'min_passing_score',
        'shuffle_questions',
        'shuffle_options',
        'is_published',
    ];

    protected $casts = [
        'available_from'      => 'datetime',
        'available_until'     => 'datetime',
        'shuffle_questions'   => 'bool',
        'shuffle_options'     => 'bool',
        'is_published'        => 'bool',
        'max_attempts'        => 'int',
        'time_limit_seconds'  => 'int',
        'min_passing_score'   => 'float',
        'category_id'         => 'int',
        'mode'                => 'string',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(SimulatorCategory::class, 'category_id');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'simulator_questions')
            ->withTimestamps()
            ->withPivot(['order']);
    }

    public function isAvailableNow(): bool
    {
        $now = now();

        if ($this->available_from && $now->lt($this->available_from)) {
            return false;
        }

        if ($this->available_until && $now->gt($this->available_until)) {
            return false;
        }

        return true;
    }

    public function isStudyMode(): bool
    {
        return $this->mode === self::MODE_STUDY;
    }

    public function isExamMode(): bool
    {
        return $this->mode === self::MODE_EXAM;
    }
}