<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'organization_id',
        'request_id',
        'origin',
        'severity',
        'action',
        'model_type',
        'model_id',
        'payload',
        'context_json',
        'before_json',
        'after_json',
        'legacy_event_log_id',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'context_json' => 'array',
        'before_json' => 'array',
        'after_json' => 'array',
        'created_at' => 'datetime',
    ];

    /* ── Relationships ── */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
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
        $payload = static::sanitize($payload ?? []);
        $before = $payload['before'] ?? $payload['old'] ?? null;
        $after = $payload['after'] ?? $payload['new'] ?? null;
        $context = Arr::except($payload, ['before', 'after', 'old', 'new']);
        $user = auth()->user();
        $organizationId = $context['organization_id']
            ?? request()->attributes->get('workspace')?->id
            ?? $user?->organization_id;

        return static::create([
            'user_id' => $user?->id,
            'organization_id' => $organizationId,
            'request_id' => static::requestId(),
            'origin' => static::origin(),
            'severity' => $context['severity'] ?? 'info',
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'payload' => $payload,
            'context_json' => $context ?: null,
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    public static function fromEvent(EventLog $event): self
    {
        $context = static::sanitize($event->context_json ?? []);
        if ($event->message) {
            $context['message'] = $event->message;
        }

        return static::firstOrCreate(
            ['legacy_event_log_id' => $event->id],
            [
                'organization_id' => $event->organization_id,
                'user_id' => $event->actor_user_id,
                'request_id' => static::requestId(),
                'origin' => static::origin(),
                'severity' => $event->severity ?: 'info',
                'action' => $event->event_code,
                'model_type' => $event->entity_type,
                'model_id' => $event->entity_id,
                'payload' => $context,
                'context_json' => $context ?: null,
                'before_json' => static::sanitize($event->before_json ?? []),
                'after_json' => static::sanitize($event->after_json ?? []),
                'ip_address' => $event->ip,
                'user_agent' => $event->user_agent,
                'created_at' => $event->created_at ?? now(),
            ],
        );
    }

    /** @param array<string, mixed> $data */
    public static function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (str_contains($normalizedKey, 'password')
                || $normalizedKey === 'token'
                || str_ends_with($normalizedKey, '_token')
                || $normalizedKey === 'secret'
                || str_ends_with($normalizedKey, '_secret')) {
                $data[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = static::sanitize($value);

                continue;
            }

            if (is_string($value) && (str_starts_with(trim($value), '{') || str_starts_with(trim($value), '['))) {
                $decoded = json_decode($value, true);
                if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                    $data[$key] = json_encode(
                        static::sanitize($decoded),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    );
                }
            }
        }

        return $data;
    }

    private static function requestId(): string
    {
        $request = request();
        $existing = $request->attributes->get('audit_request_id');
        if (is_string($existing)) {
            return $existing;
        }

        $provided = $request->headers->get('X-Request-ID');
        $requestId = is_string($provided) && preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $provided)
            ? $provided
            : (string) Str::uuid();
        $request->attributes->set('audit_request_id', $requestId);

        return $requestId;
    }

    private static function origin(): string
    {
        if (app()->bound('request') && request()->route()) {
            return request()->is('api/*') ? 'api' : 'web';
        }

        return app()->runningInConsole() ? 'console' : 'system';
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
