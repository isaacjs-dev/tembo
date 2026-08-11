<?php

namespace App\Models\Traits;

use App\Models\Organization;
use App\Services\EntitlementService;
use App\Services\PlanLimiterService;

/**
 * Trait HasPlanLimits
 *
 * Adiciona métodos de verificação de quota de plano a qualquer Model
 * que tenha acesso a uma Organization (via relationship ou direto).
 *
 * Requer que o Model implemente getOrganizationForPlan(): ?Organization
 */
trait HasPlanLimits
{
    /**
     * Retorna a Organization associada para resolução do plano.
     * Override no model concreto se necessário.
     */
    public function getOrganizationForPlan(): ?Organization
    {
        // Default: tenta $this->organization ou $this se for Organization
        if ($this instanceof Organization) {
            return $this;
        }

        return $this->organization ?? null;
    }

    /**
     * Verifica se pode criar mais um recurso do tipo informado.
     */
    public function canCreateResource(string $resourceKey, int $currentCount): bool
    {
        $org = $this->getOrganizationForPlan();
        if (! $org) {
            return false;
        }

        return app(PlanLimiterService::class)->canCreate($org, $resourceKey, $currentCount);
    }

    /**
     * Retorna a quantidade restante de um recurso.
     * null = ilimitado.
     */
    public function remainingResource(string $resourceKey, int $currentCount): ?int
    {
        $org = $this->getOrganizationForPlan();
        if (! $org) {
            return 0;
        }

        return app(PlanLimiterService::class)->remaining($org, $resourceKey, $currentCount);
    }

    /**
     * Verifica se o plano ativo da organização tem a feature habilitada.
     */
    public function planHasFeature(string $featureKey): bool
    {
        $org = $this->getOrganizationForPlan();
        if (! $org) {
            return false;
        }

        $plan = app(EntitlementService::class)->planForOrganization($org);
        if (! $plan) {
            return false;
        }

        return $plan->hasFeature($featureKey);
    }

    /**
     * Retorna array com uso/limite/remaining para exibição em UI.
     */
    public function quotaInfo(string $resourceKey, int $currentCount): array
    {
        $org = $this->getOrganizationForPlan();
        $plan = $org ? app(EntitlementService::class)->planForOrganization($org) : null;
        $limit = $plan?->getLimit($resourceKey);

        return [
            'current' => $currentCount,
            'limit' => $limit,           // null = ilimitado
            'remaining' => $limit !== null ? max(0, $limit - $currentCount) : null,
            'unlimited' => $limit === null,
            'percent' => $limit !== null && $limit > 0
                ? round(($currentCount / $limit) * 100)
                : 0,
        ];
    }
}
