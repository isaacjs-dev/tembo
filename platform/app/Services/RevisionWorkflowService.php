<?php

namespace App\Services;

use App\Models\Revision;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevisionWorkflowService
{
    /**
     * Serialize content changes and protect attempts from retroactive mutation.
     *
     * @template T
     *
     * @param  Closure(Revision): T  $mutation
     * @return T
     */
    public function mutate(Revision $revision, Closure $mutation): mixed
    {
        return DB::transaction(function () use ($revision, $mutation): mixed {
            $locked = Revision::query()->lockForUpdate()->findOrFail($revision->id);
            if ($locked->attempts()->exists()) {
                throw ValidationException::withMessages([
                    'revision' => 'Esta revisão possui tentativas e seu conteúdo histórico não pode mais ser alterado. Duplique-a para criar uma nova versão.',
                ]);
            }

            $result = $mutation($locked);
            if ($locked->status !== 'draft' || $locked->approved_content_hash || $locked->approved_at) {
                $locked->forceFill([
                    'status' => 'draft',
                    'reviewed_by' => null,
                    'review_notes' => null,
                    'published_at' => null,
                    'approved_content_hash' => null,
                    'approved_at' => null,
                ])->save();
            }

            return $result;
        }, 3);
    }

    public function transition(
        Revision $revision,
        User $actor,
        string $status,
        ?string $reviewNotes,
        bool $isAuthor,
        bool $isReviewer,
    ): Revision {
        return DB::transaction(function () use ($revision, $actor, $status, $reviewNotes, $isAuthor, $isReviewer): Revision {
            $locked = Revision::query()->lockForUpdate()->findOrFail($revision->id);
            $allowedFrom = match ($status) {
                'in_review' => ['draft', 'changes_requested'],
                'changes_requested' => ['in_review'],
                'published' => ['draft', 'in_review', 'suspended'],
                'suspended' => ['published'],
                default => [],
            };
            if (! in_array($locked->status, $allowedFrom, true)) {
                throw ValidationException::withMessages(['status' => 'Transição de situação inválida para esta revisão.']);
            }
            $independentReviewer = $isReviewer && (int) $actor->id !== (int) $locked->author_id;
            $canPublish = $status === 'published'
                && (($locked->status === 'draft' && ($isAuthor || $isReviewer))
                    || (in_array($locked->status, ['in_review', 'suspended'], true) && $independentReviewer));
            abort_unless(($status === 'in_review' && $isAuthor)
                || $canPublish
                || ($status === 'changes_requested' && $independentReviewer)
                || ($status === 'suspended' && $independentReviewer), 403);

            if ($status === 'published' && ! $locked->activeItems()->exists()) {
                throw ValidationException::withMessages(['status' => 'Adicione ao menos um item ativo antes de publicar.']);
            }

            $recordsReview = $status === 'suspended'
                || $status === 'changes_requested'
                || ($status === 'published' && $locked->status !== 'draft');
            $values = [
                'status' => $status,
                'review_notes' => $reviewNotes,
                'reviewed_by' => $status === 'in_review' || ($status === 'published' && $locked->status === 'draft')
                    ? null
                    : ($recordsReview && $isReviewer ? $actor->id : $locked->reviewed_by),
                'published_at' => $status === 'published' ? ($locked->published_at ?: now()) : $locked->published_at,
            ];
            if ($status === 'published') {
                $values['approved_content_hash'] = $this->contentHash($locked);
                $values['approved_at'] = now();
            }
            $locked->update($values);

            return $locked->fresh();
        }, 3);
    }

    public function contentHash(Revision $revision): string
    {
        $revision->load(['activeItems', 'schoolClasses:id']);
        $payload = [
            'schema_version' => 1,
            'revision' => $revision->only([
                'title', 'description', 'discipline_id', 'is_required', 'timing', 'block_exam',
                'available_at', 'due_at', 'max_attempts', 'feedback_mode', 'gamification_enabled',
            ]),
            'class_ids' => $revision->schoolClasses->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all(),
            'items' => $revision->activeItems->map(fn ($item): array => $item->only([
                'type', 'order', 'prompt', 'content', 'solution', 'explanation', 'hints', 'difficulty', 'points', 'is_active',
            ]))->values()->all(),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
