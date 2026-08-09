<?php

namespace App\Http\Controllers\Api;

use App\Models\AnswerSheetType;
use App\Models\ConfigAuditLog;
use App\Models\ConfigRule;
use App\Models\InstitutionRole;
use App\Models\ScanMode;
use App\Models\User;
use App\Services\ConfigAuditService;
use App\Services\ConfigPrecedenceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class ConfigApiController extends Controller
{
    public function __construct(
        private ConfigPrecedenceResolver $resolver,
        private ConfigAuditService $auditService,
    ) {}

    /**
     * GET /api/v2/config/effective
     *
     * Retorna a configuração efetiva do usuário autenticado.
     * Usado pelo Duoscanner para saber qual gabarito e modo de leitura usar.
     */
    public function effective(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        if (! $organizationId) {
            return response()->json(['error' => 'Usuário sem organização ativa.'], 422);
        }

        // Resolver ambas as configs
        $answerSheetResult = $this->resolver->resolveWithTrace(
            $organizationId, $user->id, 'answer_sheet_type'
        );
        $scanModeResult = $this->resolver->resolveWithTrace(
            $organizationId, $user->id, 'scan_mode'
        );

        // Buscar os objetos completos
        $answerSheetType = AnswerSheetType::where('slug', $answerSheetResult['effective_value'])
            ->active()
            ->forOrganization($organizationId)
            ->first();

        $scanMode = ScanMode::where('slug', $scanModeResult['effective_value'])
            ->active()
            ->first();

        // Calcular config_version (max dos IDs das regras ativas)
        $configVersion = ConfigRule::forOrganization($organizationId)
            ->effective()
            ->max('id') ?? 0;

        return response()->json([
            'effective' => [
                'answer_sheet_type' => $answerSheetResult['effective_value'],
                'answer_sheet_type_resolved_from' => $answerSheetResult['resolved_from'],
                'scan_mode' => $scanModeResult['effective_value'],
                'scan_mode_resolved_from' => $scanModeResult['resolved_from'],
            ],
            'answer_sheet_type_config' => $answerSheetType ? [
                'slug' => $answerSheetType->slug,
                'name' => $answerSheetType->name,
                'layout_config' => $answerSheetType->layout_config,
                'grading_config' => $answerSheetType->grading_config,
                'version' => $answerSheetType->version,
            ] : null,
            'scan_mode_config' => $scanMode ? $scanMode->toConfigPayload() : null,
            'resolved_at' => now()->toISOString(),
            'config_version' => (int) $configVersion,
        ]);
    }

    /**
     * GET /api/v2/config/trace/{userId}
     *
     * Mostra a cadeia completa de resolução para um usuário.
     * Apenas para administradores.
     */
    public function trace(Request $request, int $userId): JsonResponse
    {
        $currentUser = $request->user();
        $this->ensureCanManage($currentUser);
        $organizationId = $currentUser->organization_id;

        if (! $organizationId) {
            return response()->json(['error' => 'Sem organização ativa.'], 422);
        }

        $targetExists = User::whereKey($userId)
            ->where('status', 'active')
            ->where(function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId)
                    ->orWhereHas('organizations', function ($organizations) use ($organizationId) {
                        $organizations->where('organizations.id', $organizationId)
                            ->where('user_organization.status', 'active');
                    });
            })
            ->exists();

        abort_unless($targetExists, 404);

        $result = [];
        foreach (ConfigRule::VALID_KEYS as $key) {
            $result[$key] = $this->resolver->resolveWithTrace($organizationId, $userId, $key);
        }

        return response()->json([
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'resolutions' => $result,
        ]);
    }

    /**
     * GET /api/v2/config/rules
     *
     * Lista regras de configuração da organização com filtros opcionais.
     */
    public function indexRules(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureCanManage($user);
        $organizationId = $user->organization_id;

        $query = ConfigRule::forOrganization($organizationId)
            ->with('creator:id,name')
            ->orderBy('config_key')
            ->orderBy('priority');

        if ($request->filled('config_key')) {
            $query->forKey($request->input('config_key'));
        }

        if ($request->filled('scope_type')) {
            $query->where('scope_type', $request->input('scope_type'));
        }

        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        $rules = $query->get();

        return response()->json([
            'rules' => $rules->map(fn (ConfigRule $rule) => [
                'id' => $rule->id,
                'config_key' => $rule->config_key,
                'config_value' => $rule->config_value,
                'scope_type' => $rule->scope_type,
                'scope_label' => $rule->getScopeLabel(),
                'scope_id' => $rule->scope_id,
                'priority' => $rule->priority,
                'is_active' => $rule->is_active,
                'effective_from' => $rule->effective_from?->toISOString(),
                'effective_until' => $rule->effective_until?->toISOString(),
                'created_by' => $rule->creator?->name,
                'created_at' => $rule->created_at?->toISOString(),
            ]),
        ]);
    }

    /**
     * POST /api/v2/config/rules
     *
     * Cria uma nova regra de configuração.
     */
    public function storeRule(Request $request): JsonResponse
    {
        $this->ensureCanManage($request->user());

        $validator = Validator::make($request->all(), [
            'config_key' => 'required|string|in:'.implode(',', ConfigRule::VALID_KEYS),
            'config_value' => 'required|string|max:100',
            'scope_type' => 'required|string|in:global,user_type,role,function,permission,user',
            'scope_id' => 'nullable|string|max:100|required_unless:scope_type,global',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date|after:effective_from',
            'change_reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $organizationId = $user->organization_id;

        if (! $organizationId) {
            return response()->json(['error' => 'Usuário sem organização ativa.'], 422);
        }

        if (! $this->isValidScope((int) $organizationId, $request->scope_type, $request->scope_id)) {
            return response()->json(['error' => 'O escopo informado não pertence à instituição ativa.'], 422);
        }

        // Validar que o config_value é válido
        if (! $this->isValidConfigValue($request->config_key, $request->config_value, (int) $organizationId)) {
            return response()->json([
                'error' => "Valor '{$request->config_value}' inválido para a chave '{$request->config_key}'.",
            ], 422);
        }

        // Verificar se já existe uma regra ativa para o mesmo escopo
        $existing = ConfigRule::forOrganization($organizationId)
            ->forKey($request->config_key)
            ->forScope($request->scope_type, $request->scope_id)
            ->active()
            ->first();

        if ($existing) {
            return response()->json([
                'error' => 'Já existe uma regra ativa para este escopo. Desative-a primeiro ou use PUT para atualizar.',
                'existing_rule_id' => $existing->id,
            ], 409);
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

        return response()->json(['rule' => $rule], 201);
    }

    /**
     * PUT /api/v2/config/rules/{id}
     *
     * Atualiza uma regra existente.
     */
    public function updateRule(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->ensureCanManage($user);
        $organizationId = $user->organization_id;

        $rule = ConfigRule::forOrganization($organizationId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'config_value' => 'sometimes|required|string|max:100',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date',
            'is_active' => 'sometimes|boolean',
            'change_reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validar config_value se fornecido
        if ($request->filled('config_value') && ! $this->isValidConfigValue($rule->config_key, $request->config_value, (int) $organizationId)) {
            return response()->json([
                'error' => "Valor '{$request->config_value}' inválido para a chave '{$rule->config_key}'.",
            ], 422);
        }

        $oldValues = [
            'config_value' => $rule->config_value,
            'is_active' => $rule->is_active,
            'effective_from' => $rule->effective_from?->toISOString(),
            'effective_until' => $rule->effective_until?->toISOString(),
        ];

        $rule->update($request->only(['config_value', 'effective_from', 'effective_until', 'is_active']));

        $this->auditService->logUpdated($rule, $oldValues, $user->id, $request->change_reason);

        return response()->json(['rule' => $rule->fresh()]);
    }

    /**
     * DELETE /api/v2/config/rules/{id}
     *
     * Desativa (soft-delete) uma regra.
     */
    public function destroyRule(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $this->ensureCanManage($user);
        $organizationId = $user->organization_id;

        $rule = ConfigRule::forOrganization($organizationId)->findOrFail($id);

        $rule->update(['is_active' => false]);

        $this->auditService->logDeactivated($rule, $user->id, $request->input('change_reason'));

        return response()->json(['message' => 'Regra desativada.']);
    }

    /**
     * GET /api/v2/config/audit
     *
     * Timeline de auditoria das alterações de configuração.
     */
    public function audit(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureCanManage($user);
        $organizationId = $user->organization_id;
        $limit = max(1, min($request->integer('limit', 50), 100));

        $logs = ConfigAuditLog::forOrganization($organizationId)
            ->with('changedBy:id,name')
            ->recent($limit)
            ->get();

        return response()->json(['audit_logs' => $logs]);
    }

    /**
     * GET /api/v2/answer-sheet-types
     *
     * Lista tipos de gabarito disponíveis.
     */
    public function answerSheetTypes(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationId = $user->organization_id;

        if (! $organizationId) {
            return response()->json(['error' => 'Usuário sem organização ativa.'], 422);
        }

        $types = AnswerSheetType::active()
            ->forOrganization($organizationId)
            ->get(['id', 'slug', 'name', 'description', 'is_system', 'is_default', 'version', 'layout_config', 'grading_config']);

        return response()->json(['answer_sheet_types' => $types]);
    }

    /**
     * GET /api/v2/scan-modes
     *
     * Lista modos de leitura disponíveis.
     */
    public function scanModes(): JsonResponse
    {
        $modes = ScanMode::active()
            ->get(['id', 'slug', 'name', 'description', 'is_default', 'requires_predownload', 'requires_qr_data', 'offline_capable']);

        return response()->json(['scan_modes' => $modes]);
    }

    /**
     * Valida se um config_value é válido para a config_key.
     */
    private function isValidConfigValue(string $key, string $value, int $organizationId): bool
    {
        return match ($key) {
            'answer_sheet_type' => AnswerSheetType::where('slug', $value)
                ->active()
                ->forOrganization($organizationId)
                ->exists(),
            'scan_mode' => ScanMode::where('slug', $value)->active()->exists(),
            default => false,
        };
    }

    private function isValidScope(int $organizationId, string $scopeType, ?string $scopeId): bool
    {
        if ($scopeType === 'global') {
            return $scopeId === null || $scopeId === '';
        }

        if ($scopeType === 'user') {
            return ctype_digit((string) $scopeId)
                && User::whereKey((int) $scopeId)
                    ->where('status', 'active')
                    ->where(function ($query) use ($organizationId) {
                        $query->where('organization_id', $organizationId)
                            ->orWhereHas('organizations', function ($organizations) use ($organizationId) {
                                $organizations->where('organizations.id', $organizationId)
                                    ->where('user_organization.status', 'active');
                            });
                    })
                    ->exists();
        }

        if ($scopeType === 'role') {
            return ctype_digit((string) $scopeId)
                && InstitutionRole::where('organization_id', $organizationId)
                    ->whereKey((int) $scopeId)
                    ->where('is_active', true)
                    ->exists();
        }

        return $scopeId !== null && $scopeId !== '';
    }

    private function ensureCanManage(User $user): void
    {
        abort_unless($user->hasWorkspaceRole('admin', 'institution_admin', 'global_admin'), 403);
        abort_unless($user->organization_id, 422, 'Usuário sem organização ativa.');
    }
}
