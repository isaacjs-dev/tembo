<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicCatalogRewardAward extends Model
{
    protected $fillable = [
        'public_catalog_reward_rule_id', 'public_catalog_submission_id', 'user_id',
        'organization_id', 'membership_id', 'usage_event_id', 'scope_key', 'resource_key',
        'rule_version', 'requested_amount', 'awarded_amount', 'status', 'idempotency_key',
        'period_start', 'metadata', 'awarded_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'integer', 'awarded_amount' => 'integer',
            'period_start' => 'date', 'metadata' => 'array', 'awarded_at' => 'datetime',
        ];
    }

    public function rule()
    {
        return $this->belongsTo(PublicCatalogRewardRule::class, 'public_catalog_reward_rule_id');
    }

    public function submission()
    {
        return $this->belongsTo(PublicCatalogSubmission::class, 'public_catalog_submission_id');
    }

    public function usageEvent()
    {
        return $this->belongsTo(UsageEvent::class);
    }
}
