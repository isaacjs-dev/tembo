<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\SchoolClass;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    /**
     * Dashboard de billing: plano atual, uso, status, vencimento.
     */
    public function index()
    {
        $this->authorizePlanOwner();
        $organization = auth()->user()->organization;
        $activeSubscription = $organization->subscriptions()
            ->where('status', 'active')
            ->with('plan')
            ->first();

        $plans = Plan::where('status', 'active')
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
            'plan_id' => 'required|exists:plans,id',
        ]);

        $organization = auth()->user()->organization;
        $newPlan = Plan::findOrFail($validated['plan_id']);
        $currentSub = $organization->subscriptions()->where('status', 'active')->first();

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

        DB::transaction(function () use ($organization, $newPlan, $currentSub) {
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
                    'status' => 'canceled',
                    'expires_at' => now(),
                ]);
            }

            // Data de vencimento: se gerou saldo de dias significativos (> 0), usa eles.
            // Senão, dá-se 30 dias na premissa de um novo contrato mensal.
            $expiresAt = $extraDays > 0 ? now()->addDays($extraDays) : now()->addDays(30);

            // Criar nova assinatura
            Subscription::create([
                'organization_id' => $organization->id,
                'plan_id' => $newPlan->id,
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            AuditLog::log('plan_changed', Subscription::class, null, [
                'from_plan' => $currentSub?->plan_id,
                'to_plan' => $newPlan->id,
                'organization_id' => $organization->id,
                'prorata_extra_days' => $extraDays,
            ]);
        });

        return redirect()->route('institution.billing.index')
            ->with('status', "Plano alterado para {$newPlan->name} com sucesso!");
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

        $currentSub = $organization->subscriptions()->where('status', 'active')->first();

        if (! $currentSub) {
            return back()->withErrors(['Nenhuma assinatura ativa para cancelar.']);
        }

        $graceUntil = now()->addDays(30);

        DB::transaction(function () use ($currentSub, $organization, $graceUntil) {
            $currentSub->update([
                'status' => 'canceled',
                'expires_at' => $graceUntil,
            ]);

            AuditLog::log('plan_canceled', Subscription::class, $currentSub->id, [
                'organization_id' => $organization->id,
                'grace_until' => $graceUntil->toISOString(),
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
        $organization = $user?->organization;
        $isOwner = $organization && (int) $organization->owner_user_id === (int) $user->id;
        $isLegacyOwner = $organization
            && $organization->owner_user_id === null
            && $user->type === 'institution_admin'
            && ! $user->organizations()->exists()
            && (int) $user->getRawOriginal('organization_id') === (int) $organization->id;

        abort_unless($isOwner || $isLegacyOwner, 403);
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
