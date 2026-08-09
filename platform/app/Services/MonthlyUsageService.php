<?php

namespace App\Services;

use App\Exceptions\QuotaExceededException;
use App\Models\UsageEvent;
use App\Models\UsagePeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MonthlyUsageService
{
    public const OMR_SCANS = 'monthly_omr_scans';

    public const EXAM_PUBLICATIONS = 'monthly_exam_publications';

    public const QUESTIONS_CREATED = 'monthly_questions_created';

    public const RESOURCES = [self::OMR_SCANS, self::EXAM_PUBLICATIONS, self::QUESTIONS_CREATED];

    public function __construct(private readonly EntitlementService $entitlements) {}

    /** @return array{limit:?int,consumed:int,remaining:?int,period_start:string,period_end:string} */
    public function snapshot(User $user, string $resourceKey): array
    {
        $this->assertResource($resourceKey);
        $period = $this->ensurePeriod($user, $resourceKey);
        $limit = $this->entitlements->monthlyAllowance($user, $resourceKey);

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
    ): array {
        $this->assertResource($resourceKey);
        if ($amount < 1) {
            throw new \InvalidArgumentException('O consumo precisa ser maior que zero.');
        }

        DB::transaction(function () use ($user, $resourceKey, $amount, $idempotencyKey, $context, $actor, $metadata): void {
            if (UsageEvent::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                return;
            }

            $period = $this->ensurePeriod($user, $resourceKey);
            $period = UsagePeriod::query()->lockForUpdate()->findOrFail($period->id);
            $limit = $this->entitlements->monthlyAllowance($user, $resourceKey);
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
                'organization_id' => $user->organization_id,
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

        return $this->snapshot($user->fresh(), $resourceKey);
    }

    public function reset(User $user, string $resourceKey, User $actor, string $reason, string $operationKey): void
    {
        $this->assertResource($resourceKey);

        DB::transaction(function () use ($user, $resourceKey, $actor, $reason, $operationKey): void {
            if (UsageEvent::query()->where('idempotency_key', $operationKey)->exists()) {
                return;
            }

            $period = UsagePeriod::query()->lockForUpdate()->findOrFail(
                $this->ensurePeriod($user, $resourceKey)->id
            );
            $previous = (int) $period->consumed;
            $period->update(['consumed' => 0, 'manual_resets' => (int) $period->manual_resets + 1]);

            UsageEvent::query()->create([
                'usage_period_id' => $period->id,
                'user_id' => $user->id,
                'organization_id' => $user->organization_id,
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

    public function ensurePeriod(User $user, string $resourceKey, ?CarbonImmutable $at = null): UsagePeriod
    {
        $this->assertResource($resourceKey);
        $at ??= CarbonImmutable::now(config('app.timezone'));
        $start = $at->startOfMonth();
        $end = $at->endOfMonth();

        return UsagePeriod::query()->firstOrCreate(
            ['user_id' => $user->id, 'resource_key' => $resourceKey, 'period_start' => $start],
            [
                'organization_id' => $user->organization_id,
                'period_end' => $end,
                'allowance' => $this->entitlements->monthlyAllowance($user, $resourceKey),
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
}
