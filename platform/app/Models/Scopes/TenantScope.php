<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->hasUser()) {
            $user = auth()->user();

            // Administradores Globais enxergam de todas as organizações
            if ($user->type === 'global_admin') {
                return;
            }

            // Authenticated tenant users without an explicit context must never
            // fall back to an unscoped query.
            if (! $user->organization_id || ! $user->belongsToActiveOrganization((int) $user->organization_id)) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($model->getTable().'.organization_id', $user->organization_id);
        }
    }
}
