<?php

namespace App\Services;

use App\Models\ConfigRule;
use App\Models\User;
use App\Models\UserOrganization;
use Illuminate\Support\Facades\Log;

class ConfigPrecedenceResolver
{
    /**
     * Valores padrão do sistema quando nenhuma regra é encontrada.
     */
    private const SYSTEM_DEFAULTS = [
        'answer_sheet_type' => 'essential',
        'scan_mode' => 'hybrid',
    ];

    /**
     * Resolve a configuração efetiva para um usuário em uma organização.
     *
     * Hierarquia de precedência (menor priority = maior prioridade):
     *   1. user        → configuração específica do usuário
     *   2. permission  → quem tem a permissão X
     *   3. role        → quem tem o papel Y
     *   4. user_type   → tipo de usuário (teacher, admin, coordinator)
     *   5. global      → padrão da organização
     *   6. (fallback)  → padrão do sistema (essential, hybrid)
     *
     * @return string O valor da configuração efetiva.
     */
    public function resolve(int $organizationId, int $userId, string $configKey): string
    {
        $result = $this->resolveWithTrace($organizationId, $userId, $configKey);

        return $result['effective_value'];
    }

    /**
     * Resolve a configuração efetiva e retorna toda a cadeia de resolução.
     * Útil para o painel administrativo e auditoria.
     *
     * @return array{
     *   effective_value: string,
     *   resolved_from: string,
     *   rule_id: int|null,
     *   trace: array
     * }
     */
    public function resolveWithTrace(int $organizationId, int $userId, string $configKey): array
    {
        $trace = [];

        // Obter contexto do usuário na organização
        $pivot = UserOrganization::where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->first();

        if (! $pivot) {
            Log::warning('ConfigPrecedenceResolver: usuário não pertence à organização', [
                'user_id' => $userId,
                'organization_id' => $organizationId,
            ]);

            return $this->buildResult(
                self::SYSTEM_DEFAULTS[$configKey] ?? 'essential',
                'system_default',
                null,
                $trace
            );
        }

        // 1. Regra para o usuário específico
        $rule = $this->findRule($organizationId, $configKey, 'user', (string) $userId);
        $trace[] = $this->traceEntry('user', (string) $userId, $rule);
        if ($rule) {
            return $this->buildResult($rule->config_value, 'user', $rule->id, $trace);
        }

        // 2. Regra por permissão
        $userPermissions = $this->getUserPermissions($pivot);
        if (! empty($userPermissions)) {
            $rule = ConfigRule::forOrganization($organizationId)
                ->forKey($configKey)
                ->effective()
                ->where('scope_type', 'permission')
                ->whereIn('scope_id', $userPermissions)
                ->orderBy('id')  // Determinístico: menor ID ganha em caso de conflito
                ->first();
            $trace[] = $this->traceEntry('permission', implode(',', $userPermissions), $rule);
            if ($rule) {
                return $this->buildResult($rule->config_value, 'permission', $rule->id, $trace);
            }
        } else {
            $trace[] = $this->traceEntry('permission', '(nenhuma)', null);
        }

        // 3. Regra por papel (role)
        $roleId = $pivot->institution_role_id;
        if ($roleId) {
            $rule = $this->findRule($organizationId, $configKey, 'role', (string) $roleId);
            $trace[] = $this->traceEntry('role', (string) $roleId, $rule);
            if ($rule) {
                return $this->buildResult($rule->config_value, 'role', $rule->id, $trace);
            }
        } else {
            $trace[] = $this->traceEntry('role', '(nenhum)', null);
        }

        // 4. Regra por tipo de usuário
        $userType = $pivot->role_in_org ?? 'teacher';
        $rule = $this->findRule($organizationId, $configKey, 'user_type', $userType);
        $trace[] = $this->traceEntry('user_type', $userType, $rule);
        if ($rule) {
            return $this->buildResult($rule->config_value, 'user_type', $rule->id, $trace);
        }

        // 5. Regra global
        $rule = ConfigRule::forOrganization($organizationId)
            ->forKey($configKey)
            ->effective()
            ->where('scope_type', 'global')
            ->first();
        $trace[] = $this->traceEntry('global', null, $rule);
        if ($rule) {
            return $this->buildResult($rule->config_value, 'global', $rule->id, $trace);
        }

        // 6. Fallback do sistema
        $defaultValue = self::SYSTEM_DEFAULTS[$configKey] ?? 'essential';
        $trace[] = $this->traceEntry('system_default', $defaultValue, null);

        return $this->buildResult($defaultValue, 'system_default', null, $trace);
    }

    /**
     * Resolve todas as chaves de configuração de uma vez para um usuário.
     *
     * @return array<string, array{effective_value: string, resolved_from: string}>
     */
    public function resolveAll(int $organizationId, int $userId): array
    {
        $result = [];
        foreach (ConfigRule::VALID_KEYS as $key) {
            $resolved = $this->resolveWithTrace($organizationId, $userId, $key);
            $result[$key] = [
                'effective_value' => $resolved['effective_value'],
                'resolved_from' => $resolved['resolved_from'],
            ];
        }

        return $result;
    }

    /**
     * Busca uma regra ativa/efetiva para um escopo específico.
     */
    private function findRule(int $organizationId, string $configKey, string $scopeType, ?string $scopeId): ?ConfigRule
    {
        $query = ConfigRule::forOrganization($organizationId)
            ->forKey($configKey)
            ->effective()
            ->where('scope_type', $scopeType);

        if ($scopeId !== null) {
            $query->where('scope_id', $scopeId);
        } else {
            $query->whereNull('scope_id');
        }

        return $query->first();
    }

    /**
     * Obtém as permission keys do usuário na organização.
     */
    private function getUserPermissions(UserOrganization $pivot): array
    {
        if (! $pivot->institution_role_id) {
            return [];
        }

        $role = $pivot->role;
        if (! $role) {
            return [];
        }

        return $role->getPermissionKeys();
    }

    /**
     * Monta um registro de trace para auditoria.
     */
    private function traceEntry(string $scopeType, ?string $scopeId, ?ConfigRule $rule): array
    {
        return [
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'found' => $rule !== null,
            'value' => $rule?->config_value,
            'rule_id' => $rule?->id,
        ];
    }

    /**
     * Monta o resultado final de resolução.
     */
    private function buildResult(string $value, string $resolvedFrom, ?int $ruleId, array $trace): array
    {
        return [
            'effective_value' => $value,
            'resolved_from' => $resolvedFrom,
            'rule_id' => $ruleId,
            'trace' => $trace,
        ];
    }
}
