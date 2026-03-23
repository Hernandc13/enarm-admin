<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimulatorCategory extends Model
{
    protected $table = 'simulator_categories';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'bool',
        'sort_order' => 'int',
    ];

    public function simulators(): HasMany
    {
        return $this->hasMany(Simulator::class, 'category_id');
    }
}