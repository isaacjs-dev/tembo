<?php

namespace App\Services;

use App\Models\CourtesyBenefit;
use App\Models\CourtesyGrant;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EntitlementService
{
    public function effectivePlan(
        User $user,
        ?Organization $organization = null,
        ?CarbonInterface $at = null,
    ): ?Plan {
        $organization ??= $user->organization;
        if ($organization && ! $user->canUseOrganizationContext((int) $organization->id)) {
            return null;
        }

        $subscription = $organization
            ? ($organization->isPersonalWorkspace()
                ? $this->effectiveUserSubscription($user, $at) ?? $this->effectiveOrganizationSubscription($organization, $at)
                : $this->effectiveOrganizationSubscription($organization, $at))
            : $this->effectiveUserSubscription($user, $at);

        $basePlan = $subscription?->plan ?? $this->freePlan($user, $organization);
        $courtesyPlans = $this->benefitsFor($user, $organization, $at)
            ->where('benefit_type', 'plan')->pluck('plan')->filter();

        return collect([$basePlan])->merge($courtesyPlans)->filter()
            ->sortByDesc(fn (Plan $plan): array => [(int) $plan->tier_level, (int) $plan->id])
            ->first();
    }

    public function planForOrganization(Organization $organization, ?CarbonInterface $at = null): ?Plan
    {
        return $this->effectiveOrganizationSubscription($organization, $at)?->plan
            ?? $this->freePlan(null, $organization);
    }

    public function effectiveSubscription(
        User $user,
        ?Organization $organization = null,
        ?CarbonInterface $at = null,
    ): ?Subscription {
        $organization ??= $user->organization;
        if ($organization && ! $user->canUseOrganizationContext((int) $organization->id)) {
            return null;
        }

        if ($organization && ! $organization->isPersonalWorkspace()) {
            return $this->effectiveOrganizationSubscription($organization, $at);
        }

        return $this->effectiveUserSubscription($user, $at)
            ?? ($organization ? $this->effectiveOrganizationSubscription($organization, $at) : null);
    }

    public function effectiveOrganizationSubscription(
        Organization $organization,
        ?CarbonInterface $at = null,
    ): ?Subscription {
        return $this->effectiveQuery(
            Subscription::query()->where(function (Builder $query) use ($organization): void {
                $query->where(function (Builder $morph) use ($organization): void {
                    $morph->where('subscriber_type', Organization::class)
                        ->where('subscriber_id', $organization->id);
                })->orWhere(function (Builder $legacy) use ($organization): void {
                    $legacy->where('organization_id', $organization->id)
                        ->whereNull('subscriber_type')->whereNull('subscriber_id');
                });
            }),
            $at,
        )->with('plan')->first();
    }

    public function monthlyAllowance(
        User $user,
        string $resourceKey,
        ?Organization $organization = null,
        ?CarbonInterface $at = null,
    ): ?int {
        $organization ??= $user->organization;
        if ($organization && ! $user->canUseOrganizationContext((int) $organization->id)) {
            return 0;
        }

        $plan = $this->effectivePlan($user, $organization, $at);
        $base = $plan?->getLimit($resourceKey);
        $benefits = $this->benefitsFor($user, $organization, $at)->where('resource_key', $resourceKey);

        if ($benefits->contains('benefit_type', 'unlimited')) {
            return null;
        }

        $replacement = $benefits->where('benefit_type', 'replace')->max('quantity');
        $additional = (int) $benefits->where('benefit_type', 'credit')->sum('quantity');

        // Compatibility for installations created before the plan catalog was
        // seeded. Once Start/Free exists, it is the mandatory fallback.
        if (! $plan) {
            return null;
        }

        if ($plan && $base === null && $replacement === null) {
            return null;
        }

        return max((int) ($base ?? 0), (int) ($replacement ?? 0)) + $additional;
    }

    public function hasFeature(
        User $user,
        string $featureKey,
        ?Organization $organization = null,
        ?CarbonInterface $at = null,
    ): bool {
        if ($this->effectivePlan($user, $organization, $at)?->hasFeature($featureKey)) {
            return true;
        }

        return $this->benefitsFor($user, $organization, $at)
            ->where('benefit_type', 'feature')
            ->contains('feature_key', $featureKey);
    }

    /** @return Collection<int, CourtesyBenefit> */
    public function benefitsFor(
        User $user,
        ?Organization $organization = null,
        ?CarbonInterface $at = null,
    ): Collection {
        $organization ??= $user->organization;
        if ($organization && ! $user->canUseOrganizationContext((int) $organization->id)) {
            return collect();
        }

        $at ??= now();
        $contextRole = $organization
            ? ($user->roleInOrganization((int) $organization->id) ?? $user->type)
            : $user->type;
        $grants = CourtesyGrant::query()
            ->whereIn('status', ['scheduled', 'active'])
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>=', $at)
            ->where(function (Builder $query) use ($user, $organization, $contextRole): void {
                $query->where('target_scope', 'all')
                    ->orWhere(fn (Builder $target) => $target->where('target_scope', 'user')->where('target_id', $user->id))
                    ->orWhere(fn (Builder $target) => $target->where('target_scope', 'role')->where('target_role', $contextRole));

                if ($organization) {
                    $query->orWhere(fn (Builder $target) => $target
                        ->where('target_scope', 'organization')->where('target_id', $organization->id));
                }
            })
            ->with('benefits.plan')
            ->get()
            ->filter(function (CourtesyGrant $grant) use ($contextRole): bool {
                $eligibleRoles = $grant->metadata['eligible_roles'] ?? [];

                return $eligibleRoles === [] || in_array($contextRole, $eligibleRoles, true);
            });

        return $grants->flatMap->benefits->values();
    }

    private function effectiveUserSubscription(User $user, ?CarbonInterface $at = null): ?Subscription
    {
        return $this->effectiveQuery(
            Subscription::query()
                ->where('subscriber_type', User::class)
                ->where('subscriber_id', $user->id),
            $at,
        )->with('plan')->first();
    }

    private function effectiveQuery(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where(fn (Builder $window) => $window->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(function (Builder $state) use ($at): void {
                $state->where(function (Builder $current) use ($at): void {
                    $current->whereIn('status', ['active', 'scheduled'])
                        ->where(fn (Builder $expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>=', $at));
                })->orWhere(function (Builder $grace) use ($at): void {
                    $grace->whereIn('status', ['canceled', 'past_due'])
                        ->whereNotNull('grace_ends_at')->where('grace_ends_at', '>=', $at);
                });
            })
            ->orderByDesc('starts_at')
            ->orderByDesc('id');
    }

    private function freePlan(?User $user, ?Organization $organization): ?Plan
    {
        $audience = $organization && ! $organization->isPersonalWorkspace()
            ? 'institution'
            : (($user?->type === 'student') ? 'student' : 'teacher');

        return Plan::query()->where('status', 'active')->whereIn('slug', ['free', 'start'])
            ->where(function (Builder $query) use ($audience): void {
                $query->whereNull('target_audience')->orWhereIn('target_audience', [$audience, 'both']);
            })->orderBy('tier_level')->orderBy('id')->first();
    }
}
