<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CourtesyGrant extends Model
{
    protected $fillable = [
        'target_scope', 'target_id', 'target_role', 'status', 'starts_at', 'ends_at',
        'reason', 'authorized_by', 'suspended_by', 'cancelled_by', 'suspended_at',
        'cancelled_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime',
            'suspended_at' => 'datetime', 'cancelled_at' => 'datetime', 'metadata' => 'array',
        ];
    }

    public function benefits()
    {
        return $this->hasMany(CourtesyBenefit::class);
    }

    public function authorizer()
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function scopeEffective(Builder $query): Builder
    {
        return $query->whereIn('status', ['scheduled', 'active'])
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }
}
