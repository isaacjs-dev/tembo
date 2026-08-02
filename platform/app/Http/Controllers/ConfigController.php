<?php

namespace App\Http\Controllers;

use App\Models\AnswerSheetType;
use App\Models\ConfigAuditLog;
use App\Models\ConfigRule;
use App\Models\InstitutionRole;
use App\Models\ScanMode;
use App\Models\User;
use App\Services\ConfigAuditService;
use App\Services\ConfigPrecedenceResolver;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function __construct(
        private ConfigPrecedenceResolver $resolver,
        private ConfigAuditService $auditService,
    ) {}

    /**
     * GET /config
     * Painel principal de configuração de OMR.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        $rules = ConfigRule::forOrganization($organizationId)
            ->with('creator:id,name')
            ->orderBy('config_key')
            ->orderBy('priority')
            ->get();

        $answerSheetTypes = AnswerSheetType::active()
            ->forOrganization($organizationId)
            ->get();

        $scanModes = ScanMode::active()->get();

        // Grouped rules by key for display
        $groupedRules = $rules->groupBy('config_key');

        // Get roles for scope selector
        $roles = InstitutionRole::where('organization_id', $organizationId)->get();

        return view('config.index', compact(
            'rules',
            'groupedRules',
            'answerSheetTypes',
            'scanModes',
            'roles',
            'organizationId',
        ));
    }

    /**
     * POST /config/rules
     * Cria uma nova regra de configuração.
     */
    public function storeRule(Request $request)
    {
        $request->validate([
            'config_key' => 'required|in:'.implode(',', ConfigRule::VALID_KEYS),
            'config_value' => 'required|string|max:100',
            'scope_type' => 'required|in:global,user_type,role,permission,user',
            'scope_id' => 'nullable|string|max:100',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date|after:effective_from',
            'change_reason' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $organizationId = $user->organization_id;

        // Check for existing active rule
        $existing = ConfigRule::forOrganization($organizationId)
            ->forKey($request->config_key)
            ->forScope($request->scope_type, $request->scope_id)
            ->active()
            ->first();

        if ($existing) {
            return back()->with('error', 'Já existe uma regra ativa para este escopo. Desative-a primeiro.');
        }

        $rule = ConfigRule::create([
            'organization_id' => $organizationId,
            'config_key' => $request->config_key,
            'config_value' => $request->config_value,
            'scope_type' => $request->scope_type,
            'scope_id' => $request->scope_id,
            'priority' => ConfigRule::PRIORITY_MAP[$request->scope_type] ?? 5,
            'effective_from' => $request->effective_from ?? now(),
            'effective_until' => $request->effective_until,
            'created_by' => $user->id,
        ]);

        $this->auditService->logCreated($rule, $user->id, $request->change_reason);

        return back()->with('status', 'Regra criada com sucesso.');
    }

    /**
     * PUT /config/rules/{id}
     * Atualiza uma regra de configuração existente.
     */
    public function updateRule(Request $request, int $id)
    {
        $request->validate([
            'config_value' => 'sometimes|required|string|max:100',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date',
            'is_active' => 'sometimes|boolean',
            'change_reason' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $organizationId = $user->organization_id;

        $rule = ConfigRule::forOrganization($organizationId)->findOrFail($id);

        $oldValues = [
            'config_value' => $rule->config_value,
            'is_active' => $rule->is_active,
        ];

        $rule->update($request->only(['config_value', 'effective_from', 'effective_until', 'is_active']));

        $this->auditService->logUpdated($rule, $oldValues, $user->id, $request->change_reason);

        return back()->with('status', 'Regra atualizada com sucesso.');
    }

    /**
     * DELETE /config/rules/{id}
     * Desativa uma regra de configuração.
     */
    public function destroyRule(Request $request, int $id)
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        $rule = ConfigRule::forOrganization($organizationId)->findOrFail($id);
        $rule->update(['is_active' => false]);

        $this->auditService->logDeactivated($rule, $user->id, $request->input('change_reason'));

        return back()->with('status', 'Regra desativada com sucesso.');
    }

    /**
     * GET /config/audit
     * Timeline de auditoria.
     */
    public function audit(Request $request)
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        $logs = ConfigAuditLog::forOrganization($organizationId)
            ->with('changedBy:id,name')
            ->recent($request->integer('limit', 50))
            ->get();

        return view('config.audit', compact('logs'));
    }

    /**
     * GET /config/simulate/{userId}
     * Simula a cadeia de resolução para um usuário.
     */
    public function simulate(Request $request, int $userId)
    {
        $currentUser = $request->user();
        $organizationId = $currentUser->organization_id;

        $targetUser = User::findOrFail($userId);

        $results = [];
        foreach (ConfigRule::VALID_KEYS as $key) {
            $results[$key] = $this->resolver->resolveWithTrace($organizationId, $userId, $key);
        }

        return view('config.simulate', compact('targetUser', 'results'));
    }
}
