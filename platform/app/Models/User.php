<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\HasPlanLimits;
use App\Services\UserFinderService;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasPlanLimits, HasRoles, MustVerifyEmail, Notifiable, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'password',
        'type',
        'status',
        'link_code',
        'settings',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->link_code)) {
                $user->link_code = UserFinderService::generateLinkCode();
            }
        });
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array',
        ];
    }

    /* ── Relationships ── */

    /** Legado: relação direta (organization_id FK) */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /** N:N pivot user_organization */
    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'user_organization')
            ->using(UserOrganization::class)
            ->withPivot('role_in_org', 'status', 'joined_at')
            ->withTimestamps();
    }

    public function activeOrganizations()
    {
        return $this->organizations()->wherePivot('status', 'active');
    }

    /**
     * Restrict users to a membership in an organization. During the compatibility
     * window, a legacy organization_id is accepted only when no pivot exists for
     * that same organization; an inactive pivot therefore cannot be bypassed by
     * the legacy column.
     */
    public function scopeMemberOfOrganization(
        Builder $query,
        int $organizationId,
        ?string $role = null,
        bool $activeOnly = true,
    ): Builder {
        return $query->where(function (Builder $membershipQuery) use ($organizationId, $role, $activeOnly) {
            $membershipQuery->whereHas('organizations', function (Builder $organizationQuery) use ($organizationId, $role, $activeOnly) {
                $organizationQuery->where('organizations.id', $organizationId);

                if ($activeOnly) {
                    $organizationQuery->where('user_organization.status', 'active');
                }

                if ($role !== null) {
                    $organizationQuery->where('user_organization.role_in_org', $role);
                }
            })->orWhere(function (Builder $legacyQuery) use ($organizationId, $role) {
                $legacyQuery->where('users.organization_id', $organizationId)
                    // The direct FK is a compatibility path only for accounts
                    // that have not entered the membership model at all. Once
                    // any pivot exists, it is the sole source of tenant access.
                    ->whereDoesntHave('organizations');

                if ($role !== null) {
                    $legacyQuery->where('users.type', $role);
                }
            });
        });
    }

    public function belongsToActiveOrganization(int $organizationId, ?string $role = null): bool
    {
        return static::query()
            ->whereKey($this->getKey())
            ->memberOfOrganization($organizationId, $role)
            ->exists();
    }

    /** Global admins may select an active tenant; other users need an active membership. */
    public function canUseOrganizationContext(int $organizationId): bool
    {
        if ($organizationId < 1) {
            return false;
        }

        if ($this->type === 'global_admin') {
            return Organization::query()
                ->whereKey($organizationId)
                ->where('active', true)
                ->exists();
        }

        return $this->belongsToActiveOrganization($organizationId);
    }

    /** Resolve the contextual membership status without confusing it with users.status. */
    public function organizationMembershipStatus(int $organizationId): ?string
    {
        $organization = $this->relationLoaded('organizations')
            ? $this->organizations->firstWhere('id', $organizationId)
            : $this->organizations()->whereKey($organizationId)->first();

        if ($organization !== null) {
            return $organization->pivot->status;
        }

        return (int) $this->organization_id === $organizationId
            && ! $this->organizations()->exists()
            ? $this->status
            : null;
    }

    /** Resolve the active role in one workspace without relying on the global account type. */
    public function roleInOrganization(?int $organizationId = null): ?string
    {
        if ($this->type === 'global_admin') {
            return 'global_admin';
        }

        $organizationId ??= (int) $this->organization_id;
        if ($organizationId < 1) {
            return null;
        }

        $organization = $this->organizations()
            ->whereKey($organizationId)
            ->wherePivot('status', 'active')
            ->first();

        if ($organization) {
            return $organization->pivot->role_in_org;
        }

        return ! $this->organizations()->exists() && (int) $this->organization_id === $organizationId
            ? $this->type
            : null;
    }

    public function hasWorkspaceRole(string ...$roles): bool
    {
        return in_array($this->roleInOrganization(), $roles, true);
    }

    /** Subscription individual (morph) */
    public function subscription()
    {
        return $this->morphOne(Subscription::class, 'subscriber')->where('status', 'active')->latestOfMany();
    }

    public function studentProfile()
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function studentProfiles()
    {
        return $this->hasMany(StudentProfile::class);
    }

    public function schoolClasses()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_student', 'user_id', 'school_class_id');
    }

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class, 'student_id');
    }

    public function activityAttempts()
    {
        return $this->hasMany(ActivityAttempt::class, 'student_id');
    }

    public function taughtClasses()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_teacher', 'user_id', 'school_class_id')
            ->withPivot('assigned_at');
    }

    public function taughtStudents()
    {
        return $this->belongsToMany(User::class, 'teacher_student', 'teacher_id', 'student_id')
            ->withPivot('organization_id', 'linked_by')
            ->withTimestamps();
    }

    public function academicTeachers()
    {
        return $this->belongsToMany(User::class, 'teacher_student', 'student_id', 'teacher_id')
            ->withPivot('organization_id', 'linked_by')
            ->withTimestamps();
    }

    public function taughtDisciplines()
    {
        return $this->belongsToMany(Discipline::class, 'discipline_teacher', 'user_id', 'discipline_id')
            ->withPivot('organization_id', 'assigned_by', 'assigned_at')
            ->withTimestamps();
    }

    /**
     * Estudantes que este responsável está autorizado a acompanhar.
     */
    public function guardedStudents()
    {
        return $this->belongsToMany(
            User::class,
            'guardian_student_links',
            'guardian_id',
            'student_id'
        )
            ->wherePivotNull('deleted_at')
            ->withPivot('id', 'organization_id', 'relationship', 'created_at')
            ->withTimestamps();
    }

    /**
     * Responsáveis formalmente vinculados a este estudante.
     */
    public function guardians()
    {
        return $this->belongsToMany(
            User::class,
            'guardian_student_links',
            'student_id',
            'guardian_id'
        )
            ->wherePivotNull('deleted_at')
            ->withPivot('id', 'organization_id', 'relationship', 'created_at')
            ->withTimestamps();
    }

    public function learningMaterialProgress()
    {
        return $this->hasMany(LearningMaterialProgress::class, 'student_id');
    }

    public function revisionAttempts()
    {
        return $this->hasMany(RevisionAttempt::class, 'student_id');
    }

    public function gamificationProfile()
    {
        return $this->hasOne(StudentGamificationProfile::class, 'student_id');
    }

    /** Convites enviados */
    public function sentInvites()
    {
        return $this->hasMany(Invite::class, 'inviter_id');
    }

    /** Convites recebidos (por email) */
    public function receivedInvites()
    {
        return Invite::where('invitee_email', $this->email);
    }

    /* ── Feature & Plan Checks ── */

    public function hasFeature(string $feature): bool
    {
        // 1. Plano individual
        $plan = $this->subscription?->plan;
        if ($plan && $plan->hasFeature($feature)) {
            return true;
        }

        // 2. Planos institucionais (via pivot)
        foreach ($this->activeOrganizations as $org) {
            $orgPlan = $org->subscription?->plan;
            if ($orgPlan && $orgPlan->hasFeature($feature)) {
                return true;
            }
        }

        // 3. Legado: organização direta
        $directOrg = $this->organization;
        if ($directOrg) {
            return $directOrg->hasFeature($feature);
        }

        return false;
    }
}
