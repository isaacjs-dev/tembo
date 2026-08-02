<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'payload',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /* ── Relationships ── */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /* ── Scopes ── */

    public function scopeForModel($q, string $type, ?int $id = null)
    {
        $q->where('model_type', $type);
        if ($id) {
            $q->where('model_id', $id);
        }

        return $q;
    }

    public function scopeByAction($q, string $action)
    {
        return $q->where('action', $action);
    }

    public function scopeByUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeInPeriod($q, $from, $to)
    {
        return $q->whereBetween('created_at', [$from, $to]);
    }

    /* ── Helpers ── */

    public static function log(
        string $action,
        ?string $modelType = null,
        ?int $modelId = null,
        ?array $payload = null
    ): self {
        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'payload' => $payload,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Nome amigável do model.
     */
    public function getModelLabelAttribute(): string
    {
        if (! $this->model_type) {
            return '—';
        }

        return class_basename($this->model_type);
    }

    /**
     * Rótulo amigável da ação.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'created' => 'Criação',
            'updated' => 'Atualização',
            'deleted' => 'Exclusão',
            'login' => 'Login',
            'logout' => 'Logout',
            'invite_sent' => 'Convite Enviado',
            'invite_accepted' => 'Convite Aceito',
            'invite_declined' => 'Convite Recusado',
            'invite_canceled' => 'Convite Cancelado',
            'unlinked' => 'Desvinculação',
            'transfer_initiated' => 'Transferência Iniciada',
            'transfer_accepted' => 'Transferência Aceita',
            'plan_changed' => 'Plano Alterado',
            'plan_canceled' => 'Plano Cancelado',
            'restored' => 'Restauração',
            'force_deleted' => 'Exclusão Permanente',
            'deactivated' => 'Desativação',
            'role_assigned' => 'Cargo Atribuído',
            'access_denied' => 'Acesso Negado',
            'submission_graded' => 'Correção de avaliação',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }
}
