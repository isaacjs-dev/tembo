<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\PublicCatalogRewardAward;
use App\Models\PublicCatalogRewardRule;
use App\Models\PublicCatalogSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PublicCatalogRewardService
{
    public function __construct(
        private readonly MonthlyUsageService $usage,
        private readonly PublicCatalogRewardScheduleService $schedule,
    ) {}

    public function grantForApproval(
        PublicCatalogSubmission $submission,
        User $moderator,
    ): ?PublicCatalogRewardAward {
        $existing = PublicCatalogRewardAward::query()
            ->where('public_catalog_submission_id', $submission->id)->first();
        if ($existing) {
            return $existing;
        }

        $kind = data_get($submission->snapshot_json, 'kind');
        if (! in_array($kind, PublicCatalogService::TARGET_TYPES, true)) {
            return null;
        }

        $now = now();
        $award = DB::transaction(function () use ($submission, $moderator, $kind, $now): ?PublicCatalogRewardAward {
            $retry = PublicCatalogRewardAward::query()
                ->where('public_catalog_submission_id', $submission->id)->first();
            if ($retry) {
                return $retry;
            }

            $slot = $this->schedule->lockAndNormalize($kind, $now);
            $lockedRule = $slot->active_rule_id
                ? PublicCatalogRewardRule::query()->lockForUpdate()->find($slot->active_rule_id)
                : null;
            if (! $lockedRule) {
                return null;
            }
            if ($lockedRule->status !== 'active'
                || ($lockedRule->starts_at && $lockedRule->starts_at->isAfter($now))
                || ($lockedRule->ends_at && $lockedRule->ends_at->isBefore($now))) {
                return null;
            }

            $user = User::query()->lockForUpdate()->findOrFail($submission->submitter_id);
            $organization = $submission->organization_id
                ? Organization::query()->find($submission->organization_id)
                : null;
            $membershipId = $organization
                ? DB::table('user_organization')->where('user_id', $user->id)
                    ->where('organization_id', $organization->id)->where('status', 'active')->value('id')
                : null;
            $scopeKey = $organization ? 'organization:'.$organization->id : 'user:'.$user->id;
            $periodStart = CarbonImmutable::parse($now)->startOfMonth()->toDateString();
            $idempotencyKey = "public-approval:{$submission->id}:{$lockedRule->rule_version}";

            if ($organization && ! $membershipId && ! $user->canUseOrganizationContext((int) $organization->id)) {
                return PublicCatalogRewardAward::query()->create([
                    'public_catalog_reward_rule_id' => $lockedRule->id,
                    'public_catalog_submission_id' => $submission->id,
                    'user_id' => $user->id,
                    'organization_id' => $organization->id,
                    'scope_key' => $scopeKey,
                    'resource_key' => $lockedRule->resource_key,
                    'rule_version' => $lockedRule->rule_version,
                    'requested_amount' => $lockedRule->credit_amount,
                    'awarded_amount' => 0,
                    'status' => 'ineligible',
                    'idempotency_key' => $idempotencyKey,
                    'period_start' => $periodStart,
                    'metadata' => ['reason' => 'inactive_membership'],
                ]);
            }

            $userAwarded = (int) PublicCatalogRewardAward::query()
                ->whereHas('rule', fn ($query) => $query->where('subject_kind', $lockedRule->subject_kind))
                ->where('user_id', $user->id)->where('scope_key', $scopeKey)
                ->where('resource_key', $lockedRule->resource_key)->whereDate('period_start', $periodStart)
                ->sum('awarded_amount');
            $globalAwarded = (int) PublicCatalogRewardAward::query()
                ->whereHas('rule', fn ($query) => $query->where('subject_kind', $lockedRule->subject_kind))
                ->where('resource_key', $lockedRule->resource_key)->whereDate('period_start', $periodStart)
                ->sum('awarded_amount');

            $amount = min(
                (int) $lockedRule->credit_amount,
                max(0, (int) $lockedRule->per_user_monthly_cap - $userAwarded),
                $lockedRule->global_monthly_cap === null
                    ? PHP_INT_MAX
                    : max(0, (int) $lockedRule->global_monthly_cap - $globalAwarded),
            );
            $status = $amount === 0 ? 'capped' : ($amount < (int) $lockedRule->credit_amount ? 'partial' : 'granted');

            $award = PublicCatalogRewardAward::query()->create([
                'public_catalog_reward_rule_id' => $lockedRule->id,
                'public_catalog_submission_id' => $submission->id,
                'user_id' => $user->id,
                'organization_id' => $organization?->id,
                'membership_id' => $membershipId,
                'scope_key' => $scopeKey,
                'resource_key' => $lockedRule->resource_key,
                'rule_version' => $lockedRule->rule_version,
                'requested_amount' => $lockedRule->credit_amount,
                'awarded_amount' => $amount,
                'status' => $status,
                'idempotency_key' => $idempotencyKey,
                'period_start' => $periodStart,
                'metadata' => [
                    'per_user_monthly_cap' => $lockedRule->per_user_monthly_cap,
                    'global_monthly_cap' => $lockedRule->global_monthly_cap,
                ],
                'awarded_at' => $amount > 0 ? $now : null,
            ]);

            if ($amount > 0) {
                $event = $this->usage->credit(
                    $user, $lockedRule->resource_key, $amount, $idempotencyKey,
                    $submission, $moderator,
                    ['reward_rule_id' => $lockedRule->id, 'rule_version' => $lockedRule->rule_version],
                    $organization,
                );
                $award->update(['usage_event_id' => $event->id]);
            }

            return $award->fresh();
        }, 3);

        if (! $award) {
            return null;
        }

        AuditLog::log('public_catalog_reward_processed', PublicCatalogRewardAward::class, $award->id, [
            'organization_id' => $award->organization_id,
            'submission_id' => $award->public_catalog_submission_id,
            'rule_version' => $award->rule_version,
            'resource_key' => $award->resource_key,
            'awarded_amount' => $award->awarded_amount,
            'status' => $award->status,
        ]);

        return $award;
    }
}
