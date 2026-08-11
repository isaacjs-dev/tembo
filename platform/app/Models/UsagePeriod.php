<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsagePeriod extends Model
{
    protected $fillable = [
        'user_id', 'organization_id', 'scope_key', 'membership_id', 'resource_key', 'period_start', 'period_end',
        'allowance', 'bonus_credits', 'consumed', 'manual_resets',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'allowance' => 'integer',
            'bonus_credits' => 'integer',
            'consumed' => 'integer',
            'manual_resets' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function events()
    {
        return $this->hasMany(UsageEvent::class);
    }
}
