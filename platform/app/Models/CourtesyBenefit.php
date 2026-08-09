<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourtesyBenefit extends Model
{
    protected $fillable = [
        'courtesy_grant_id', 'benefit_type', 'resource_key', 'quantity',
        'plan_id', 'feature_key', 'metadata',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'metadata' => 'array'];
    }

    public function grant()
    {
        return $this->belongsTo(CourtesyGrant::class, 'courtesy_grant_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
