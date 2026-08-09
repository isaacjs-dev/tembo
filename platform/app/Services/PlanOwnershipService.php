<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;

class PlanOwnershipService
{
    public function owns(User $user, ?Organization $organization = null): bool
    {
        $organization ??= $user->organization;
        if (! $organization || ! $user->canUseOrganizationContext((int) $organization->id)) {
            return false;
        }

        if ($organization->owner_user_id !== null) {
            return (int) $organization->owner_user_id === (int) $user->id;
        }

        return $user->type === 'institution_admin'
            && ! $user->organizations()->exists()
            && (int) $user->getRawOriginal('organization_id') === (int) $organization->id;
    }

    public function authorize(User $user, ?Organization $organization = null): void
    {
        abort_unless($this->owns($user, $organization), 403);
    }
}
