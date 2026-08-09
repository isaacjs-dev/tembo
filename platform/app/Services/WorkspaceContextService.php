<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class WorkspaceContextService
{
    /** @return Collection<int, Organization> */
    public function availableFor(User $user): Collection
    {
        if ($user->type === 'global_admin') {
            return Organization::query()->where('active', true)->orderBy('name')->get();
        }

        $workspaces = $user->activeOrganizations()
            ->where('organizations.active', true)
            ->orderBy('organizations.name')
            ->get();

        // Compatibility for accounts created before memberships existed.
        if ($workspaces->isEmpty() && $user->organization_id && ! $user->organizations()->exists()) {
            $legacy = Organization::query()
                ->whereKey($user->organization_id)
                ->where('active', true)
                ->first();
            if ($legacy) {
                $workspaces->push($legacy);
            }
        }

        return $workspaces->unique('id')->values();
    }

    public function resolve(Request $request, User $user): ?Organization
    {
        $headerId = $request->headers->get('X-Workspace-Id');
        if ($headerId !== null) {
            $workspaceId = filter_var($headerId, FILTER_VALIDATE_INT);

            return $workspaceId !== false && $user->canUseOrganizationContext((int) $workspaceId)
                ? Organization::query()->whereKey((int) $workspaceId)->where('active', true)->first()
                : null;
        }

        $requestedId = $request->hasSession()
            ? $request->session()->get('workspace_id')
            : null;
        $candidateIds = array_values(array_unique(array_filter([
            filter_var($requestedId, FILTER_VALIDATE_INT) !== false ? (int) $requestedId : null,
            $user->organization_id ? (int) $user->organization_id : null,
        ])));

        foreach ($candidateIds as $candidateId) {
            if ($user->canUseOrganizationContext($candidateId)) {
                return Organization::query()->whereKey($candidateId)->where('active', true)->first();
            }
        }

        $available = $this->availableFor($user);

        return $available->count() === 1 ? $available->first() : null;
    }

    public function roleFor(User $user, ?int $organizationId = null): ?string
    {
        return $user->roleInOrganization($organizationId);
    }
}
