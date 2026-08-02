<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserOrganization extends Pivot
{
    protected $table = 'user_organization';

    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'organization_id',
        'role_in_org',
        'status',
        'joined_at',
        'institution_role_id',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    /* ── Relationships ── */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function role()
    {
        return $this->belongsTo(InstitutionRole::class, 'institution_role_id');
    }

    /* ── Scopes ── */

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    /* ── Helpers ── */

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
