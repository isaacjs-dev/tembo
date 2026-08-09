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
            'role' => $query->where('type', $targetRole),
            'organization' => $query->where('organization_id', $targetId),
            default => throw new \InvalidArgumentException('Escopo administrativo inválido.'),
        };
    }
}
