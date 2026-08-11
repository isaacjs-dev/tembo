<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PublicCatalogRewardRule;
use App\Services\MonthlyUsageService;
use App\Services\PublicCatalogRewardScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PublicCatalogRewardRuleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'rule_version' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:public_catalog_reward_rules,rule_version'],
            'subject_kind' => ['required', Rule::in(['question', 'resource'])],
            'resource_key' => ['required', Rule::in(MonthlyUsageService::RESOURCES)],
            'credit_amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'per_user_monthly_cap' => ['required', 'integer', 'min:1', 'max:1000000', 'gte:credit_amount'],
            'global_monthly_cap' => ['nullable', 'integer', 'min:1', 'max:100000000', 'gte:credit_amount'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $rule = PublicCatalogRewardRule::query()->create([
            ...$data,
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);
        AuditLog::log('public_catalog_reward_rule_created', $rule::class, $rule->id, [
            'rule_version' => $rule->rule_version,
        ]);

        return back()->with('status', 'Regra versionada criada como rascunho. Ative-a após revisar os limites.');
    }

    public function activate(
        Request $request,
        PublicCatalogRewardRule $rewardRule,
        PublicCatalogRewardScheduleService $schedule,
    ): RedirectResponse {
        abort_unless($rewardRule->status === 'draft', 409, 'Somente regras em rascunho podem ser ativadas.');
        abort_if($rewardRule->ends_at?->isPast(), 422, 'A vigência desta regra já terminou.');

        DB::transaction(function () use ($request, $rewardRule, $schedule): void {
            $slot = $schedule->lockAndNormalize($rewardRule->subject_kind);
            $locked = PublicCatalogRewardRule::query()->lockForUpdate()->findOrFail($rewardRule->id);
            abort_unless($locked->status === 'draft', 409);
            $scheduled = $locked->starts_at?->isFuture() ?? false;
            $replacedId = $scheduled ? $slot->scheduled_rule_id : $slot->active_rule_id;
            if ($replacedId) {
                PublicCatalogRewardRule::query()->lockForUpdate()->find($replacedId)?->update([
                    'status' => 'retired', 'retired_at' => now(),
                ]);
            }
            $locked->update([
                'status' => $scheduled ? 'scheduled' : 'active',
                'activated_by' => $request->user()->id,
                'activated_at' => now(),
                'retired_at' => null,
            ]);
            DB::table('public_catalog_reward_rule_slots')->where('subject_kind', $locked->subject_kind)->update([
                $scheduled ? 'scheduled_rule_id' : 'active_rule_id' => $locked->id,
                'updated_at' => now(),
            ]);
        }, 3);

        AuditLog::log('public_catalog_reward_rule_activated', $rewardRule::class, $rewardRule->id, [
            'rule_version' => $rewardRule->rule_version,
        ]);

        return back()->with('status', $rewardRule->starts_at?->isFuture()
            ? 'Regra agendada sem interromper a recompensa vigente.'
            : 'Regra ativada. A versão vigente anterior desse tipo foi aposentada.');
    }

    public function retire(
        Request $request,
        PublicCatalogRewardRule $rewardRule,
        PublicCatalogRewardScheduleService $schedule,
    ): RedirectResponse {
        abort_unless(in_array($rewardRule->status, ['active', 'scheduled'], true), 409, 'Somente regras vigentes ou agendadas podem ser aposentadas.');
        DB::transaction(function () use ($rewardRule, $schedule): void {
            $slot = $schedule->lockAndNormalize($rewardRule->subject_kind);
            $locked = PublicCatalogRewardRule::query()->lockForUpdate()->findOrFail($rewardRule->id);
            abort_unless(in_array($locked->status, ['active', 'scheduled'], true), 409);
            $locked->update(['status' => 'retired', 'retired_at' => now()]);
            DB::table('public_catalog_reward_rule_slots')->where('subject_kind', $locked->subject_kind)->update([
                'active_rule_id' => (int) $slot->active_rule_id === (int) $locked->id ? null : $slot->active_rule_id,
                'scheduled_rule_id' => (int) $slot->scheduled_rule_id === (int) $locked->id ? null : $slot->scheduled_rule_id,
                'updated_at' => now(),
            ]);
        }, 3);
        AuditLog::log('public_catalog_reward_rule_retired', $rewardRule::class, $rewardRule->id, [
            'rule_version' => $rewardRule->rule_version,
        ]);

        return back()->with('status', 'Regra aposentada; premiações históricas foram preservadas.');
    }
}
