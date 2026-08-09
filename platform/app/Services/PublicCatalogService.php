<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BNCcNode;
use App\Models\CustomSkill;
use App\Models\Discipline;
use App\Models\KnowledgeArea;
use App\Models\PublicCatalogEntry;
use App\Models\PublicCatalogReport;
use App\Models\PublicCatalogReputationEvent;
use App\Models\PublicCatalogSubmission;
use App\Models\Question;
use App\Models\QuestionResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicCatalogService
{
    public const TARGET_TYPES = ['question', 'resource'];

    public const RIGHTS_BASES = ['own_work', 'public_domain', 'licensed', 'authorized'];

    public const REPORT_REASONS = ['copyright', 'incorrect', 'inappropriate', 'duplicate', 'privacy', 'spam', 'other'];

    public function resolveOwnedTarget(User $user, string $type, int $id): Question|QuestionResource
    {
        return match ($type) {
            'question' => Question::query()
                ->where('organization_id', $user->organization_id)
                ->where('owner_id', $user->id)
                ->findOrFail($id),
            'resource' => QuestionResource::query()
                ->where('organization_id', $user->organization_id)
                ->where('owner_id', $user->id)
                ->where('status', 'active')
                ->findOrFail($id),
            default => abort(404),
        };
    }

    public function resolvePublicTarget(User $user, string $type, int $id): Question|QuestionResource
    {
        return match ($type) {
            'question' => app(QuestionLibraryService::class)->visibleTo($user)
                ->where('visibility_scope', 'platform_public')->findOrFail($id),
            'resource' => app(QuestionResourceService::class)->visibleTo($user)
                ->where('visibility_scope', 'platform_public')->findOrFail($id),
            default => abort(404),
        };
    }

    /** @param array{rights_basis:string,rights_notes?:?string,attribution?:?string,evidence_url?:?string,idempotency_key:string} $data */
    public function submit(Question|QuestionResource $target, User $actor, array $data): PublicCatalogSubmission
    {
        $this->assertOwned($target, $actor);
        if ($target->visibility_scope === 'platform_public') {
            throw ValidationException::withMessages(['target' => 'O item já pertence ao catálogo público.']);
        }

        $snapshot = $this->snapshot($target);
        $contentHash = $this->fingerprint($snapshot);
        $activeFingerprint = hash('sha256', $target::class.'|'.$contentHash);
        $similarityKey = $this->similarityKey($snapshot);

        try {
            $submission = DB::transaction(function () use ($target, $actor, $data, $snapshot, $contentHash, $activeFingerprint, $similarityKey): PublicCatalogSubmission {
                User::query()->lockForUpdate()->findOrFail($actor->id);

                $existingRetry = PublicCatalogSubmission::query()
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->first();
                if ($existingRetry) {
                    abort_unless((int) $existingRetry->submitter_id === (int) $actor->id
                        && $existingRetry->submittable_type === $target::class
                        && (int) $existingRetry->submittable_id === (int) $target->getKey(), 409);

                    return $existingRetry;
                }

                $active = $target->publicCatalogSubmissions()
                    ->whereIn('status', PublicCatalogSubmission::ACTIVE_STATUSES)
                    ->exists();
                if ($active) {
                    throw ValidationException::withMessages(['target' => 'Já existe uma submissão ativa para este item.']);
                }

                $pendingCount = PublicCatalogSubmission::query()
                    ->where('submitter_id', $actor->id)
                    ->whereIn('status', PublicCatalogSubmission::ACTIVE_STATUSES)
                    ->count();
                if ($pendingCount >= (int) config('public_catalog.max_pending_submissions')) {
                    throw ValidationException::withMessages(['target' => 'Limite de submissões pendentes atingido. Aguarde a moderação.']);
                }

                $dailyCount = PublicCatalogSubmission::query()
                    ->where('submitter_id', $actor->id)
                    ->where('submitted_at', '>=', now()->subDay())
                    ->count();
                if ($dailyCount >= (int) config('public_catalog.daily_submission_limit')) {
                    throw ValidationException::withMessages(['target' => 'Limite diário de submissões atingido.']);
                }

                $duplicate = PublicCatalogSubmission::query()
                    ->where('submittable_type', $target::class)
                    ->where('content_hash', $contentHash)
                    ->whereIn('status', ['pending', 'in_review', 'approved'])
                    ->first();
                if ($duplicate) {
                    throw ValidationException::withMessages(['target' => 'Conteúdo equivalente já está em análise ou publicado.']);
                }

                $candidates = PublicCatalogSubmission::query()
                    ->where('submittable_type', $target::class)
                    ->where('similarity_key', $similarityKey)
                    ->whereIn('status', ['pending', 'in_review', 'approved'])
                    ->latest('submitted_at')
                    ->limit(5)
                    ->pluck('id')
                    ->all();
                $previous = $target->publicCatalogSubmissions()->latest('submitted_at')->first();

                $submission = PublicCatalogSubmission::query()->create([
                    'organization_id' => $target->organization_id,
                    'submitter_id' => $actor->id,
                    'submittable_type' => $target::class,
                    'submittable_id' => $target->getKey(),
                    'previous_submission_id' => $previous?->id,
                    'status' => 'pending',
                    'content_hash' => $contentHash,
                    'active_fingerprint' => $activeFingerprint,
                    'similarity_key' => $similarityKey,
                    'snapshot_json' => $snapshot,
                    'rights_basis' => $data['rights_basis'],
                    'rights_notes' => $data['rights_notes'] ?? null,
                    'terms_version' => config('public_catalog.terms_version'),
                    'attribution' => $data['attribution'] ?? null,
                    'evidence_url' => $data['evidence_url'] ?? null,
                    'rights_confirmed_at' => now(),
                    'idempotency_key' => $data['idempotency_key'],
                    'duplicate_candidates_json' => $candidates,
                    'submitted_at' => now(),
                ]);
                $this->event($submission, $actor, 'submitted', null, 'pending');

                return $submission;
            }, 3);
        } catch (QueryException $exception) {
            $retry = PublicCatalogSubmission::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($retry && (int) $retry->submitter_id === (int) $actor->id
                && $retry->submittable_type === $target::class
                && (int) $retry->submittable_id === (int) $target->getKey()) {
                return $retry;
            }
            if (PublicCatalogSubmission::query()->where('active_fingerprint', $activeFingerprint)->exists()) {
                throw ValidationException::withMessages(['target' => 'Conteúdo equivalente já está em análise.']);
            }

            throw $exception;
        }

        AuditLog::log('public_catalog_submitted', PublicCatalogSubmission::class, $submission->id, [
            'organization_id' => $submission->organization_id,
            'subject_type' => $submission->submittable_type,
            'subject_id' => $submission->submittable_id,
            'content_hash' => $submission->content_hash,
            'terms_version' => $submission->terms_version,
        ]);

        return $submission;
    }

    public function withdraw(PublicCatalogSubmission $submission, User $actor, string $reason): void
    {
        abort_unless((int) $submission->submitter_id === (int) $actor->id
            && (int) $submission->organization_id === (int) $actor->organization_id, 404);

        DB::transaction(function () use ($submission, $actor, $reason): void {
            $locked = PublicCatalogSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            abort_unless((int) $locked->submitter_id === (int) $actor->id
                && (int) $locked->organization_id === (int) $actor->organization_id, 404);
            abort_unless(in_array($locked->status, PublicCatalogSubmission::ACTIVE_STATUSES, true), 409);
            $from = $locked->status;
            $locked->update([
                'status' => 'withdrawn',
                'active_fingerprint' => null,
                'decision_reason' => $reason,
                'reviewed_at' => now(),
            ]);
            $this->event($locked, $actor, 'withdrawn', $from, 'withdrawn', $reason);
        }, 3);

        AuditLog::log('public_catalog_withdrawn', PublicCatalogSubmission::class, $submission->id, [
            'organization_id' => $submission->organization_id,
            'reason_recorded' => true,
        ]);
    }

    public function startReview(PublicCatalogSubmission $submission, User $moderator): void
    {
        $this->assertModerator($moderator, $submission);

        DB::transaction(function () use ($submission, $moderator): void {
            $locked = PublicCatalogSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            abort_unless($locked->status === 'pending', 409);
            $locked->update([
                'status' => 'in_review',
                'moderator_id' => $moderator->id,
                'review_started_at' => now(),
            ]);
            $this->event($locked, $moderator, 'review_started', 'pending', 'in_review');
        }, 3);
    }

    public function decide(
        PublicCatalogSubmission $submission,
        User $moderator,
        string $decision,
        string $reason,
        ?int $duplicateOf = null,
    ): PublicCatalogSubmission {
        $this->assertModerator($moderator, $submission);
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);

        $result = DB::transaction(function () use ($submission, $moderator, $decision, $reason, $duplicateOf): PublicCatalogSubmission {
            $locked = PublicCatalogSubmission::query()
                ->with(['submittable', 'entry'])
                ->lockForUpdate()
                ->findOrFail($submission->id);
            abort_unless(in_array($locked->status, PublicCatalogSubmission::ACTIVE_STATUSES, true), 409);
            abort_if((int) $locked->submitter_id === (int) $moderator->id, 403);
            $from = $locked->status;

            if ($decision === 'approved') {
                abort_if(! $locked->submittable || $locked->submittable->trashed(), 409, 'A origem foi removida durante a análise.');
                if (PublicCatalogEntry::query()->where('canonical_fingerprint', $locked->content_hash)->exists()) {
                    throw ValidationException::withMessages(['decision' => 'Já existe uma publicação canônica com o mesmo conteúdo.']);
                }
                $published = $this->publishSnapshot($locked, $moderator);
                PublicCatalogEntry::query()->create([
                    'submission_id' => $locked->id,
                    'organization_id' => $locked->organization_id,
                    'publisher_id' => $locked->submitter_id,
                    'entryable_type' => $published::class,
                    'entryable_id' => $published->getKey(),
                    'fingerprint' => $locked->content_hash,
                    'canonical_fingerprint' => $locked->content_hash,
                    'status' => 'published',
                    'published_at' => now(),
                ]);
                $this->recordReputation($locked->submitter_id, 'submission_approved', $locked, "catalog:approved:{$locked->id}");
            } elseif ($duplicateOf !== null) {
                abort_unless(PublicCatalogSubmission::query()->whereKey($duplicateOf)->where('status', 'approved')->exists(), 422);
            }

            $locked->update([
                'status' => $decision,
                'active_fingerprint' => null,
                'moderator_id' => $moderator->id,
                'decision_reason' => $reason,
                'duplicate_of_submission_id' => $decision === 'rejected' ? $duplicateOf : null,
                'review_started_at' => $locked->review_started_at ?? now(),
                'reviewed_at' => now(),
            ]);
            $this->event($locked, $moderator, $decision, $from, $decision, $reason, [
                'duplicate_of_submission_id' => $duplicateOf,
            ]);
            if ($decision === 'rejected') {
                $this->recordReputation($locked->submitter_id, 'submission_rejected', $locked, "catalog:rejected:{$locked->id}");
            }

            return $locked->fresh(['entry', 'events']);
        }, 3);

        AuditLog::log("public_catalog_{$decision}", PublicCatalogSubmission::class, $result->id, [
            'organization_id' => $result->organization_id,
            'reason_recorded' => true,
            'published_entry_id' => $result->entry?->id,
        ]);

        return $result;
    }

    /** @param array{reason_code:string,details:string,idempotency_key:string} $data */
    public function report(Question|QuestionResource $target, User $reporter, array $data): PublicCatalogReport
    {
        abort_if((int) $target->owner_id === (int) $reporter->id, 422, 'O autor não pode denunciar o próprio conteúdo.');
        $entry = $this->entryForPublicTarget($target);

        $report = DB::transaction(function () use ($entry, $reporter, $data): PublicCatalogReport {
            User::query()->lockForUpdate()->findOrFail($reporter->id);
            $retry = PublicCatalogReport::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($retry) {
                abort_unless((int) $retry->reporter_id === (int) $reporter->id
                    && (int) $retry->public_catalog_entry_id === (int) $entry->id, 409);

                return $retry;
            }

            $daily = PublicCatalogReport::query()
                ->where('reporter_id', $reporter->id)
                ->where('created_at', '>=', now()->subDay())
                ->count();
            if ($daily >= (int) config('public_catalog.daily_report_limit')) {
                throw ValidationException::withMessages(['details' => 'Limite diário de denúncias atingido.']);
            }
            if (PublicCatalogReport::query()
                ->where('reporter_id', $reporter->id)
                ->where('public_catalog_entry_id', $entry->id)
                ->whereIn('status', ['open', 'in_review'])
                ->exists()) {
                throw ValidationException::withMessages(['details' => 'Você já possui uma denúncia em análise para este item.']);
            }

            return PublicCatalogReport::query()->create([
                'organization_id' => $reporter->organization_id,
                'reporter_id' => $reporter->id,
                'public_catalog_entry_id' => $entry->id,
                'reason_code' => $data['reason_code'],
                'details' => $data['details'],
                'status' => 'open',
                'idempotency_key' => $data['idempotency_key'],
            ]);
        }, 3);

        AuditLog::log('public_catalog_reported', PublicCatalogReport::class, $report->id, [
            'organization_id' => $report->organization_id,
            'entry_id' => $entry->id,
            'target_type' => $target::class,
            'target_id' => $target->getKey(),
            'reason_code' => $report->reason_code,
        ]);

        return $report;
    }

    public function resolveReport(PublicCatalogReport $report, User $moderator, string $decision, string $resolution): void
    {
        abort_unless($moderator->hasRole('global_admin'), 403);
        abort_unless(in_array($decision, ['upheld', 'dismissed'], true), 422);

        DB::transaction(function () use ($report, $moderator, $decision, $resolution): void {
            $locked = PublicCatalogReport::query()->lockForUpdate()->findOrFail($report->id);
            abort_unless(in_array($locked->status, ['open', 'in_review'], true), 409);
            $entry = PublicCatalogEntry::query()->lockForUpdate()->findOrFail($locked->public_catalog_entry_id);
            $submission = PublicCatalogSubmission::query()->lockForUpdate()->findOrFail($entry->submission_id);
            abort_if((int) $moderator->id === (int) $locked->reporter_id
                || (int) $moderator->id === (int) $entry->publisher_id
                || (int) $moderator->id === (int) $submission->submitter_id, 403);

            if ($decision === 'upheld' && $entry->status === 'published') {
                $entry->update([
                    'status' => 'suspended',
                    'suspended_at' => now(),
                    'suspended_by' => $moderator->id,
                    'suspension_reason' => $resolution,
                ]);
                $submission->update([
                    'status' => 'removed',
                    'moderator_id' => $moderator->id,
                    'decision_reason' => $resolution,
                    'reviewed_at' => now(),
                ]);
                $this->event($submission, $moderator, 'removed', 'approved', 'removed', $resolution, [
                    'report_id' => $locked->id,
                ]);
                $this->recordReputation($entry->publisher_id, 'content_removed', $locked, "catalog:removed:{$locked->id}:publisher");
                $this->recordReputation($locked->reporter_id, 'report_upheld', $locked, "catalog:report:{$locked->id}:upheld");
            } elseif ($decision === 'dismissed') {
                $this->recordReputation($locked->reporter_id, 'report_dismissed', $locked, "catalog:report:{$locked->id}:dismissed");
            }

            $locked->update([
                'status' => $decision,
                'moderator_id' => $moderator->id,
                'resolution' => $resolution,
                'reviewed_at' => now(),
            ]);
        }, 3);

        AuditLog::log("public_catalog_report_{$decision}", PublicCatalogReport::class, $report->id, [
            'organization_id' => $report->organization_id,
            'resolution_recorded' => true,
        ]);
    }

    /** @return array{score:int,events:int} */
    public function reputation(User $user): array
    {
        $query = PublicCatalogReputationEvent::query()->where('user_id', $user->id);

        return ['score' => (int) (clone $query)->sum('points'), 'events' => (clone $query)->count()];
    }

    /** @return array<string, mixed> */
    public function snapshot(Question|QuestionResource $target): array
    {
        if ($target instanceof QuestionResource) {
            $target->loadMissing('currentVersion');
            $version = $target->currentVersion;
            if (! $version) {
                throw ValidationException::withMessages(['target' => 'O recurso não possui versão utilizável.']);
            }

            return [
                'kind' => 'resource',
                'source_id' => $target->id,
                'title' => $target->title,
                'type' => $target->type,
                'version' => Arr::only($version->toArray(), [
                    'version_number', 'content', 'content_hash', 'storage_disk', 'storage_path',
                    'mime_type', 'size_bytes', 'sha256',
                ]),
            ];
        }

        $target->loadMissing([
            'bnccSkills:id',
            'customSkills:id',
            'resourceLinks.version',
            'resourceLinks.resource.publicCatalogEntries',
        ]);
        $resources = $target->resourceLinks->map(function ($link): array {
            $resource = $link->resource;
            if (! $resource || ! $link->version
                || $resource->visibility_scope !== 'platform_public'
                || ! $this->entryIsPublishedOrLegacy($resource)) {
                throw ValidationException::withMessages([
                    'target' => 'Todos os recursos obrigatórios precisam estar publicados antes da questão.',
                ]);
            }

            return [
                'resource_id' => $resource->id,
                'version_id' => $link->question_resource_version_id,
                'version_hash' => $link->version->content_hash,
                'is_required' => (bool) $link->is_required,
                'sort_order' => (int) $link->sort_order,
            ];
        })->values()->all();

        return [
            'kind' => 'question',
            'source_id' => $target->id,
            'type' => $target->type,
            'content' => $target->content,
            'knowledge_area_id' => $target->knowledge_area_id,
            'discipline_id' => $target->discipline_id,
            'level' => $target->level,
            'stage' => $target->stage,
            'grade' => $target->grade,
            'bncc_skill_ids' => $target->bnccSkills->modelKeys(),
            'custom_skill_ids' => $target->customSkills->modelKeys(),
            'resources' => $resources,
        ];
    }

    private function publishSnapshot(PublicCatalogSubmission $submission, User $moderator): Question|QuestionResource
    {
        $snapshot = $submission->snapshot_json;
        if (($snapshot['kind'] ?? null) === 'resource') {
            $version = $snapshot['version'];
            $resource = QuestionResource::query()->create([
                'organization_id' => $submission->organization_id,
                'owner_id' => $submission->submitter_id,
                'source_resource_id' => $submission->submittable_id,
                'title' => $snapshot['title'],
                'type' => $snapshot['type'],
                'visibility_scope' => 'platform_public',
                'status' => 'active',
            ]);
            $resource->versions()->create([
                ...$version,
                'version_number' => 1,
                'created_by' => $moderator->id,
            ]);

            return $resource;
        }

        $resources = $this->validatedSnapshotResources($snapshot);

        $question = Question::query()->create([
            'organization_id' => $submission->organization_id,
            'owner_id' => $submission->submitter_id,
            'source_question_id' => $submission->submittable_id,
            'type' => $snapshot['type'],
            'content' => $snapshot['content'],
            'visibility_scope' => 'platform_public',
            'knowledge_area_id' => $this->validTaxonomyId(KnowledgeArea::class, $snapshot['knowledge_area_id'] ?? null, $submission->organization_id),
            'discipline_id' => $this->validTaxonomyId(Discipline::class, $snapshot['discipline_id'] ?? null, $submission->organization_id),
            'level' => $snapshot['level'] ?? null,
            'stage' => $snapshot['stage'] ?? null,
            'grade' => $snapshot['grade'] ?? null,
        ]);
        $question->bnccSkills()->sync(BNCcNode::query()->whereKey($snapshot['bncc_skill_ids'] ?? [])->pluck('id'));
        $question->customSkills()->sync(CustomSkill::query()
            ->where('organization_id', $submission->organization_id)
            ->whereKey($snapshot['custom_skill_ids'] ?? [])
            ->pluck('id'));
        foreach ($resources as $resource) {
            $question->resources()->attach($resource['resource_id'], [
                'question_resource_version_id' => $resource['version_id'],
                'is_required' => $resource['is_required'],
                'sort_order' => $resource['sort_order'],
            ]);
        }

        return $question;
    }

    private function entryForPublicTarget(Question|QuestionResource $target): PublicCatalogEntry
    {
        abort_unless($target->visibility_scope === 'platform_public', 404);
        $entry = $target->publicCatalogEntries()->where('status', 'published')->latest('published_at')->first();
        if ($entry) {
            return $entry;
        }
        abort_unless($target->publicCatalogEntries()->doesntExist(), 404);

        return DB::transaction(function () use ($target): PublicCatalogEntry {
            $locked = $target->newQuery()->lockForUpdate()->findOrFail($target->id);
            $existing = $locked->publicCatalogEntries()->first();
            if ($existing) {
                abort_unless($existing->status === 'published', 404);

                return $existing;
            }
            $snapshot = $this->snapshot($locked);
            $fingerprint = $this->fingerprint($snapshot);
            $submission = PublicCatalogSubmission::query()->create([
                'organization_id' => $locked->organization_id,
                'submitter_id' => $locked->owner_id,
                'submittable_type' => $locked::class,
                'submittable_id' => $locked->id,
                'status' => 'approved',
                'content_hash' => $fingerprint,
                'similarity_key' => $this->similarityKey($snapshot),
                'snapshot_json' => $snapshot,
                'rights_basis' => 'legacy_import',
                'rights_notes' => 'Registro público anterior ao fluxo de moderação.',
                'terms_version' => 'legacy',
                'rights_confirmed_at' => $locked->created_at ?? now(),
                'idempotency_key' => 'legacy-public:'.get_class($locked).':'.$locked->id,
                'submitted_at' => $locked->created_at ?? now(),
                'reviewed_at' => now(),
            ]);

            return PublicCatalogEntry::query()->create([
                'submission_id' => $submission->id,
                'organization_id' => $locked->organization_id,
                'publisher_id' => $locked->owner_id,
                'entryable_type' => $locked::class,
                'entryable_id' => $locked->id,
                'fingerprint' => $fingerprint,
                'canonical_fingerprint' => null,
                'status' => 'published',
                'published_at' => $locked->created_at ?? now(),
            ]);
        }, 3);
    }

    private function entryIsPublishedOrLegacy(QuestionResource $resource): bool
    {
        return $resource->publicCatalogEntries->isEmpty()
            || $resource->publicCatalogEntries->contains('status', 'published');
    }

    /** @param array<string, mixed> $snapshot */
    private function fingerprint(array $snapshot): string
    {
        $portable = Arr::except($snapshot, ['source_id', 'legacy_backfill']);

        if (($portable['kind'] ?? null) === 'resource') {
            $version = (array) ($portable['version'] ?? []);
            $portable['version'] = Arr::only($version, [
                'content', 'content_hash', 'mime_type', 'size_bytes', 'sha256',
            ]);
        }

        if (($portable['kind'] ?? null) === 'question') {
            $portable['resources'] = collect($portable['resources'] ?? [])->map(fn (array $resource): array => Arr::only($resource, [
                'version_hash', 'is_required', 'sort_order',
            ]))->values()->all();
        }

        return hash('sha256', json_encode($this->normalize($portable), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $snapshot */
    private function similarityKey(array $snapshot): string
    {
        $essence = ($snapshot['kind'] ?? null) === 'question'
            ? ['kind' => 'question', 'type' => $snapshot['type'] ?? null, 'statement' => data_get($snapshot, 'content.statement')]
            : ['kind' => 'resource', 'type' => $snapshot['type'] ?? null, 'title' => $snapshot['title'] ?? null, 'body' => data_get($snapshot, 'version.content.body')];

        return hash('sha256', json_encode($this->normalize($essence), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function normalize(mixed $value): mixed
    {
        if (is_string($value)) {
            return preg_replace('/\s+/u', ' ', mb_strtolower(trim($value)));
        }
        if (! is_array($value)) {
            return $value;
        }
        if (Arr::isAssoc($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => $this->normalize($item), $value);
    }

    private function assertOwned(Question|QuestionResource $target, User $actor): void
    {
        abort_unless((int) $target->organization_id === (int) $actor->organization_id
            && (int) $target->owner_id === (int) $actor->id, 404);
    }

    private function assertModerator(User $moderator, PublicCatalogSubmission $submission): void
    {
        abort_unless($moderator->hasRole('global_admin'), 403);
        abort_if((int) $submission->submitter_id === (int) $moderator->id, 403);
    }

    /** @param array<string, mixed> $metadata */
    private function event(
        PublicCatalogSubmission $submission,
        ?User $actor,
        string $action,
        ?string $from,
        string $to,
        ?string $reason = null,
        array $metadata = [],
    ): void {
        $submission->events()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }

    private function recordReputation(int $userId, string $eventKey, Model $source, string $idempotencyKey): void
    {
        PublicCatalogReputationEvent::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'user_id' => $userId,
                'event_key' => $eventKey,
                'points' => (int) config("public_catalog.reputation_points.{$eventKey}", 0),
                'rule_version' => config('public_catalog.reputation_rule_version'),
                'source_type' => $source::class,
                'source_id' => $source->getKey(),
                'metadata' => ['facts_only' => true, 'credits_granted' => false],
                'created_at' => now(),
            ],
        );
    }

    /** @param array<string, mixed> $snapshot
     * @return array<int, array{resource_id:int,version_id:int,version_hash:string,is_required:bool,sort_order:int}>
     */
    private function validatedSnapshotResources(array $snapshot): array
    {
        $validated = [];
        foreach ($snapshot['resources'] ?? [] as $link) {
            $resource = QuestionResource::query()
                ->with('publicCatalogEntries')
                ->whereKey($link['resource_id'] ?? null)
                ->where('visibility_scope', 'platform_public')
                ->where('status', 'active')
                ->first();
            $version = $resource?->versions()->whereKey($link['version_id'] ?? null)->first();
            $expectedHash = (string) ($link['version_hash'] ?? '');

            if (! $resource || ! $version || ! $this->entryIsPublishedOrLegacy($resource)
                || $expectedHash === '' || ! hash_equals($expectedHash, (string) $version->content_hash)) {
                throw ValidationException::withMessages([
                    'decision' => 'Um recurso obrigatório foi removido, suspenso ou alterado durante a análise. Rejeite a submissão ou solicite um novo envio.',
                ]);
            }

            $validated[] = [
                'resource_id' => (int) $resource->id,
                'version_id' => (int) $version->id,
                'version_hash' => $expectedHash,
                'is_required' => (bool) ($link['is_required'] ?? false),
                'sort_order' => (int) ($link['sort_order'] ?? 0),
            ];
        }

        return $validated;
    }

    private function validTaxonomyId(string $model, mixed $id, ?int $organizationId): ?int
    {
        if (! $id) {
            return null;
        }

        return $model::query()->whereKey($id)->where('organization_id', $organizationId)->value('id');
    }
}
