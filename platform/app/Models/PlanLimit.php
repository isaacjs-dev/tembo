<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanLimit extends Model
{
    protected $fillable = [
        'plan_id',
        'resource_key',
        'limit_value',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
