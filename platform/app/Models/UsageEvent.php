<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageEvent extends Model
{
    protected $fillable = [
        'usage_period_id', 'user_id', 'organization_id', 'actor_id', 'resource_key',
        'event_type', 'amount', 'idempotency_key', 'context_type', 'context_id',
        'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime', 'amount' => 'integer'];
    }

    public function period()
    {
        return $this->belongsTo(UsagePeriod::class, 'usage_period_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function context()
    {
        return $this->morphTo();
    }
}
