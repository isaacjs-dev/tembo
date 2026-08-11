<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;

class PlanLimiterService
{
    public function __construct(private readonly ?EntitlementService $entitlements = null) {}

    public function canCreate(Organization|User $subscriber, string $resourceKey, int $currentCount): bool
    {
        $plan = $this->plan($subscriber);
        if (! $plan) {
            return false;
        }
        $limit = $plan->getLimit($resourceKey);

        return $limit === null || $currentCount < $limit;
    }

    public function remaining(Organization|User $subscriber, string $resourceKey, int $currentCount): ?int
    {
        $plan = $this->plan($subscriber);
        if (! $plan) {
            return 0;
        }
        $limit = $plan->getLimit($resourceKey);

        return $limit === null ? null : max(0, $limit - $currentCount);
    }

    private function plan(Organization|User $subscriber): ?Plan
    {
        $entitlements = $this->entitlements ?? app(EntitlementService::class);

        return $subscriber instanceof Organization
            ? $entitlements->planForOrganization($subscriber)
            : $entitlements->effectivePlan($subscriber);
    }
}
