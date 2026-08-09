<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionResource;
use App\Models\QuestionResourceVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestionResourceService
{
    public function visibleTo(User $user): Builder
    {
        $organizationId = (int) $user->organization_id;

        return QuestionResource::query()
            ->where('status', 'active')
            ->where(function (Builder $query) use ($user, $organizationId): void {
                $query->where('visibility_scope', 'platform_public')
                    ->orWhere(function (Builder $query) use ($user, $organizationId): void {
                        $query->where('organization_id', $organizationId)
                            ->where(function (Builder $query) use ($user): void {
                                $query->where('owner_id', $user->id)
                                    ->orWhere('visibility_scope', 'organization')
                                    ->orWhereHas('shares', fn (Builder $shares) => $shares
                                        ->where('shared_with_user_id', $user->id));
                            });
                    });
            });
    }

    public function canView(User $user, QuestionResource $resource): bool
    {
        if ($resource->status !== 'active') {
            return false;
        }

        if ($resource->visibility_scope === 'platform_public') {
            return true;
        }

        if ((int) $user->organization_id !== (int) $resource->organization_id) {
            return false;
        }

        return (int) $resource->owner_id === (int) $user->id
            || $resource->visibility_scope === 'organization'
            || $resource->shares()->where('shared_with_user_id', $user->id)->exists();
    }

    /**
     * @param  array<int, int|string>  $resourceIds
     */
    public function syncQuestion(Question $question, array $resourceIds, User $actor): void
    {
        $ids = collect($resourceIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $existingLinks = $question->resourceLinks()->with('version')->get()->keyBy('question_resource_id');
        $visibleExistingIds = $this->visibleTo($actor)
            ->whereKey($existingLinks->keys())
            ->pluck('id');
        $preservedLinks = $existingLinks->except($visibleExistingIds->all());

        $resources = $this->visibleTo($actor)
            ->with(['currentVersion', 'shares:id,question_resource_id,shared_with_user_id'])
            ->whereKey($ids)
            ->get()
            ->keyBy('id');
        if ($resources->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'resource_ids' => 'Um ou mais recursos não existem ou não estão acessíveis neste contexto.',
            ]);
        }

        $questionShareIds = $question->shares()->pluck('shared_with_user_id');
        $sync = $preservedLinks->mapWithKeys(fn ($link): array => [
            $link->question_resource_id => [
                'question_resource_version_id' => $link->question_resource_version_id,
                'is_required' => $link->is_required,
                'sort_order' => $link->sort_order,
            ],
        ])->all();
        foreach ($ids as $order => $resourceId) {
            $resource = $resources->get($resourceId);
            if (! $resource?->currentVersion) {
                throw ValidationException::withMessages([
                    'resource_ids' => "O recurso {$resource?->title} ainda não possui uma versão utilizável.",
                ]);
            }

            $this->assertCompatible($question, $resource, $question->visibility_scope, $questionShareIds);
            $existingLink = $existingLinks->get($resource->id);
            if ($existingLink
                && (int) $existingLink->version?->question_resource_id !== (int) $resource->id) {
                throw ValidationException::withMessages([
                    'resource_ids' => 'O vínculo histórico do recurso possui uma versão incompatível.',
                ]);
            }
            $sync[$resource->id] = [
                'question_resource_version_id' => $existingLink?->question_resource_version_id
                    ?? $resource->currentVersion->id,
                'is_required' => $existingLink?->is_required ?? true,
                'sort_order' => $order,
            ];
        }

        $question->resources()->sync($sync);
    }

    /**
     * Validate links before changing a question's visibility or audience.
     *
     * @param  Collection<int, int>|null  $questionShareIds
     */
    public function assertQuestionLinksCompatible(
        Question $question,
        string $visibility,
        ?Collection $questionShareIds = null,
    ): void {
        $questionShareIds ??= $question->shares()->pluck('shared_with_user_id');
        $question->loadMissing(['resourceLinks.resource.shares']);

        foreach ($question->resourceLinks as $link) {
            $resource = $link->resource;
            if (! $resource) {
                throw ValidationException::withMessages([
                    'resource_ids' => 'Um recurso histórico vinculado à questão não está mais disponível.',
                ]);
            }
            $this->assertCompatible($question, $resource, $visibility, $questionShareIds);
        }
    }

    /**
     * Validate every consumer before narrowing a resource's audience.
     *
     * @param  Collection<int, int>  $resourceShareIds
     */
    public function assertLinkedQuestionsCompatible(
        QuestionResource $resource,
        string $visibility,
        Collection $resourceShareIds,
    ): void {
        $resource->loadMissing(['questions.shares']);
        $resource->setRelation(
            'shares',
            $resourceShareIds->map(fn (int $userId) => (object) ['shared_with_user_id' => $userId]),
        );
        $resource->visibility_scope = $visibility;

        foreach ($resource->questions as $question) {
            $this->assertCompatible(
                $question,
                $resource,
                $question->visibility_scope,
                $question->shares->pluck('shared_with_user_id'),
                $resourceShareIds,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array{storage_disk?:?string,storage_path?:?string,mime_type?:?string,size_bytes?:?int,sha256?:?string}  $file
     */
    public function createVersion(
        QuestionResource $resource,
        array $content,
        User $actor,
        array $file = [],
    ): QuestionResourceVersion {
        $content['title'] ??= $resource->title;

        return DB::transaction(function () use ($resource, $content, $actor, $file): QuestionResourceVersion {
            $locked = QuestionResource::query()->lockForUpdate()->findOrFail($resource->id);
            $latest = $locked->versions()->first();
            $fileFingerprint = $file['sha256'] ?? $latest?->sha256;
            $hash = hash('sha256', json_encode(
                ['content' => $content, 'file_sha256' => $fileFingerprint],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

            if ($latest && hash_equals($latest->content_hash, $hash)) {
                return $latest;
            }

            return $locked->versions()->create([
                'version_number' => ((int) ($latest?->version_number ?? 0)) + 1,
                'content' => $content,
                'content_hash' => $hash,
                'storage_disk' => $file['storage_disk'] ?? $latest?->storage_disk,
                'storage_path' => $file['storage_path'] ?? $latest?->storage_path,
                'mime_type' => $file['mime_type'] ?? $latest?->mime_type,
                'size_bytes' => $file['size_bytes'] ?? $latest?->size_bytes,
                'sha256' => $file['sha256'] ?? $latest?->sha256,
                'created_by' => $actor->id,
            ]);
        }, 3);
    }

    /**
     * @param  Collection<int, int>  $questionShareIds
     * @param  Collection<int, int>|null  $resourceShareIds
     */
    private function assertCompatible(
        Question $question,
        QuestionResource $resource,
        string $questionVisibility,
        Collection $questionShareIds,
        ?Collection $resourceShareIds = null,
    ): void {
        $compatible = $resource->visibility_scope === 'platform_public';

        if (! $compatible && (int) $resource->organization_id === (int) $question->organization_id) {
            $compatible = match ($questionVisibility) {
                'platform_public' => $resource->visibility_scope === 'platform_public',
                'org_public' => $resource->visibility_scope === 'organization',
                'shared_specific' => $this->coversSpecificAudience(
                    $resource,
                    $questionShareIds,
                    $resourceShareIds,
                ),
                default => (int) $resource->owner_id === (int) $question->owner_id
                    || $resource->visibility_scope === 'organization'
                    || $resource->shares()->where('shared_with_user_id', $question->owner_id)->exists(),
            };
        }

        if (! $compatible) {
            throw ValidationException::withMessages([
                'resource_ids' => "A visibilidade do recurso \"{$resource->title}\" não cobre todo o público da questão.",
            ]);
        }
    }

    /**
     * @param  Collection<int, int>  $questionShareIds
     * @param  Collection<int, int>|null  $resourceShareIds
     */
    private function coversSpecificAudience(
        QuestionResource $resource,
        Collection $questionShareIds,
        ?Collection $resourceShareIds,
    ): bool {
        if ($resource->visibility_scope === 'organization') {
            return true;
        }

        if ($questionShareIds->isEmpty()) {
            return true;
        }

        if ($resource->visibility_scope !== 'shared_specific') {
            return false;
        }

        $resourceShareIds ??= $resource->shares->pluck('shared_with_user_id');

        return $questionShareIds->diff($resourceShareIds)->isEmpty();
    }
}
