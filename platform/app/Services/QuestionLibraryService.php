<?php

namespace App\Services;

use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class QuestionLibraryService
{
    public const SCOPES = ['mine', 'shared', 'institution', 'platform'];

    public function normalizeScope(?string $scope): string
    {
        return match ($scope) {
            'personal' => 'mine',
            'public' => 'institution',
            'shared', 'institution', 'platform' => $scope,
            default => 'mine',
        };
    }

    public function visibleTo(User $user): Builder
    {
        $organizationId = (int) $user->organization_id;

        return Question::query()
            ->where(function (Builder $query) use ($user, $organizationId): void {
                $query->where('visibility_scope', 'platform_public')
                    ->orWhere(function (Builder $query) use ($user, $organizationId): void {
                        $query->where('organization_id', $organizationId)
                            ->where(function (Builder $query) use ($user): void {
                                $query->where('owner_id', $user->id)
                                    ->orWhere('visibility_scope', 'org_public')
                                    ->orWhere(function (Builder $query) use ($user): void {
                                        $query->where('visibility_scope', 'shared_specific')
                                            ->whereHas('shares', fn (Builder $shares) => $shares
                                                ->where('shared_with_user_id', $user->id));
                                    });
                            });
                    });
            });
    }

    public function forScope(User $user, ?string $scope): Builder
    {
        $scope = $this->normalizeScope($scope);
        $organizationId = (int) $user->organization_id;
        $query = $this->visibleTo($user);

        return match ($scope) {
            'shared' => $query
                ->where('organization_id', $organizationId)
                ->where('owner_id', '!=', $user->id)
                ->where('visibility_scope', 'shared_specific')
                ->whereHas('shares', fn (Builder $shares) => $shares
                    ->where('shared_with_user_id', $user->id)),
            'institution' => $query
                ->where('organization_id', $organizationId)
                ->where('owner_id', '!=', $user->id)
                ->where('visibility_scope', 'org_public'),
            'platform' => $query
                ->where('visibility_scope', 'platform_public')
                ->where(function (Builder $query) use ($user, $organizationId): void {
                    $query->where('owner_id', '!=', $user->id)
                        ->orWhere('organization_id', '!=', $organizationId);
                }),
            default => $query
                ->where('organization_id', $organizationId)
                ->where('owner_id', $user->id),
        };
    }

    public function canView(User $user, Question $question): bool
    {
        return $this->visibleTo($user)->whereKey($question)->exists();
    }

    /** @return array{mine:int,shared:int,institution:int,platform:int} */
    public function counts(User $user): array
    {
        return collect(self::SCOPES)
            ->mapWithKeys(fn (string $scope): array => [
                $scope => $this->forScope($user, $scope)->count(),
            ])
            ->all();
    }
}
