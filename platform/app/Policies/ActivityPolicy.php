<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;
use App\Services\PedagogicalAccessService;

class ActivityPolicy
{
    public function before(User $user): ?bool
    {
        return $user->type === 'global_admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasWorkspaceRole('teacher', 'admin', 'institution_admin', 'global_admin');
    }

    public function view(User $user, Activity $activity): bool
    {
        return (int) $user->organization_id === (int) $activity->organization_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Activity $activity): bool
    {
        return app(PedagogicalAccessService::class)->canManage($user, $activity->organization_id, $activity->author_id);
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $this->update($user, $activity);
    }
}
