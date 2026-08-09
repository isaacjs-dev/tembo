<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrganizationMembershipService
{
    /**
     * Change only the organization membership, never the global account status
     * or credentials. The legacy current context is moved away when it is revoked.
     */
    public function setStatus(User $user, Organization $organization, string $role, string $status): void
    {
        DB::transaction(function () use ($user, $organization, $role, $status) {
            $pivotExists = DB::table('user_organization')
                ->where('user_id', $user->id)
                ->where('organization_id', $organization->id)
                ->exists();
            $pivotData = [
                'role_in_org' => $role,
                'status' => $status,
            ];

            if (! $pivotExists && $status === 'active') {
                $pivotData['joined_at'] = now();
            }

            $user->organizations()->syncWithoutDetaching([
                $organization->id => $pivotData,
            ]);

            if ($status === 'active' && $user->organization_id === null) {
                $user->forceFill(['organization_id' => $organization->id])->save();

                return;
            }

            if ($status !== 'active' && (int) $user->organization_id === (int) $organization->id) {
                $nextOrganizationId = $user->activeOrganizations()
                    ->where('organizations.id', '!=', $organization->id)
                    ->value('organizations.id');

                $user->forceFill(['organization_id' => $nextOrganizationId])->save();
            }
        });
    }
}
