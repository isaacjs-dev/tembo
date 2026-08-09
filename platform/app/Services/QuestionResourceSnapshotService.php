<?php

namespace App\Services;

use App\Models\Question;

class QuestionResourceSnapshotService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function forQuestion(Question $question, bool $includeStorageReference = false): array
    {
        $question->loadMissing([
            'resourceLinks.resource',
            'resourceLinks.version',
        ]);

        return $question->resourceLinks
            ->map(function ($link) use ($includeStorageReference): array {
                if (! $link->resource
                    || ! $link->version
                    || (int) $link->version->question_resource_id !== (int) $link->question_resource_id) {
                    throw new \LogicException('Vínculo de recurso de questão inconsistente; o snapshot foi interrompido.');
                }
                $version = $link->version;
                $snapshot = [
                    'resource_id' => (int) $link->question_resource_id,
                    'resource_version_id' => (int) $version->id,
                    'version_number' => (int) $version->version_number,
                    'title' => (string) ($version->content['title'] ?? $link->resource->title),
                    'type' => (string) $link->resource->type,
                    'content' => $version->content,
                    'is_required' => (bool) $link->is_required,
                    'sort_order' => (int) $link->sort_order,
                    'file' => [
                        'mime_type' => $version->mime_type,
                        'size_bytes' => $version->size_bytes,
                        'sha256' => $version->sha256,
                    ],
                ];

                if ($includeStorageReference && $version->storage_disk && $version->storage_path) {
                    $snapshot['file']['storage_disk'] = $version->storage_disk;
                    $snapshot['file']['storage_path'] = $version->storage_path;
                }

                return $snapshot;
            })
            ->values()
            ->all();
    }
}
