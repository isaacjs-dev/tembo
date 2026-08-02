<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'plan_id',
        'subscriber_type',
        'subscriber_id',
        'status',
        'starts_at',
        'expires_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /* ---- Relationships ---- */

    /**
     * Morph: o assinante pode ser User ou Organization.
     */
    public function subscriber()
    {
        return $this->morphTo();
    }

    /**
     * Retrocompat: mantém relação direta com organization.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /* ---- Scopes ---- */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
