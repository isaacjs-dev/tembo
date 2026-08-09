<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamCopy;
use Illuminate\Validation\ValidationException;

/**
 * Server-side contract for an offline OMR capture.
 *
 * A device can read the signed QR and capture the visual answers while offline,
 * but it never receives a plaintext answer key or an organization signing key.
 * On synchronization this service authenticates the QR, binds it to the exact
 * printed copy/page and maps printed positions to the immutable questions_map.
 */
class OfflineOmrQrService
{
    public function __construct(private QrCodeSigningService $signer) {}

    /**
     * Validate the QR against the tenant, exam, copy and page supplied by the client.
     * Returns normalized numeric metadata only after the HMAC has been verified.
     *
     * @return array{question_start:int,question_end:int,page:int,total_pages:int,template_id:?int,template_version:?int}
     */
    public function validatePage(
        array $payload,
        int $organizationId,
        Exam $exam,
        ExamCopy $copy,
        int $pageIndex,
        int $totalPages,
        int $questionStart,
    ): array {
        $required = ['e', 'c', 'h', 'p', 'pt', 'qs', 'qe', 'v', 'rpp', 'tpl_id', 'tpl_v', 'g', 'oc', 'chk'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $payload) || $payload[$key] === '' || $payload[$key] === null) {
                $this->fail('qr_payload', "O QR Code não contém o campo obrigatório {$key}.");
            }
        }

        if (
            ! in_array((int) $payload['v'], [4, 5], true)
            || ! $this->signer->hasSupportedContract($payload)
            || ! $this->signer->verifyPayload($payload, $organizationId)
        ) {
            $this->fail('qr_payload', 'A assinatura do QR Code não confere. Leia novamente o cartão original.');
        }

        if (
            (int) $payload['e'] !== (int) $exam->id
            || (int) $payload['c'] !== (int) $copy->id
            || ! hash_equals((string) $copy->validation_hash, (string) $payload['h'])
        ) {
            $this->fail('qr_payload', 'O QR Code pertence a outra prova ou a outra versão impressa.');
        }

        $qrPage = (int) $payload['p'];
        $qrTotal = (int) $payload['pt'];
        $qs = (int) $payload['qs'];
        $qe = (int) $payload['qe'];
        if (
            $qrPage !== $pageIndex
            || $qrTotal !== $totalPages
            || $qs !== $questionStart
            || $qs < 1
            || $qe < $qs
            || $qe > count($copy->questions_map ?? [])
        ) {
            $this->fail('qr_payload', 'Os dados da página não correspondem ao QR Code lido.');
        }

        return [
            'question_start' => $qs,
            'question_end' => $qe,
            'page' => $qrPage,
            'total_pages' => $qrTotal,
            'template_id' => isset($payload['tpl_id']) ? (int) $payload['tpl_id'] : null,
            'template_version' => isset($payload['tpl_v']) ? (int) $payload['tpl_v'] : null,
        ];
    }

    /** @return array<int, mixed> question id => visual answer */
    public function mapPrintedAnswers(ExamCopy $copy, array $printedAnswers, int $questionStart, int $questionEnd): array
    {
        $questionMap = array_values($copy->questions_map ?? []);
        $mapped = [];

        foreach ($printedAnswers as $printedNumber => $answer) {
            if (! is_numeric($printedNumber)) {
                $this->fail('detected_answers', 'As respostas offline devem usar os números impressos das questões.');
            }
            $position = (int) $printedNumber;
            if ($position < $questionStart || $position > $questionEnd || ! isset($questionMap[$position - 1])) {
                $this->fail('detected_answers', 'Uma resposta informada não pertence à página indicada pelo QR Code.');
            }
            $mapped[(int) $questionMap[$position - 1]] = $answer;
        }

        return $mapped;
    }

    /** @return array<int, mixed> question id => confidence */
    public function mapPrintedConfidences(ExamCopy $copy, array $printedConfidences, int $questionStart, int $questionEnd): array
    {
        $mapped = $this->mapPrintedAnswers($copy, $printedConfidences, $questionStart, $questionEnd);
        foreach ($mapped as $confidence) {
            if (! is_numeric($confidence) || (float) $confidence < 0 || (float) $confidence > 1) {
                $this->fail('confidences', 'As confianças da leitura devem estar entre 0 e 1.');
            }
        }

        return $mapped;
    }

    /**
     * If present, decrypt the embedded answer key only on the server and verify
     * it still represents the official key for this exact printed page.
     *
     * @return array<int, array{qr:?int,official:?int}> keyed by question id
     */
    public function embeddedKeyMismatches(array $payload, int $organizationId, Exam $exam, ExamCopy $copy): array
    {
        if (empty($payload['gab_enc'])) {
            return [];
        }

        $answers = $this->signer->decryptGabarito((string) $payload['gab_enc'], $organizationId);
        if (! is_array($answers)) {
            $this->fail('qr_payload', 'O gabarito cifrado do QR Code não pôde ser validado.');
        }

        $start = (int) ($payload['qs'] ?? 1);
        $questionMap = array_values($copy->questions_map ?? []);
        $exam->loadMissing('questions');
        $snapshotById = collect($copy->question_snapshot ?? [])->keyBy(fn (array $question): int => (int) $question['id']);
        $mismatches = [];

        foreach ($answers as $offset => $visualAnswer) {
            $questionId = $questionMap[$start + $offset - 1] ?? null;
            $question = $questionId ? $exam->questions->firstWhere('id', $questionId) : null;
            $snapshot = $questionId ? $snapshotById->get((int) $questionId) : null;
            if (! $question && ! $snapshot) {
                continue;
            }
            $optionMap = $copy->options_map[$questionId] ?? null;
            $correct = is_array($snapshot)
                ? data_get($snapshot, 'content.correct_option')
                : ($question->content['correct_option'] ?? null);
            $officialVisual = is_array($optionMap) && $correct !== null
                ? array_search((int) $correct, array_map('intval', array_values($optionMap)), true)
                : false;
            $officialVisual = $officialVisual === false ? null : (int) $officialVisual;
            $qrVisual = $visualAnswer === -1 ? null : (int) $visualAnswer;

            if ($qrVisual !== null && $qrVisual !== $officialVisual) {
                $mismatches[(int) $questionId] = ['qr' => $qrVisual, 'official' => $officialVisual];
            }
        }

        return $mismatches;
    }

    /** @return never */
    private function fail(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
