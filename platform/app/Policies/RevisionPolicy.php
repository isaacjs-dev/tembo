<?php

namespace App\Policies;

use App\Models\Revision;
use App\Models\User;
use App\Services\PedagogicalAccessService;

class RevisionPolicy
{
    public function before(User $user): ?bool
    {
        return $user->type === 'global_admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasWorkspaceRole('teacher', 'admin', 'institution_admin', 'global_admin');
    }

    public function view(User $user, Revision $revision): bool
    {
        return (int) $user->organization_id === (int) $revision->organization_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Revision $revision): bool
    {
        return app(PedagogicalAccessService::class)->canManage($user, $revision->organization_id, $revision->author_id);
    }

    public function review(User $user, Revision $revision): bool
    {
        return app(PedagogicalAccessService::class)->canReview($user, $revision->organization_id);
    }

    public function delete(User $user, Revision $revision): bool
    {
        return $this->update($user, $revision);
    }
}
