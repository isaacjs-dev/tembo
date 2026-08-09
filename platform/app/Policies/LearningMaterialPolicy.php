<?php

namespace App\Policies;

use App\Models\LearningMaterial;
use App\Models\User;

class LearningMaterialPolicy
{
    public function before(User $user): ?bool
    {
        if (! $user->hasWorkspaceRole('teacher', 'admin', 'institution_admin', 'global_admin')) {
            return false;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function view(User $user, LearningMaterial $material): bool
    {
        return $this->canManage($user, $material);
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function update(User $user, LearningMaterial $material): bool
    {
        return $this->canManage($user, $material);
    }

    public function delete(User $user, LearningMaterial $material): bool
    {
        return $this->canManage($user, $material);
    }

    private function canManage(User $user, LearningMaterial $material): bool
    {
        if ((int) $user->organization_id !== (int) $material->organization_id) {
            return false;
        }

        return $user->hasWorkspaceRole('admin', 'institution_admin', 'global_admin')
            || (int) $material->author_id === (int) $user->id;
    }
}
