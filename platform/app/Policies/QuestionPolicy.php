<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;
use App\Services\QuestionLibraryService;

class QuestionPolicy
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

    public function view(User $user, Question $question): bool
    {
        return app(QuestionLibraryService::class)->canView($user, $question);
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function update(User $user, Question $question): bool
    {
        return $question->visibility_scope !== 'platform_public'
            && (int) $user->organization_id === (int) $question->organization_id
            && (int) $question->owner_id === (int) $user->id;
    }

    public function delete(User $user, Question $question): bool
    {
        return $this->update($user, $question);
    }

    public function share(User $user, Question $question): bool
    {
        return $this->update($user, $question);
    }

    public function duplicate(User $user, Question $question): bool
    {
        return $this->view($user, $question);
    }
}
