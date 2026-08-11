<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamCopy;
use App\Models\QuestionResourceVersion;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CanonicalPrintDocumentService
{
    public function __construct(
        private readonly AppearanceTemplateService $appearances,
        private readonly AppearanceDefinitionSchema $appearanceSchema,
        private readonly AppearanceTokenResolver $tokens,
        private readonly ExamQuestionSnapshotService $questionSnapshots,
        private readonly AssessmentPrintContextService $printContexts,
    ) {}

    /** @return array<string, mixed> */
    public function preview(Exam $exam, array $options = []): array
    {
        $exam->loadMissing([
            'author', 'organization', 'discipline', 'questions.discipline',
            'questions.resourceLinks.resource', 'questions.resourceLinks.version',
        ]);
        $snapshot = $this->questionSnapshots->fromQuestions($exam->questions);
        $appearance = $this->appearances->snapshotForExam($exam, null, $options);
        $appearance['render_context'] = $this->printContexts->snapshot($exam);

        return $this->build(
            $exam,
            null,
            $snapshot,
            array_column($snapshot, 'id'),
            $this->identityOptionMaps($snapshot),
            $appearance,
            'live_draft_preview',
        );
    }

    /** @return array<string, mixed> */
    public function copy(Exam $exam, ExamCopy $copy): array
    {
        abort_unless((int) $copy->exam_id === (int) $exam->id, 409, 'Cópia incompatível com a Avaliação.');
        $exam->loadMissing(['author', 'organization', 'discipline']);
        $copy->loadMissing(['student.studentProfile', 'schoolClass']);
        $questions = $copy->question_snapshot;
        if (! is_array($questions) || $questions === []) {
            throw new RuntimeException('A cópia não possui snapshot canônico de questões.');
        }

        return $this->build(
            $exam,
            $copy,
            $questions,
            $copy->questions_map ?? [],
            $copy->options_map ?? [],
            is_array($copy->template_snapshot) ? $copy->template_snapshot : [],
            'immutable_exam_copy',
        );
    }

    /** @return array<string, mixed> */
    private function build(
        Exam $exam,
        ?ExamCopy $copy,
        array $questionSnapshot,
        array $questionMap,
        array $optionMaps,
        array $appearance,
        string $source,
    ): array {
        $appearance = $this->appearanceWithLegacyFallback($appearance);
        $snapshottedContext = data_get($appearance, 'render_context');
        $context = is_array($snapshottedContext)
            ? $snapshottedContext
            : $this->printContexts->snapshot($exam, $copy?->student, $copy?->schoolClass, $copy?->copy_number);
        if ($copy && ! is_array($snapshottedContext)) {
            $source = 'legacy_copy_with_live_context';
        }
        $layoutDefinition = $this->appearanceSchema->normalize(
            'assessment_layout',
            data_get($appearance, 'assessment_layout.definition', []),
        );
        $headerDefinition = $this->appearanceSchema->normalize(
            'assessment_header',
            data_get($appearance, 'assessment_header.definition', []),
        );
        $layout = $this->layout($layoutDefinition);
        $questions = array_map(function (array $question) use ($layout): array {
            $estimatedLength = mb_strlen($question['statement'])
                + array_sum(array_map(fn (array $option): int => mb_strlen($option['text']), $question['options']));
            $question['avoid_break'] = $layout['avoid_break_inside'] && $estimatedLength <= 1800;

            return $question;
        }, $this->questions($questionSnapshot, $questionMap, $optionMaps));
        if ($layout['columns'] === 2 && collect($questions)->contains(fn (array $question): bool => ! $question['avoid_break'])) {
            $layout['columns'] = 1;
            $layout['columns_fallback_reason'] = 'oversized_question';
        }
        $preferences = $this->preferences(data_get($appearance, 'print_preferences', []));

        $hashPayload = $this->canonical([
            'questions' => $questionSnapshot,
            'map' => $questionMap,
            'options' => $optionMaps,
            'appearance' => $appearance,
            'context' => $context,
        ]);
        $documentHash = hash('sha256', json_encode(
            $hashPayload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        return [
            'schema_version' => 1,
            'source' => $source,
            'source_hash' => $documentHash,
            'document_hash' => $documentHash,
            'title' => (string) data_get($context, 'assessment.title', $exam->title),
            'copy_number' => $copy?->copy_number,
            'validation_hash' => $copy?->validation_hash,
            'layout' => $layout,
            'preferences' => $preferences,
            'header' => [
                'template_id' => data_get($appearance, 'assessment_header.template_id'),
                'version' => data_get($appearance, 'assessment_header.version'),
                'elements' => $this->tokens->elements($headerDefinition, $context),
                'height_mm' => (float) $headerDefinition['height_mm'],
            ],
            'context' => $context,
            'questions' => $questions,
            'total_points' => array_sum(array_column($questions, 'points')),
            'appearance_snapshot' => $appearance,
        ];
    }

    /** @return array<string, mixed> */
    private function layout(array $definition): array
    {
        $page = is_array($definition['page'] ?? null) ? $definition['page'] : [];
        $questions = is_array($definition['questions'] ?? null) ? $definition['questions'] : [];
        $margins = is_array($page['margins_mm'] ?? null) ? array_values($page['margins_mm']) : [15, 15, 15, 15];
        if (count($margins) !== 4) {
            $margins = [15, 15, 15, 15];
        }
        $margins = array_map(fn (mixed $value): float => max(5, min(30, (float) $value)), $margins);

        return [
            'page_size' => ($page['size'] ?? 'A4') === 'A4' ? 'A4' : 'A4',
            'orientation' => in_array($page['orientation'] ?? null, ['portrait', 'landscape'], true)
                ? $page['orientation'] : 'portrait',
            'margins_mm' => $margins,
            'columns' => max(1, min(2, (int) ($questions['columns'] ?? 1))),
            'separator' => in_array($questions['separator'] ?? null, ['line', 'box', 'none'], true)
                ? $questions['separator'] : 'line',
            'avoid_break_inside' => (bool) ($questions['avoid_break_inside'] ?? true),
        ];
    }

    /** @return array<string, mixed> */
    private function preferences(mixed $preferences): array
    {
        $preferences = is_array($preferences) ? $preferences : [];
        $separator = strip_tags((string) ($preferences['question_separator'] ?? '.'));
        if ($separator === '' || mb_strlen($separator) > 3) {
            $separator = '.';
        }

        return [
            'hide_question_term' => (bool) ($preferences['hide_question_term'] ?? false),
            'show_question_value' => (bool) ($preferences['show_question_value'] ?? true),
            'show_option_brackets' => (bool) ($preferences['show_option_brackets'] ?? false),
            'question_separator' => $separator,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function questions(array $snapshot, array $map, array $optionMaps): array
    {
        $byId = collect($snapshot)->filter(fn (mixed $question): bool => is_array($question) && isset($question['id']))
            ->keyBy(fn (array $question): int => (int) $question['id']);
        $orderedIds = array_values(array_map('intval', $map));
        if ($orderedIds === [] || count($orderedIds) !== count(array_unique($orderedIds))) {
            throw new RuntimeException('Mapa canônico de questões ausente ou duplicado.');
        }

        $resourceVersionIds = collect($snapshot)->flatMap(fn (array $question): array => array_column(
            is_array($question['resources'] ?? null) ? $question['resources'] : [],
            'resource_version_id',
        ))->filter()->map(fn (mixed $id): int => (int) $id)->unique()->all();
        $resourceVersions = QuestionResourceVersion::query()->whereIn('id', $resourceVersionIds)->get()->keyBy('id');

        return collect($orderedIds)->map(function (int $id, int $index) use ($byId, $optionMaps, $resourceVersions): array {
            $question = $byId->get($id);
            if (! $question) {
                throw new RuntimeException("A questão {$id} não existe no snapshot da cópia.");
            }
            $type = (string) ($question['type'] ?? 'essay');
            if (! in_array($type, ['multiple_choice', 'true_false', 'essay'], true)) {
                throw new RuntimeException("Tipo de questão não suportado no snapshot: {$type}.");
            }
            $content = is_array($question['content'] ?? null) ? $question['content'] : [];
            $rawOptions = is_array($content['options'] ?? null) ? array_values($content['options']) : [];
            $map = $optionMaps[$id] ?? $optionMaps[(string) $id] ?? null;
            $displayOptions = [];
            if (in_array($type, ['multiple_choice', 'true_false'], true)) {
                $map = is_array($map) ? array_values(array_map('intval', $map)) : array_keys($rawOptions);
                if ($type === 'true_false' && $rawOptions === []) {
                    $rawOptions = ['Verdadeiro', 'Falso'];
                }
                if (count($map) !== count($rawOptions) || array_values(array_unique($map)) !== $map
                    || collect($map)->contains(fn (int $item): bool => ! array_key_exists($item, $rawOptions))) {
                    throw new RuntimeException("Mapa de alternativas inválido para a questão {$id}.");
                }
                foreach ($map as $position => $originalIndex) {
                    $displayOptions[] = [
                        'label' => chr(65 + $position),
                        'text' => strip_tags((string) $rawOptions[$originalIndex]),
                        'original_index' => $originalIndex,
                    ];
                }
            }

            return [
                'id' => $id,
                'number' => $index + 1,
                'type' => $type,
                'statement' => strip_tags((string) ($content['statement'] ?? '')),
                'points' => (float) ($question['points'] ?? 0),
                'options' => $displayOptions,
                'resources' => $this->resources(
                    is_array($question['resources'] ?? null) ? $question['resources'] : [],
                    $resourceVersions,
                ),
            ];
        })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function resources(array $snapshots, $resourceVersions): array
    {
        return collect($snapshots)->filter(fn (mixed $snapshot): bool => is_array($snapshot))->map(function (array $snapshot) use ($resourceVersions): array {
            $content = is_array($snapshot['content'] ?? null) ? $snapshot['content'] : [];
            $file = is_array($snapshot['file'] ?? null) ? $snapshot['file'] : [];
            $version = $resourceVersions->get((int) ($snapshot['resource_version_id'] ?? 0));
            $imageDataUri = null;
            if ($version
                && (int) $version->question_resource_id === (int) ($snapshot['resource_id'] ?? 0)
                && $version->storage_disk === ($file['storage_disk'] ?? null)
                && $version->storage_path === ($file['storage_path'] ?? null)
                && $version->sha256 === ($file['sha256'] ?? null)) {
                $imageDataUri = $this->imageDataUri($version);
            }

            return [
                'title' => strip_tags((string) ($snapshot['title'] ?? 'Material de apoio')),
                'body' => strip_tags((string) ($content['body'] ?? '')),
                'alt_text' => strip_tags((string) ($content['alt_text'] ?? $snapshot['title'] ?? 'Imagem de apoio')),
                'external_url' => filter_var($content['external_url'] ?? null, FILTER_VALIDATE_URL) ?: null,
                'mime_type' => $file['mime_type'] ?? null,
                'image_data_uri' => $imageDataUri,
            ];
        })->values()->all();
    }

    private function imageDataUri(QuestionResourceVersion $version): ?string
    {
        $mime = (string) $version->mime_type;
        $path = (string) $version->storage_path;
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)
            || $path === '' || str_contains($path, '..') || str_contains($path, "\0") || str_starts_with($path, '/')
            || (int) $version->size_bytes > 5 * 1024 * 1024
            || ! Storage::disk($version->storage_disk)->exists($path)) {
            return null;
        }
        $bytes = Storage::disk($version->storage_disk)->get($path);
        if (strlen($bytes) > 5 * 1024 * 1024 || ($version->sha256 && ! hash_equals($version->sha256, hash('sha256', $bytes)))) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    /** @return array<int|string, array<int, int>|null> */
    private function identityOptionMaps(array $snapshot): array
    {
        $maps = [];
        foreach ($snapshot as $question) {
            $id = (int) ($question['id'] ?? 0);
            $type = $question['type'] ?? null;
            if ($type === 'multiple_choice') {
                $count = count(data_get($question, 'content.options', []));
                $maps[$id] = $count > 0 ? range(0, $count - 1) : [];
            } elseif ($type === 'true_false') {
                $maps[$id] = [0, 1];
            } else {
                $maps[$id] = null;
            }
        }

        return $maps;
    }

    /** @return array<string, mixed> */
    private function appearanceWithLegacyFallback(array $appearance): array
    {
        $appearance['schema_version'] ??= 1;
        $appearance['assessment_layout'] ??= [
            'name' => 'Layout legado compatível',
            'version' => 1,
            'definition' => [
                'page' => ['size' => 'A4', 'orientation' => 'portrait', 'margins_mm' => [15, 15, 15, 15]],
                'questions' => ['columns' => 1, 'separator' => 'line', 'avoid_break_inside' => true],
            ],
            'legacy_fallback' => true,
        ];
        $appearance['assessment_header'] ??= [
            'name' => 'Cabeçalho legado compatível',
            'version' => 1,
            'definition' => ['height_mm' => 30, 'elements' => [
                ['type' => 'text', 'token' => 'assessment.title'],
                ['type' => 'text', 'token' => 'institution.name'],
                ['type' => 'field', 'token' => 'student.name'],
            ]],
            'legacy_fallback' => true,
        ];

        return $appearance;
    }

    private function canonical(array $value): array
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->canonical($item);
            }
        }
        unset($item);
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
