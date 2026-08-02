<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;

class PlanLimiterService
{
    /**
     * Verifica se o recurso pode ser criado dado o limite do plano.
     *
     * @param  Organization|User  $subscriber  Entidade assinante
     * @param  string  $resourceKey  Ex: max_students, max_classes, max_exams
     * @param  int  $currentCount  Quantidade atual do recurso
     */
    public function canCreate($subscriber, string $resourceKey, int $currentCount): bool
    {
        $plan = $this->getActivePlan($subscriber);

        if (! $plan) {
            return false; // Sem plano ativo
        }

        $limit = $plan->getLimit($resourceKey);

        if ($limit === null) {
            return true; // Ilimitado
        }

        return $currentCount < $limit;
    }

    /**
     * Retorna a quantidade restante de um recurso.
     * null = ilimitado.
     */
    public function remaining($subscriber, string $resourceKey, int $currentCount): ?int
    {
        $plan = $this->getActivePlan($subscriber);

        if (! $plan) {
            return 0;
        }

        $limit = $plan->getLimit($resourceKey);

        if ($limit === null) {
            return null; // Ilimitado
        }

        return max(0, $limit - $currentCount);
    }

    /**
     * Retorna o plano ativo do subscriber (Organization ou User).
     */
    private function getActivePlan($subscriber): ?Plan
    {
        if ($subscriber instanceof Organization) {
            return $subscriber->subscription?->plan;
        }

        if ($subscriber instanceof User) {
            // Tenta plano individual primeiro
            $sub = $subscriber->subscription ?? null;
            if ($sub?->plan) {
                return $sub->plan;
            }

            // Fallback para plano da organização
            $org = $subscriber->organization;
            if ($org) {
                return $org->subscription?->plan;
            }
        }

        return null;
    }
}
