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
                'new' => $model->getAttributes(),
            ]);
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();
            if (empty($dirty)) {
                return;
            }

            $original = collect($model->getOriginal())->only(array_keys($dirty))->toArray();

            AuditLog::log('updated', get_class($model), $model->getKey(), [
                'old' => $original,
                'new' => $dirty,
            ]);
        });

        static::deleted(function ($model) {
            AuditLog::log('deleted', get_class($model), $model->getKey(), [
                'old' => $model->getAttributes(),
            ]);
        });
    }
}
