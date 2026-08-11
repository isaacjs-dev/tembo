<?php

namespace App\Services;

use App\Models\Revision;
use App\Models\RevisionAttempt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RevisionAttemptSnapshotService
{
    public function __construct(private readonly RevisionGraderService $grader) {}

    /** @return array<string, mixed> */
    public function build(Revision $revision): array
    {
        $revision->loadMissing('activeItems');

        return [
            'schema_version' => 1,
            'revision' => [
                'id' => $revision->id,
                'title' => $revision->title,
                'description' => $revision->description,
                'feedback_mode' => $revision->feedback_mode,
                'gamification_enabled' => (bool) $revision->gamification_enabled,
                'approved_content_hash' => $revision->approved_content_hash,
            ],
            'items' => $revision->activeItems->map(fn ($item): array => $this->grader->snapshot($item))->values()->all(),
        ];
    }

    /** @param array<string, mixed> $snapshot */
    public function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * Backfill a legacy attempt on first access. Existing response snapshots take
     * precedence over the current item so an already answered question cannot be
     * silently reinterpreted after an upgrade.
     */
    public function ensure(RevisionAttempt $attempt, Revision $revision): RevisionAttempt
    {
        if (is_array($attempt->content_snapshot) && isset($attempt->content_snapshot['schema_version'])) {
            return $attempt;
        }

        return DB::transaction(function () use ($attempt, $revision): RevisionAttempt {
            $lockedRevision = Revision::query()->lockForUpdate()->findOrFail($revision->id);
            $lockedAttempt = RevisionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if (is_array($lockedAttempt->content_snapshot) && isset($lockedAttempt->content_snapshot['schema_version'])) {
                return $lockedAttempt;
            }

            $snapshot = $this->build($lockedRevision);
            $responses = $lockedAttempt->responses()->whereNotNull('item_snapshot')->lockForUpdate()->get();
            $answeredSnapshots = $responses->mapWithKeys(function ($response): array {
                $key = (int) ($response->snapshot_item_key
                    ?: $response->revision_item_id
                    ?: data_get($response->item_snapshot, 'id', 0));
                if ($key > 0 && ! $response->snapshot_item_key) {
                    $response->update(['snapshot_item_key' => $key]);
                }

                return $key > 0 && is_array($response->item_snapshot) ? [$key => $response->item_snapshot] : [];
            });
            $snapshot['items'] = collect($snapshot['items'])->map(function (array $item) use ($answeredSnapshots): array {
                $response = $answeredSnapshots->get((int) ($item['id'] ?? 0));

                return is_array($response?->item_snapshot) ? $response->item_snapshot : $item;
            })->keyBy(fn (array $item): int => (int) ($item['id'] ?? 0));
            foreach ($answeredSnapshots as $key => $answeredSnapshot) {
                $snapshot['items']->put((int) $key, $answeredSnapshot);
            }
            $snapshot['items'] = $snapshot['items']->values()->all();
            $lockedAttempt->update([
                'content_snapshot' => $snapshot,
                'snapshot_hash' => $this->hash($snapshot),
            ]);

            return $lockedAttempt->fresh();
        }, 3);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function items(RevisionAttempt $attempt): Collection
    {
        return collect(data_get($attempt->content_snapshot, 'items', []));
    }

    /** @return array<string, mixed>|null */
    public function item(RevisionAttempt $attempt, int $key): ?array
    {
        return $this->items($attempt)->first(fn (array $item): bool => (int) ($item['id'] ?? 0) === $key);
    }
}
