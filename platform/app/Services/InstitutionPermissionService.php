<?php

namespace App\Services;

use App\Models\InstitutionRolePermission;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InstitutionPermissionService
{
    /** @var array<string, array<int, string>> */
    private const BUILT_IN_PERMISSIONS = [
        'director' => [
            'view_teachers', 'manage_teachers', 'view_students', 'manage_students',
            'view_classes', 'manage_classes', 'view_questions', 'manage_questions',
            'view_exams', 'manage_exams', 'view_reports', 'view_invites', 'manage_invites',
            'view_omr', 'manage_omr', 'review_revisions',
        ],
        'coordinator' => [
            'view_teachers', 'manage_teachers', 'view_students', 'manage_students',
            'view_classes', 'manage_classes', 'view_questions', 'manage_questions',
            'view_exams', 'manage_exams', 'view_reports', 'view_invites', 'manage_invites',
            'view_omr', 'manage_omr', 'review_revisions',
        ],
        'pedagogue' => [
            'view_teachers', 'view_students', 'view_classes', 'view_questions',
            'view_exams', 'view_reports', 'view_invites', 'view_omr', 'review_revisions',
        ],
    ];

    public function allows(User $user, string $permission, ?int $organizationId = null): bool
    {
        $organizationId ??= (int) $user->organization_id;
        if ($organizationId < 1 || ! $user->canUseOrganizationContext($organizationId)) {
            return false;
        }

        $role = $user->roleInOrganization($organizationId);
        if (in_array($role, ['global_admin', 'admin', 'institution_admin'], true)) {
            return true;
        }

        if (in_array($permission, self::BUILT_IN_PERMISSIONS[$role] ?? [], true)) {
            return true;
        }

        $pivot = DB::table('user_organization')
            ->where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->first(['institution_role_id']);

        if ($pivot?->institution_role_id) {
            return InstitutionRolePermission::query()
                ->where('institution_role_id', $pivot->institution_role_id)
                ->whereHas('role', fn ($query) => $query
                    ->where('organization_id', $organizationId)
                    ->where('is_active', true))
                ->where('permission', $permission)
                ->exists();
        }

        if ($role !== 'teacher') {
            return false;
        }

        $teacherPermissions = [
            'view_questions', 'manage_questions', 'view_exams', 'manage_exams',
            'view_reports', 'view_omr', 'manage_omr',
        ];
        $organization = Organization::query()->find($organizationId);
        if ($organization?->isPersonalWorkspace()) {
            $teacherPermissions = [...$teacherPermissions, 'view_classes', 'manage_classes'];
        }

        return in_array($permission, $teacherPermissions, true);
    }

    /** @return array<int, string> */
    public function invitationalRoles(User $user, int $organizationId): array
    {
        $role = $user->roleInOrganization($organizationId);

        return match ($role) {
            'global_admin', 'admin', 'institution_admin' => ['director', 'coordinator', 'pedagogue', 'teacher', 'student'],
            'director' => ['coordinator', 'pedagogue', 'teacher', 'student'],
            'coordinator' => ['teacher', 'student'],
            default => $this->allows($user, 'manage_invites', $organizationId) ? ['teacher', 'student'] : [],
        };
    }
}
