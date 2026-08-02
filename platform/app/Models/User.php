<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\HasPlanLimits;
use App\Services\UserFinderService;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
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

    /** Subscription individual (morph) */
    public function subscription()
    {
        return $this->morphOne(Subscription::class, 'subscriber')->where('status', 'active')->latestOfMany();
    }

    public function studentProfile()
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function schoolClasses()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_student', 'user_id', 'school_class_id');
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
