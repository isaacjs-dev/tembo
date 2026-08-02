<?php

namespace App\Services;

use App\Models\ConfigAuditLog;
use App\Models\ConfigRule;

class ConfigAuditService
{
    /**
     * Registra a criação de uma regra de configuração.
     */
    public function logCreated(ConfigRule $rule, int $changedBy, ?string $reason = null): void
    {
        ConfigAuditLog::create([
            'organization_id' => $rule->organization_id,
            'config_rule_id' => $rule->id,
            'action' => 'created',
            'config_key' => $rule->config_key,
            'old_value' => null,
            'new_value' => $this->ruleSnapshot($rule),
            'changed_by' => $changedBy,
            'change_reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /**
     * Registra a atualização de uma regra de configuração.
     */
    public function logUpdated(ConfigRule $rule, array $oldValues, int $changedBy, ?string $reason = null): void
    {
        ConfigAuditLog::create([
            'organization_id' => $rule->organization_id,
            'config_rule_id' => $rule->id,
            'action' => 'updated',
            'config_key' => $rule->config_key,
            'old_value' => $oldValues,
            'new_value' => $this->ruleSnapshot($rule),
            'changed_by' => $changedBy,
            'change_reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /**
     * Registra a desativação de uma regra de configuração.
     */
    public function logDeactivated(ConfigRule $rule, int $changedBy, ?string $reason = null): void
    {
        ConfigAuditLog::create([
            'organization_id' => $rule->organization_id,
            'config_rule_id' => $rule->id,
            'action' => 'deactivated',
            'config_key' => $rule->config_key,
            'old_value' => $this->ruleSnapshot($rule),
            'new_value' => array_merge($this->ruleSnapshot($rule), ['is_active' => false]),
            'changed_by' => $changedBy,
            'change_reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /**
     * Registra a exclusão de uma regra de configuração.
     */
    public function logDeleted(ConfigRule $rule, int $changedBy, ?string $reason = null): void
    {
        ConfigAuditLog::create([
            'organization_id' => $rule->organization_id,
            'config_rule_id' => $rule->id,
            'action' => 'deleted',
            'config_key' => $rule->config_key,
            'old_value' => $this->ruleSnapshot($rule),
            'new_value' => ['deleted' => true],
            'changed_by' => $changedBy,
            'change_reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /**
     * Cria um snapshot dos dados relevantes da regra para o log.
     */
    private function ruleSnapshot(ConfigRule $rule): array
    {
        return [
            'config_key' => $rule->config_key,
            'config_value' => $rule->config_value,
            'scope_type' => $rule->scope_type,
            'scope_id' => $rule->scope_id,
            'priority' => $rule->priority,
            'is_active' => $rule->is_active,
            'effective_from' => $rule->effective_from?->toISOString(),
            'effective_until' => $rule->effective_until?->toISOString(),
        ];
    }
}
