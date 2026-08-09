<?php

namespace App\Services;

use App\Models\CourtesyBenefit;
use App\Models\CourtesyGrant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Collection;

class EntitlementService
{
    public function effectivePlan(User $user): ?Plan
    {
        $plans = collect([$this->basePlan($user)]);

        $courtesyPlans = $this->benefitsFor($user)
            ->where('benefit_type', 'plan')
            ->pluck('plan')
            ->filter();

        return $plans->merge($courtesyPlans)
            ->filter()
            ->sortByDesc(fn (Plan $plan): array => [(int) $plan->tier_level, (int) $plan->id])
            ->first();
    }

    public function monthlyAllowance(User $user, string $resourceKey): ?int
    {
        $base = $this->effectivePlan($user)?->getLimit($resourceKey);
        $benefits = $this->benefitsFor($user)->where('resource_key', $resourceKey);

        if ($benefits->contains('benefit_type', 'unlimited')) {
            return null;
        }

        $replacement = $benefits->where('benefit_type', 'replace')->max('quantity');
        $additional = (int) $benefits->where('benefit_type', 'credit')->sum('quantity');

        if ($base === null && $replacement === null) {
            return null;
        }

        return max((int) ($base ?? 0), (int) ($replacement ?? 0)) + $additional;
    }

    public function hasFeature(User $user, string $featureKey): bool
    {
        if ($this->effectivePlan($user)?->hasFeature($featureKey)) {
            return true;
        }

        return $this->benefitsFor($user)
            ->where('benefit_type', 'feature')
            ->contains('feature_key', $featureKey);
    }

    /** @return Collection<int, CourtesyBenefit> */
    public function benefitsFor(User $user): Collection
    {
        $organizationIds = collect([$user->organization_id])
            ->merge($user->activeOrganizations()->pluck('organizations.id'))
            ->filter()->unique()->values();

        $grants = CourtesyGrant::query()
            ->effective()
            ->where(function ($query) use ($user, $organizationIds): void {
                $query->where('target_scope', 'all')
                    ->orWhere(function ($query) use ($user): void {
                        $query->where('target_scope', 'user')->where('target_id', $user->id);
                    })
                    ->orWhere(function ($query) use ($user): void {
                        $query->where('target_scope', 'role')->where('target_role', $user->type);
                    });

                if ($organizationIds->isNotEmpty()) {
                    $query->orWhere(function ($query) use ($organizationIds): void {
                        $query->where('target_scope', 'organization')->whereIn('target_id', $organizationIds);
                    });
                }
            })
            ->with('benefits.plan')
            ->get()
            ->filter(function (CourtesyGrant $grant) use ($user): bool {
                $eligibleRoles = $grant->metadata['eligible_roles'] ?? [];

                return $eligibleRoles === [] || in_array($user->type, $eligibleRoles, true);
            });

        return $grants->flatMap->benefits->values();
    }

    private function basePlan(User $user): ?Plan
    {
        $individual = Subscription::query()
            ->where('subscriber_type', User::class)
            ->where('subscriber_id', $user->id)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->latest('id')->with('plan')->first()?->plan;

        if ($individual) {
            return $individual;
        }

        return $user->organization?->subscription?->plan;
    }
}
