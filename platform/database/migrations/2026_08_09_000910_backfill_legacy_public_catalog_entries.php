<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfillQuestions();
        $this->backfillResources();
    }

    public function down(): void
    {
        // Registros de moderação podem receber denúncias depois do backfill.
        // O rollback da migration estrutural 000900 remove o domínio completo;
        // este passo isolado preserva o histórico operacional já criado.
    }

    private function backfillQuestions(): void
    {
        DB::table('questions')
            ->where('visibility_scope', 'platform_public')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(200, function ($questions): void {
                foreach ($questions as $question) {
                    $this->insertLegacy(
                        'App\\Models\\Question',
                        $question,
                        [
                            'kind' => 'question',
                            'source_id' => $question->id,
                            'type' => $question->type,
                            'content' => $this->decodeJson($question->content),
                            'knowledge_area_id' => $question->knowledge_area_id,
                            'discipline_id' => $question->discipline_id,
                            'level' => $question->level,
                            'stage' => $question->stage,
                            'grade' => $question->grade,
                            'bncc_skill_ids' => [],
                            'custom_skill_ids' => [],
                            'resources' => [],
                            'legacy_backfill' => true,
                        ],
                    );
                }
            }, 'id');
    }

    private function backfillResources(): void
    {
        DB::table('question_resources')
            ->where('visibility_scope', 'platform_public')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(200, function ($resources): void {
                foreach ($resources as $resource) {
                    $version = DB::table('question_resource_versions')
                        ->where('question_resource_id', $resource->id)
                        ->orderByDesc('version_number')
                        ->first();
                    $snapshotVersion = $version ? [
                        'version_number' => $version->version_number,
                        'content' => $this->decodeJson($version->content),
                        'content_hash' => $version->content_hash,
                        'storage_disk' => $version->storage_disk,
                        'storage_path' => $version->storage_path,
                        'mime_type' => $version->mime_type,
                        'size_bytes' => $version->size_bytes,
                        'sha256' => $version->sha256,
                    ] : null;

                    $this->insertLegacy(
                        'App\\Models\\QuestionResource',
                        $resource,
                        [
                            'kind' => 'resource',
                            'source_id' => $resource->id,
                            'title' => $resource->title,
                            'type' => $resource->type,
                            'version' => $snapshotVersion,
                            'legacy_backfill' => true,
                        ],
                    );
                }
            }, 'id');
    }

    /** @param array<string, mixed> $snapshot */
    private function insertLegacy(string $model, object $source, array $snapshot): void
    {
        $idempotencyKey = 'legacy-backfill:'.$model.':'.$source->id;
        if (DB::table('public_catalog_submissions')->where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }

        $encoded = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $fingerprint = $this->fingerprint($snapshot);
        $createdAt = $source->created_at ?? now();
        $submissionId = DB::table('public_catalog_submissions')->insertGetId([
            'organization_id' => $source->organization_id,
            'submitter_id' => $source->owner_id,
            'submittable_type' => $model,
            'submittable_id' => $source->id,
            'status' => 'approved',
            'content_hash' => $fingerprint,
            'similarity_key' => $fingerprint,
            'snapshot_json' => $encoded,
            'rights_basis' => 'legacy_import',
            'rights_notes' => 'Registro público anterior ao fluxo de moderação.',
            'terms_version' => 'legacy',
            'rights_confirmed_at' => $createdAt,
            'idempotency_key' => $idempotencyKey,
            'submitted_at' => $createdAt,
            'reviewed_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        DB::table('public_catalog_entries')->insert([
            'submission_id' => $submissionId,
            'organization_id' => $source->organization_id,
            'publisher_id' => $source->owner_id,
            'entryable_type' => $model,
            'entryable_id' => $source->id,
            'fingerprint' => $fingerprint,
            'canonical_fingerprint' => null,
            'status' => 'published',
            'published_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        DB::table('public_catalog_submission_events')->insert([
            'submission_id' => $submissionId,
            'actor_id' => null,
            'action' => 'legacy_imported',
            'from_status' => null,
            'to_status' => 'approved',
            'reason' => 'Importado como publicação legada durante a ativação do fluxo moderado.',
            'metadata' => json_encode(['legacy' => true], JSON_THROW_ON_ERROR),
            'created_at' => $createdAt,
        ]);
    }

    /** @return array<string, mixed>|array<int, mixed> */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $snapshot */
    private function fingerprint(array $snapshot): string
    {
        unset($snapshot['source_id'], $snapshot['legacy_backfill']);

        if (($snapshot['kind'] ?? null) === 'resource') {
            $version = (array) ($snapshot['version'] ?? []);
            $snapshot['version'] = array_intersect_key($version, array_flip([
                'content', 'content_hash', 'mime_type', 'size_bytes', 'sha256',
            ]));
        }

        $encoded = json_encode($this->normalize($snapshot), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return hash('sha256', $encoded);
    }

    private function normalize(mixed $value): mixed
    {
        if (is_string($value)) {
            return preg_replace('/\s+/u', ' ', mb_strtolower(trim($value)));
        }

        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }
};
