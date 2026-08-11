<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AdminAudienceService
{
    public function query(string $scope, ?int $targetId = null, ?string $targetRole = null): Builder
    {
        $query = User::query()->where('status', 'active');

        return match ($scope) {
            'all' => $query,
            'user' => $query->whereKey($targetId),
            'role' => $query->where(function (Builder $roles) use ($targetRole): void {
                $roles->where('type', $targetRole)
                    ->orWhereHas('organizations', fn (Builder $organizations) => $organizations
                        ->where('user_organization.status', 'active')
                        ->where('user_organization.role_in_org', $targetRole));
            }),
            'organization' => $query->memberOfOrganization((int) $targetId),
            default => throw new \InvalidArgumentException('Escopo administrativo inválido.'),
        };
    }
}
