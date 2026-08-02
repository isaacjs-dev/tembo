<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfigAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'config_rule_id',
        'action',
        'config_key',
        'old_value',
        'new_value',
        'changed_by',
        'change_reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /* ── Relationships ── */

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function configRule()
    {
        return $this->belongsTo(ConfigRule::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /* ── Scopes ── */

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeRecent($query, int $limit = 50)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    /* ── Accessors ── */

    /**
     * Alias para old_value (convenção plural usada nas views).
     */
    public function getOldValuesAttribute(): ?array
    {
        return $this->old_value;
    }

    /**
     * Alias para new_value (convenção plural usada nas views).
     */
    public function getNewValuesAttribute(): ?array
    {
        return $this->new_value;
    }

    /**
     * Scope type da regra associada para display.
     */
    public function getScopeTypeAttribute(): ?string
    {
        return $this->new_value['scope_type'] ?? $this->old_value['scope_type'] ?? null;
    }
}
