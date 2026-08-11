<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamCopy;
use Illuminate\Validation\ValidationException;

/**
 * Binds an authenticated QR payload to one immutable printed copy.
 *
 * The QR proves integrity, never authorization. Callers must scope the Exam and
 * ExamCopy to the authenticated workspace before invoking this service.
 */
class PrintedQrBindingService
{
    public function __construct(private QrCodeSigningService $signer) {}

    /**
     * @param  array{page?:int,total_pages?:int,question_start?:int}  $clientPage
     * @return array{question_start:int,question_end:int,page:int,total_pages:int,template_id:int,template_version:int,exam_version:int,legacy_binding:bool}
     */
    public function bind(
        array $payload,
        int $organizationId,
        Exam $exam,
        ExamCopy $copy,
        array $clientPage = [],
    ): array {
        if (
            ! $this->signer->hasSupportedContract($payload)
            || ! $this->signer->verifyPayload($payload, $organizationId)
        ) {
            $this->fail('A assinatura ou a estrutura do QR Code não confere.');
        }

        if (
            (int) $exam->organization_id !== $organizationId
            || (int) $copy->exam_id !== (int) $exam->id
            || (int) $payload['e'] !== (int) $exam->id
            || (int) $payload['c'] !== (int) $copy->id
            || ! hash_equals((string) $copy->validation_hash, (string) $payload['h'])
        ) {
            $this->fail('O QR Code pertence a outra Avaliação, cópia ou revisão impressa.');
        }

        [$copyTemplateId, $copyTemplateVersion] = $this->copyTemplateIdentity($copy);
        $hasTemplateIdentity = $copyTemplateId !== null && $copyTemplateVersion !== null;
        $legacyBinding = ! $hasTemplateIdentity || ! $this->hasCanonicalEvidence($copy);
        if ($hasTemplateIdentity && (
            (int) $payload['tpl_id'] !== $copyTemplateId
            || (int) $payload['tpl_v'] !== $copyTemplateVersion
        )) {
            $this->fail('O template ou a versão do QR Code não corresponde à cópia impressa.');
        }

        $page = (int) ($payload['p'] ?? 1);
        $totalPages = (int) ($payload['pt'] ?? 1);
        $questionStart = (int) ($payload['qs'] ?? 1);
        $questionEnd = (int) ($payload['qe'] ?? count($copy->questions_map ?? []));
        foreach ([
            'page' => $page,
            'total_pages' => $totalPages,
            'question_start' => $questionStart,
        ] as $field => $actual) {
            if (isset($clientPage[$field]) && (int) $clientPage[$field] !== $actual) {
                $this->fail('Os metadados enviados não correspondem à página assinada no QR Code.');
            }
        }

        if ((int) ($payload['v'] ?? 0) >= 5 || isset($payload['pt'], $payload['qs'], $payload['qe'], $payload['rpp'], $payload['oc'])) {
            $this->assertCanonicalPage($payload, $copy);
        }

        return [
            'question_start' => $questionStart,
            'question_end' => $questionEnd,
            'page' => $page,
            'total_pages' => $totalPages,
            'template_id' => (int) $payload['tpl_id'],
            'template_version' => (int) $payload['tpl_v'],
            'exam_version' => max(1, (int) $copy->exam_version),
            'legacy_binding' => $legacyBinding,
        ];
    }

