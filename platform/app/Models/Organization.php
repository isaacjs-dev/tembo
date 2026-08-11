<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\HasPlanLimits;
use App\Services\EntitlementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use Auditable, HasPlanLimits, SoftDeletes;

    protected $fillable = [
        'name',
        'workspace_type',
        'subdomain',
        'active',
        'allow_class_copy',
        'owner_user_id',
        'settings',
        'can_access_trash',
        'can_access_logs',
        'trash_access_users',
        'logs_access_users',
    ];

    protected $casts = [
        'active' => 'boolean',
        'allow_class_copy' => 'boolean',
        'can_access_trash' => 'boolean',
        'can_access_logs' => 'boolean',
        'trash_access_users' => 'array',
        'logs_access_users' => 'array',
        'settings' => 'array',
    ];

    public function isPersonalWorkspace(): bool
    {
        return $this->workspace_type === 'personal';
    }

    /* ── Relationships ── */

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** Legado: relação direta (organization_id na users) */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /** N:N pivot user_organization */
    public function members()
    {
        return $this->belongsToMany(User::class, 'user_organization')
            ->using(UserOrganization::class)
            ->withPivot('role_in_org', 'status', 'joined_at')
            ->withTimestamps();
    }

    public function activeMembers()
    {
        return $this->members()->wherePivot('status', 'active');
    }

    public function teachers()
    {
        return $this->members()->wherePivot('role_in_org', 'teacher')->wherePivot('status', 'active');
    }

    public function students()
    {
        return $this->members()->wherePivot('role_in_org', 'student')->wherePivot('status', 'active');
    }

    public function invites()
    {
        return $this->hasMany(Invite::class);
    }

    public function pendingInvites()
    {
        return $this->invites()->where('status', 'pending');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->where('status', 'active')
        );
    }

    /* ── Feature & Limit Checks (legado — mantidos para retrocompatibilidade) ── */

    public function hasFeature(string $feature): bool
    {
        return app(EntitlementService::class)
            ->planForOrganization($this)?->hasFeature($feature) ?? false;
    }

    public function canAddStudent(): bool
    {
        return $this->canCreateResource(
            'max_students',
            User::query()->memberOfOrganization((int) $this->id, 'student')->count(),
        );
    }

    public function canAddTeacher(): bool
    {
        return $this->canCreateResource(
            'max_teachers',
            User::query()->memberOfOrganization((int) $this->id, 'teacher')->count(),
        );
    }

    public function canAddClass(): bool
    {
        return $this->canCreateResource('max_classes', SchoolClass::where('organization_id', $this->id)->count());
    }
}
