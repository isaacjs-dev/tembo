<?php

namespace App\Policies;

use App\Models\QuestionResource;
use App\Models\User;
use App\Services\QuestionResourceService;

class QuestionResourcePolicy
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

    public function view(User $user, QuestionResource $resource): bool
    {
        return app(QuestionResourceService::class)->canView($user, $resource);
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function update(User $user, QuestionResource $resource): bool
    {
        return (int) $user->organization_id === (int) $resource->organization_id
            && ((int) $resource->owner_id === (int) $user->id
                || $user->hasWorkspaceRole('admin', 'institution_admin', 'global_admin'));
    }

    public function delete(User $user, QuestionResource $resource): bool
    {
        return $this->update($user, $resource);
    }
}
