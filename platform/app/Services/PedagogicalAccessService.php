<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PedagogicalAccessService
{
    public function __construct(private readonly InstitutionPermissionService $permissions) {}

    public function classesFor(User $user): Collection
    {
        return SchoolClass::query()
            ->where('organization_id', $user->organization_id)
            ->when($user->hasWorkspaceRole('teacher'), function (Builder $query) use ($user): void {
                $query->where(function (Builder $scope) use ($user): void {
                    $scope->where(function (Builder $owner) use ($user): void {
                        $owner->where('owner_type', 'user')->where('owner_id', $user->id);
                    })
                        ->orWhereHas('teachers', fn (Builder $teachers) => $teachers->where('users.id', $user->id));
                });
            })
            ->orderBy('name')
            ->get();
    }

    public function validateClassIds(User $user, array $classIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $classIds)));
        $allowed = $this->classesFor($user)->pluck('id')->map(fn ($id) => (int) $id)->all();

        abort_unless(array_diff($ids, $allowed) === [], 403, 'Uma ou mais turmas não estão disponíveis para este usuário.');

        return $ids;
    }

    public function canManage(User $user, int $organizationId, int $authorId): bool
    {
        if ((int) $user->organization_id !== $organizationId) {
            return false;
        }

        return (int) $user->id === $authorId
            || $this->permissions->allows($user, 'manage_pedagogical_content', $organizationId);
    }

    public function canCreate(User $user, int $organizationId): bool
    {
        return (int) $user->organization_id === $organizationId
            && $this->permissions->allows($user, 'manage_pedagogical_content', $organizationId);
    }

    public function canView(User $user, int $organizationId): bool
    {
        return (int) $user->organization_id === $organizationId
            && $this->permissions->allows($user, 'view_pedagogical_content', $organizationId);
    }

    public function shouldScopeToAuthor(User $user, int $organizationId): bool
    {
        if ($user->roleInOrganization($organizationId) !== 'teacher') {
            return false;
        }

        return ! DB::table('user_organization')
            ->where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->whereNotNull('institution_role_id')
            ->exists();
    }

    public function canReview(User $user, int $organizationId): bool
    {
        if ((int) $user->organization_id !== $organizationId) {
            return false;
        }

        return $this->permissions->allows($user, 'review_revisions', $organizationId);
    }
}
