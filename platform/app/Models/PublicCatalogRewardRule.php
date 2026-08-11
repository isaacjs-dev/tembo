<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicCatalogRewardRule extends Model
{
    protected $fillable = [
        'name', 'rule_version', 'subject_kind', 'resource_key', 'credit_amount',
        'per_user_monthly_cap', 'global_monthly_cap', 'status', 'starts_at', 'ends_at',
        'created_by', 'activated_by', 'activated_at', 'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'credit_amount' => 'integer',
            'per_user_monthly_cap' => 'integer',
            'global_monthly_cap' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'activated_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    public function awards()
    {
        return $this->hasMany(PublicCatalogRewardAward::class);
    }
}
