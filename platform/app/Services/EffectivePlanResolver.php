<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;

/** Compatibility adapter. New code should use EntitlementService directly. */
class EffectivePlanResolver
{
    public function __construct(private readonly ?EntitlementService $entitlements = null) {}

    public function resolve(User $user, ?Organization $organization = null): ?Plan
    {
        $entitlements = $this->entitlements ?? app(EntitlementService::class);
        $plan = $entitlements->effectivePlan($user, $organization);
        if ($plan || $organization || $user->organization) {
            return $plan;
        }

        return $user->activeOrganizations()->get()
            ->map(fn (Organization $workspace) => $entitlements->planForOrganization($workspace))
            ->filter()->sortByDesc(fn (Plan $candidate): array => [(int) $candidate->tier_level, (int) $candidate->id])
            ->first();
    }

    public function resolveLimit(User $user, string $resourceKey, ?Organization $organization = null): ?int
    {
        if (! $organization && ! $user->organization) {
            return $this->resolve($user)?->getLimit($resourceKey);
        }

        return ($this->entitlements ?? app(EntitlementService::class))
            ->monthlyAllowance($user, $resourceKey, $organization);
    }

    public function hasFeature(User $user, string $featureKey, ?Organization $organization = null): bool
    {
        if (! $organization && ! $user->organization) {
            return $this->resolve($user)?->hasFeature($featureKey) ?? false;
        }

        return ($this->entitlements ?? app(EntitlementService::class))
            ->hasFeature($user, $featureKey, $organization);
    }

    public function info(User $user, ?Organization $organization = null): array
    {
        $plan = $this->resolve($user, $organization);

        return [
            'effective_plan' => $plan,
            'is_individual' => $organization === null || $organization->isPersonalWorkspace(),
            'is_institutional' => $organization !== null && ! $organization->isPersonalWorkspace(),
            'active_plans_count' => $plan ? 1 : 0,
            'tier_level' => $plan?->tier_level ?? 0,
        ];
    }
}
