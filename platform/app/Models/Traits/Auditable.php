<?php

namespace App\Models\Traits;

use App\Models\AuditLog;

/**
 * Trait Auditable — aplique em qualquer Model para gerar logs automáticos de CRUD.
 *
 * Usage: `use Auditable;` no Model desejado.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLog::log('created', get_class($model), $model->getKey(), [
                'new' => self::sanitizeAuditData($model->getAttributes()),
            ]);
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();
            if (empty($dirty)) {
                return;
            }

            $original = collect($model->getOriginal())->only(array_keys($dirty))->toArray();

            AuditLog::log('updated', get_class($model), $model->getKey(), [
                'old' => self::sanitizeAuditData($original),
                'new' => self::sanitizeAuditData($dirty),
            ]);
        });

        static::deleted(function ($model) {
            AuditLog::log('deleted', get_class($model), $model->getKey(), [
                'old' => self::sanitizeAuditData($model->getAttributes()),
            ]);
        });
    }

    /**
     * Audit trails must preserve what changed without persisting credentials,
     * bearer tokens, HMAC keys or similarly named secrets.
     */
    private static function sanitizeAuditData(array $data): array
    {
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            $isSensitive = str_contains($normalizedKey, 'password')
                || $normalizedKey === 'token'
                || str_ends_with($normalizedKey, '_token')
                || $normalizedKey === 'secret'
                || str_ends_with($normalizedKey, '_secret');

            if ($isSensitive) {
                $data[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::sanitizeAuditData($value);

                continue;
            }

            // Cast JSON attributes are returned by getAttributes() as strings.
            if (is_string($value) && (str_starts_with(trim($value), '{') || str_starts_with(trim($value), '['))) {
                $decoded = json_decode($value, true);
                if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                    $data[$key] = json_encode(
                        self::sanitizeAuditData($decoded),
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
                }
            }
        }

        return $data;
    }
}
