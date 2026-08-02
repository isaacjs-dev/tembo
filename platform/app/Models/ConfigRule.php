<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class ConfigRule extends Model
{
    use Auditable;

    protected $fillable = [
        'organization_id',
        'config_key',
        'config_value',
        'scope_type',
        'scope_id',
        'priority',
        'is_active',
        'effective_from',
        'effective_until',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    /**
     * Mapeamento de scope_type para priority numérica.
     * Menor número = maior prioridade.
     */
    public const PRIORITY_MAP = [
        'user' => 1,
        'permission' => 2,
        'role' => 3,
        'function' => 3,  // mesma prioridade que role
        'user_type' => 4,
        'global' => 5,
    ];

    /**
     * Config keys válidas.
     */
    public const VALID_KEYS = [
        'answer_sheet_type',
        'scan_mode',
    ];

    /* ── Relationships ── */

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ── Scopes ── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEffective($query)
    {
        return $query->active()
            ->where('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>', now());
            });
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForKey($query, string $configKey)
    {
        return $query->where('config_key', $configKey);
    }

    public function scopeForScope($query, string $scopeType, ?string $scopeId = null)
    {
        $query->where('scope_type', $scopeType);

        if ($scopeId !== null) {
            $query->where('scope_id', $scopeId);
        } else {
            $query->whereNull('scope_id');
        }

        return $query;
    }

    /* ── Boot ── */

    protected static function booted(): void
    {
        static::creating(function (ConfigRule $rule) {
            if (empty($rule->priority)) {
                $rule->priority = self::PRIORITY_MAP[$rule->scope_type] ?? 5;
            }
        });
    }

    /* ── Helpers ── */

    /**
     * Retorna label legível do scope para exibição.
     */
    public function getScopeLabel(): string
    {
        return match ($this->scope_type) {
            'user' => 'Usuário Específico',
            'permission' => 'Permissão',
            'role' => 'Papel',
            'function' => 'Função',
            'user_type' => 'Tipo de Usuário',
            'global' => 'Global',
            default => $this->scope_type,
        };
    }
}
