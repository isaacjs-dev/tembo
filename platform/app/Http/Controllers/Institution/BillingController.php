<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\SchoolClass;
use App\Models\Subscription;
use App\Models\User;
use App\Services\EntitlementService;
use App\Services\PlanOwnershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function __construct(
        private readonly PlanOwnershipService $planOwnership,
        private readonly EntitlementService $entitlements,
    ) {}

    /**
     * Dashboard de billing: plano atual, uso, status, vencimento.
     */
    public function index()
    {
        $this->authorizePlanOwner();
        $organization = auth()->user()->organization;
        $activeSubscription = $this->entitlements->effectiveOrganizationSubscription($organization);

        $plans = Plan::where('status', 'active')->whereIn('target_audience', ['institution', 'both'])
            ->orderBy('tier_level')
            ->get();

        $usage = $this->getUsage($organization);

        $limits = [];
        if ($activeSubscription) {
            $planLimits = $activeSubscription->plan->planLimits;
            foreach ($planLimits as $limit) {
                $limits[$limit->resource_key] = $limit->limit_value;
            }
        }

        return view('institution.billing.index', compact(
            'organization',
            'activeSubscription',
            'plans',
            'usage',
            'limits'
        ));
    }

    /**
     * Trocar de plano (upgrade/downgrade).
     */
    public function changePlan(Request $request)
    {
        $this->authorizePlanOwner();
        $validated = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('status', 'active')],
        ]);

        $organization = auth()->user()->organization;
        $newPlan = Plan::query()->whereIn('target_audience', ['institution', 'both'])
            ->findOrFail($validated['plan_id']);
        $currentSub = $this->entitlements->effectiveOrganizationSubscription($organization);

        // Validar downgrade: verificar se uso atual não excede limites do novo plano
        $usage = $this->getUsage($organization);
        $newLimits = $newPlan->planLimits->pluck('limit_value', 'resource_key')->toArray();

        $violations = [];
        if (($usage['teachers'] ?? 0) > ($newLimits['max_teachers'] ?? PHP_INT_MAX)) {
            $violations[] = "Professores: {$usage['teachers']} (limite: {$newLimits['max_teachers']})";
        }
        if (($usage['students'] ?? 0) > ($newLimits['max_students'] ?? PHP_INT_MAX)) {
            $violations[] = "Alunos: {$usage['students']} (limite: {$newLimits['max_students']})";
        }
        if (($usage['classes'] ?? 0) > ($newLimits['max_classes'] ?? PHP_INT_MAX)) {
            $violations[] = "Turmas: {$usage['classes']} (limite: {$newLimits['max_classes']})";
        }

        if (! empty($violations)) {
            return back()->withErrors([
                'plan' => 'Não é possível fazer downgrade. Uso atual excede os limites do plano selecionado: '.implode(', ', $violations),
            ]);
        }

        $scheduledDowngrade = false;
        DB::transaction(function () use ($organization, $newPlan, $currentSub, &$scheduledDowngrade) {
            $organization->subscriptions()->where('status', 'scheduled')->update(['status' => 'canceled', 'cancelled_at' => now()]);
            if ($currentSub && $currentSub->status === 'active'
                && (int) $newPlan->tier_level < (int) $currentSub->plan?->tier_level
                && $currentSub->expires_at?->isFuture()) {
                Subscription::create([
                    'organization_id' => $organization->id,
                    'subscriber_type' => $organization::class,
                    'subscriber_id' => $organization->id,
                    'plan_id' => $newPlan->id,
                    'status' => 'scheduled',
                    'starts_at' => $currentSub->expires_at,
                ]);
                $scheduledDowngrade = true;
                AuditLog::log('plan_downgrade_scheduled', Subscription::class, $currentSub->id, [
                    'organization_id' => $organization->id,
                    'before' => ['plan_id' => $currentSub->plan_id, 'expires_at' => $currentSub->expires_at?->toISOString()],
                    'after' => ['plan_id' => $newPlan->id, 'starts_at' => $currentSub->expires_at?->toISOString()],
                ]);

                return;
            }

            $extraDays = 0;
            $newPlanPrice = floatval($newPlan->effective_price);

            // Cancelar assinatura atual e verificar saldo (Prorata)
            if ($currentSub) {
                if ($currentSub->plan && $currentSub->expires_at && $currentSub->expires_at->isFuture()) {
                    $daysRemaining = max(0, now()->diffInDays($currentSub->expires_at));

                    if ($daysRemaining > 0) {
                        $oldPlanPrice = floatval($currentSub->plan->effective_price);

                        // Assumimos que o custo do plano é baseado em ciclos mensais (30 dias)
                        $oldDailyRate = $oldPlanPrice / 30;
                        $creditBalance = $daysRemaining * $oldDailyRate;

                        $newDailyRate = $newPlanPrice > 0 ? $newPlanPrice / 30 : 0;

                        if ($newDailyRate > 0) {
                            $extraDays = (int) floor($creditBalance / $newDailyRate);
                        }
                    }

                }

                $currentSub->update([
                    'status' => 'superseded',
                    'expires_at' => now(),
                    'grace_ends_at' => null,
                ]);
            }

            // Data de vencimento: se gerou saldo de dias significativos (> 0), usa eles.
            // Senão, dá-se 30 dias na premissa de um novo contrato mensal.
            $expiresAt = $extraDays > 0 ? now()->addDays($extraDays) : now()->addDays(30);

            // Criar nova assinatura
            Subscription::create([
                'organization_id' => $organization->id,
                'subscriber_type' => $organization::class,
                'subscriber_id' => $organization->id,
                'plan_id' => $newPlan->id,
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            AuditLog::log('plan_changed', Subscription::class, null, [
                'organization_id' => $organization->id,
                'prorata_extra_days' => $extraDays,
                'before' => [
                    'subscription_id' => $currentSub?->id,
                    'plan_id' => $currentSub?->plan_id,
                    'status' => $currentSub ? 'active' : null,
                ],
                'after' => [
                    'plan_id' => $newPlan->id,
                    'status' => 'active',
                    'expires_at' => $expiresAt->toISOString(),
                ],
            ]);
        });

        return redirect()->route('institution.billing.index')->with(
            'status',
            $scheduledDowngrade
                ? "Downgrade para {$newPlan->name} agendado para o fim da vigência atual."
                : "Plano alterado para {$newPlan->name} com sucesso!",
        );
    }

    /**
     * Cancelar plano (requer confirmação por nome da org).
     */
    public function cancelPlan(Request $request)
    {
        $this->authorizePlanOwner();
        $organization = auth()->user()->organization;

        $validated = $request->validate([
            'confirmation' => 'required|string',
        ]);

        if ($validated['confirmation'] !== $organization->name) {
            return back()->withErrors([
                'confirmation' => 'Digite o nome da instituição corretamente para confirmar o cancelamento.',
            ]);
        }

        $currentSub = $this->entitlements->effectiveOrganizationSubscription($organization);

        if (! $currentSub) {
            return back()->withErrors(['Nenhuma assinatura ativa para cancelar.']);
        }
        if ($currentSub->status !== 'active') {
            return back()->withErrors(['Somente uma assinatura ativa pode ser cancelada.']);
        }

        $graceUntil = now()->addDays(30);

        DB::transaction(function () use ($currentSub, $organization, $graceUntil) {
            $organization->subscriptions()->where('status', 'scheduled')->update([
                'status' => 'canceled', 'cancelled_at' => now(), 'grace_ends_at' => null,
            ]);
            $currentSub->update([
                'status' => 'canceled',
                'cancelled_at' => now(),
                'expires_at' => $graceUntil,
                'grace_ends_at' => $graceUntil,
            ]);

            AuditLog::log('plan_canceled', Subscription::class, $currentSub->id, [
                'organization_id' => $organization->id,
                'grace_until' => $graceUntil->toISOString(),
                'before' => ['status' => 'active'],
                'after' => [
                    'status' => 'canceled',
                    'expires_at' => $graceUntil->toISOString(),
                    'grace_ends_at' => $graceUntil->toISOString(),
                ],
            ]);
        });

        return redirect()->route('institution.billing.index')
            ->with('status', 'Plano cancelado. Você terá acesso por mais 30 dias.');
    }

    /**
     * Retorna uso atual da organização.
     */
    private function authorizePlanOwner(): void
    {
        $user = auth()->user();
        abort_unless($user, 403);
        $this->planOwnership->authorize($user, $user->organization);
    }

    private function getUsage($organization): array
    {
        return [
            'teachers' => User::query()->memberOfOrganization((int) $organization->id, 'teacher')->count(),
            'students' => User::query()->memberOfOrganization((int) $organization->id, 'student')->count(),
            'classes' => SchoolClass::withoutGlobalScopes()
                ->where('organization_id', $organization->id)->count(),
        ];
    }
}
