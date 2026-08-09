<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\User;
use App\Models\UserOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PedagogicalAccessService
{
    public function classesFor(User $user): Collection
    {
        return SchoolClass::query()
            ->where('organization_id', $user->organization_id)
            ->when($user->type === 'teacher', function (Builder $query) use ($user): void {
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
        if ($user->type === 'global_admin') {
            return true;
        }

        if ((int) $user->organization_id !== $organizationId) {
            return false;
        }

        return $user->type === 'institution_admin' || (int) $user->id === $authorId;
    }

    public function canReview(User $user, int $organizationId): bool
    {
        if ($user->type === 'global_admin') {
            return true;
        }
        if ((int) $user->organization_id !== $organizationId) {
            return false;
        }
        if ($user->type === 'institution_admin') {
            return true;
        }

        return UserOrganization::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->whereHas('role.permissions', fn (Builder $query) => $query->where('permission', 'review_revisions'))
            ->exists();
    }
}
