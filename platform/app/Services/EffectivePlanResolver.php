<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Collection;

class EffectivePlanResolver
{
    /**
     * Resolve o plano efetivo de um usuário.
     * Leva em conta: plano individual + planos das instituições ativas.
     * Retorna o plano com maior tier_level.
     *
     * Cache: 10 minutos por usuário.
     */
    public function resolve(User $user): ?Plan
    {
        return cache()->remember(
            "effective_plan_user_{$user->id}",
            now()->addMinutes(10),
            fn () => $this->computeEffectivePlan($user)
        );
    }

    /**
     * Resolve o limite efetivo de um recurso.
     * Retorna o maior limite dentre todos os planos ativos (null = ilimitado).
     */
    public function resolveLimit(User $user, string $resourceKey): ?int
    {
        $plans = $this->getAllActivePlans($user);

        $limits = $plans->map(fn (Plan $p) => $p->getLimit($resourceKey));

        // Se algum plano tem ilimitado (null), retorna null
        if ($limits->contains(null)) {
            return null;
        }

        return $limits->max() ?: 0;
    }

    /**
     * Verifica se qualquer plano ativo do usuário tem a feature.
     */
    public function hasFeature(User $user, string $featureKey): bool
    {
        return $this->getAllActivePlans($user)
            ->contains(fn (Plan $p) => $p->hasFeature($featureKey));
    }

    /**
     * Retorna informações completas do plano efetivo.
     */
    public function info(User $user): array
    {
        $plan = $this->resolve($user);
        $allPlans = $this->getAllActivePlans($user);

        return [
            'effective_plan' => $plan,
            'is_individual' => $this->hasIndividualPlan($user),
            'is_institutional' => $this->hasInstitutionalPlan($user),
            'active_plans_count' => $allPlans->count(),
            'tier_level' => $plan?->tier_level ?? 0,
        ];
    }

    /**
     * Calcula o plano efetivo (sem cache).
     */
    private function computeEffectivePlan(User $user): ?Plan
    {
        return $this->getAllActivePlans($user)
            ->sortByDesc('tier_level')
            ->first();
    }

    /**
     * Coleta todos os planos ativos do usuário (individual + institucionais).
     */
    private function getAllActivePlans(User $user): Collection
    {
        $plans = collect();

        // 1. Plano individual (morph subscription)
        $individualPlan = $user->subscription?->plan;
        if ($individualPlan) {
            $plans->push($individualPlan);
        }

        // 2. Planos das instituições ativas (via pivot user_organization)
        $orgPlans = $user->activeOrganizations()
            ->with('subscription.plan')
            ->get()
            ->pluck('subscription.plan')
            ->filter();

        $plans = $plans->merge($orgPlans);

        // 3. Fallback legado: organização direta (organization_id FK)
        if ($plans->isEmpty() && $user->organization_id) {
            $legacyPlan = $user->organization?->subscription?->plan;
            if ($legacyPlan) {
                $plans->push($legacyPlan);
            }
        }

        return $plans->unique('id');
    }

    private function hasIndividualPlan(User $user): bool
    {
        return $user->subscription?->status === 'active';
    }

    private function hasInstitutionalPlan(User $user): bool
    {
        return $user->activeOrganizations()
            ->whereHas('subscription', fn ($q) => $q->where('status', 'active'))
            ->exists();
    }
}
