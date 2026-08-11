<?php

namespace App\Services;

use App\Exceptions\QuotaExceededException;
use App\Models\Organization;
use App\Models\UsageEvent;
use App\Models\UsagePeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MonthlyUsageService
{
    public const OMR_SCANS = 'monthly_omr_scans';

    public const EXAM_PUBLICATIONS = 'monthly_exam_publications';

    public const QUESTIONS_CREATED = 'monthly_questions_created';

    public const RESOURCES = [self::OMR_SCANS, self::EXAM_PUBLICATIONS, self::QUESTIONS_CREATED];

    public function __construct(private readonly EntitlementService $entitlements) {}

    /** @return array{limit:?int,consumed:int,remaining:?int,period_start:string,period_end:string} */
    public function snapshot(User $user, string $resourceKey, ?Organization $organization = null): array
    {
        $this->assertResource($resourceKey);
        $organization ??= $user->organization;
        $period = $this->ensurePeriod($user, $resourceKey, null, $organization);
        $limit = $this->entitlements->monthlyAllowance($user, $resourceKey, $organization);

        if ($period->allowance !== $limit) {
            $period->update(['allowance' => $limit]);
        }

        return [
            'limit' => $limit,
            'consumed' => (int) $period->consumed,
            'remaining' => $limit === null ? null : max(0, $limit - (int) $period->consumed),
            'period_start' => $period->period_start->toDateString(),
            'period_end' => $period->period_end->toDateString(),
        ];
    }

    /** @return array{limit:?int,consumed:int,remaining:?int,period_start:string,period_end:string} */
    public function consume(
        User $user,
        string $resourceKey,
        int $amount,
        string $idempotencyKey,
        ?Model $context = null,
        ?User $actor = null,
        array $metadata = [],
        ?Organization $organization = null,
    ): array {
        $this->assertResource($resourceKey);
        if ($amount < 1) {
            throw new \InvalidArgumentException('O consumo precisa ser maior que zero.');
        }

        $organization ??= $user->organization;
        DB::transaction(function () use ($user, $resourceKey, $amount, $idempotencyKey, $context, $actor, $metadata, $organization): void {
            if ($this->isReplayOrFail($idempotencyKey, $user, $resourceKey, $amount, $organization)) {
                return;
            }

            $period = $this->ensurePeriod($user, $resourceKey, null, $organization);
            $period = UsagePeriod::query()->lockForUpdate()->findOrFail($period->id);
            if ($this->isReplayOrFail($idempotencyKey, $user, $resourceKey, $amount, $organization)) {
                return;
            }
            $limit = $this->entitlements->monthlyAllowance($user, $resourceKey, $organization);
            $remaining = $limit === null ? null : max(0, $limit - (int) $period->consumed);

            if ($remaining !== null && $remaining < $amount) {
                throw new QuotaExceededException($resourceKey, $amount, $remaining);
            }

            $period->update([
                'allowance' => $limit,
                'consumed' => (int) $period->consumed + $amount,
            ]);

            UsageEvent::query()->create([
                'usage_period_id' => $period->id,
                'user_id' => $user->id,
                'organization_id' => $organization?->id,
                'scope_key' => $period->scope_key,
                'membership_id' => $period->membership_id,
                'actor_id' => $actor?->id,
                'resource_key' => $resourceKey,
                'event_type' => 'consume',
                'amount' => $amount,
                'idempotency_key' => $idempotencyKey,
                'context_type' => $context ? $context::class : null,
                'context_id' => $context?->getKey(),
                'metadata' => $metadata,
                'occurred_at' => now(),
            ]);
        });

        return $this->snapshot($user->fresh(), $resourceKey, $organization);
    }

    public function reset(
        User $user,
        string $resourceKey,
        User $actor,
        string $reason,
        string $operationKey,
        ?Organization $organization = null,
    ): void {
        $this->assertResource($resourceKey);
        $organization ??= $user->organization;

        DB::transaction(function () use ($user, $resourceKey, $actor, $reason, $operationKey, $organization): void {
            if ($this->isResetReplayOrFail($operationKey, $user, $resourceKey, $organization)) {
                return;
            }

            $period = UsagePeriod::query()->lockForUpdate()->findOrFail(
                $this->ensurePeriod($user, $resourceKey, null, $organization)->id
            );
            if ($this->isResetReplayOrFail($operationKey, $user, $resourceKey, $organization)) {
                return;
            }
            $previous = (int) $period->consumed;
            $period->update(['consumed' => 0, 'manual_resets' => (int) $period->manual_resets + 1]);

            UsageEvent::query()->create([
                'usage_period_id' => $period->id,
                'user_id' => $user->id,
                'organization_id' => $organization?->id,
                'scope_key' => $period->scope_key,
                'membership_id' => $period->membership_id,
                'actor_id' => $actor->id,
                'resource_key' => $resourceKey,
                'event_type' => 'reset',
                'amount' => -$previous,
                'idempotency_key' => $operationKey,
                'metadata' => ['reason' => $reason, 'previous_consumed' => $previous],
                'occurred_at' => now(),
            ]);
        });
    }

    public function ensurePeriod(
        User $user,
        string $resourceKey,
        ?CarbonImmutable $at = null,
        ?Organization $organization = null,
    ): UsagePeriod {
        $this->assertResource($resourceKey);
        $organization ??= $user->organization;
        if ($organization && ! $user->canUseOrganizationContext((int) $organization->id)) {
            abort(403, 'Workspace sem membership ativa para consumo.');
        }
        $at ??= CarbonImmutable::now(config('app.timezone'));
        $start = $at->startOfMonth();
        $end = $at->endOfMonth();
        $scopeKey = $organization ? 'organization:'.$organization->id : 'user:'.$user->id;
        $membershipId = $organization
            ? DB::table('user_organization')->where('user_id', $user->id)
                ->where('organization_id', $organization->id)->where('status', 'active')->value('id')
            : null;

        return UsagePeriod::query()->firstOrCreate(
            [
                'user_id' => $user->id, 'scope_key' => $scopeKey,
                'resource_key' => $resourceKey, 'period_start' => $start,
            ],
            [
                'organization_id' => $organization?->id,
                'membership_id' => $membershipId,
                'period_end' => $end,
                'allowance' => $this->entitlements->monthlyAllowance($user, $resourceKey, $organization),
                'consumed' => 0,
            ],
        );
    }

    private function assertResource(string $resourceKey): void
    {
        if (! in_array($resourceKey, self::RESOURCES, true)) {
            throw new \InvalidArgumentException("Recurso mensal desconhecido: {$resourceKey}");
        }
    }

    private function isReplayOrFail(
        string $idempotencyKey,
        User $user,
        string $resourceKey,
        int $amount,
        ?Organization $organization,
    ): bool {
        $existing = UsageEvent::query()->where('idempotency_key', $idempotencyKey)->first();
        if (! $existing) {
            return false;
        }

        $scopeKey = $organization ? 'organization:'.$organization->id : 'user:'.$user->id;
        if ((int) $existing->user_id !== (int) $user->id
            || $existing->scope_key !== $scopeKey
            || $existing->resource_key !== $resourceKey
            || $existing->event_type !== 'consume'
            || (int) $existing->amount !== $amount) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'A chave de idempotência já pertence a outra operação de consumo.',
            ]);
        }

        return true;
    }

    private function isResetReplayOrFail(
        string $idempotencyKey,
        User $user,
        string $resourceKey,
        ?Organization $organization,
    ): bool {
        $existing = UsageEvent::query()->where('idempotency_key', $idempotencyKey)->first();
        if (! $existing) {
            return false;
        }

        $scopeKey = $organization ? 'organization:'.$organization->id : 'user:'.$user->id;
        if ((int) $existing->user_id !== (int) $user->id
            || $existing->scope_key !== $scopeKey
            || $existing->resource_key !== $resourceKey
            || $existing->event_type !== 'reset') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'A chave de idempotência já pertence a outra operação de consumo.',
            ]);
        }

        return true;
    }
}
