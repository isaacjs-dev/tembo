<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;
use App\Services\PedagogicalAccessService;

class LessonPolicy
{
    public function before(User $user): ?bool
    {
        return $user->type === 'global_admin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasWorkspaceRole('teacher', 'admin', 'institution_admin', 'global_admin');
    }

    public function view(User $user, Lesson $lesson): bool
    {
        return (int) $user->organization_id === (int) $lesson->organization_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return app(PedagogicalAccessService::class)->canManage($user, $lesson->organization_id, $lesson->author_id);
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $this->update($user, $lesson);
    }
}