    private function assertCanonicalPage(array $payload, ExamCopy $copy): void
    {
        $questionsMap = array_values($copy->questions_map ?? []);
        $questionCount = count($questionsMap);
        if ($questionCount < 1) {
            $this->fail('A cópia não possui um mapa imutável de questões.');
        }

        $layout = data_get($copy->template_snapshot, 'answer_sheet_card.layout_config')
            ?? data_get($copy->template_snapshot, 'layout_config');
        if (! is_array($layout) || $layout === []) {
            // Cópias legadas não permitem reconstruir paginação; ainda preservamos
            // assinatura, identidade e limites estritos do mapa de questões.
            if ((int) $payload['qs'] < 1 || (int) $payload['qe'] > $questionCount) {
                $this->fail('A faixa de questões do QR Code excede a cópia impressa.');
            }

            return;
        }

        $rows = max(1, (int) ($layout['rows_per_column'] ?? $payload['rpp']));
        $maxColumns = max(1, (int) ($layout['columns'] ?? 1));
        $columns = max(1, min($maxColumns, (int) ceil($questionCount / $rows)));
        $questionsPerPage = $rows * $columns;
        $expectedTotalPages = (int) ceil($questionCount / $questionsPerPage);
        $page = (int) $payload['p'];
        $expectedStart = (($page - 1) * $questionsPerPage) + 1;
        $expectedEnd = min($questionCount, $expectedStart + $questionsPerPage - 1);

        if (
            (int) $payload['rpp'] !== $rows
            || (int) $payload['pt'] !== $expectedTotalPages
            || $page < 1
            || $page > $expectedTotalPages
            || (int) $payload['qs'] !== $expectedStart
            || (int) $payload['qe'] !== $expectedEnd
        ) {
            $this->fail('A paginação do QR Code diverge do snapshot imutável da cópia.');
        }

        $snapshotById = collect($copy->question_snapshot ?? [])->keyBy(
            fn (array $question): int => (int) ($question['id'] ?? 0)
        );
        if ($snapshotById->isEmpty()) {
            return;
        }

        $expectedOptionCounts = '';
        foreach (array_slice($questionsMap, $expectedStart - 1, $expectedEnd - $expectedStart + 1) as $questionId) {
            $question = $snapshotById->get((int) $questionId);
            if (! is_array($question)) {
                $this->fail('O snapshot da questão indicada pelo QR Code não está disponível.');
            }
            $type = (string) ($question['type'] ?? '');
            $expectedOptionCounts .= match ($type) {
                'essay' => '0',
                'true_false' => '2',
                'multiple_choice' => (string) count(data_get($question, 'content.options', [])),
                default => throw ValidationException::withMessages([
                    'qr_payload' => 'O snapshot contém um tipo de questão incompatível com OMR.',
                ]),
            };
        }
        if (! hash_equals($expectedOptionCounts, (string) $payload['oc'])) {
            $this->fail('A contagem de alternativas do QR Code diverge da cópia impressa.');
        }
    }

    /** @return array{0:?int,1:?int} */
    private function copyTemplateIdentity(ExamCopy $copy): array
    {
        $templateId = $copy->card_template_id
            ?? data_get($copy->template_snapshot, 'answer_sheet_card.id')
            ?? data_get($copy->template_snapshot, 'id');
        $templateVersion = $copy->card_template_version
            ?? data_get($copy->template_snapshot, 'answer_sheet_card.version')
            ?? data_get($copy->template_snapshot, 'version');

        return [
            $templateId === null ? null : (int) $templateId,
            $templateVersion === null ? null : (int) $templateVersion,
        ];
    }

    private function hasCanonicalEvidence(ExamCopy $copy): bool
    {
        $layout = data_get($copy->template_snapshot, 'answer_sheet_card.layout_config')
            ?? data_get($copy->template_snapshot, 'layout_config');
        $questionsMap = array_values($copy->questions_map ?? []);
        $snapshotById = collect($copy->question_snapshot ?? [])->keyBy(
            fn (array $question): int => (int) ($question['id'] ?? 0)
        );

        return is_array($layout)
            && $layout !== []
            && $questionsMap !== []
            && collect($questionsMap)->every(
                fn (mixed $questionId): bool => is_array($snapshotById->get((int) $questionId))
            );
    }

    /** @return never */
    private function fail(string $message): void
    {
        throw ValidationException::withMessages(['qr_payload' => $message]);
    }
}
