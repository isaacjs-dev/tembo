<?php

namespace App\Services;

use App\Models\AnswerSheetType;
use App\Models\Exam;
use App\Models\ExamCopy;
use App\Models\OmrTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Generates printable OMR answer sheets (Cartão-Resposta) with
 * fiducial markers, signed QR codes, and consistent bubble grids.
 *
 * Supports two layouts:
 *   - Essential: 2 columns, up to 40 questions, 5.5mm bubbles, 6mm fiducials
 *   - Detailed:  4 columns, up to 60 questions, 4.2mm bubbles, 4.76mm fiducials
 *
 * Each generated sheet creates an OmrTemplate record with pre-calculated
 * ROI coordinates for the engine to read.
 */
class AnswerSheetGeneratorService
{
    private QrCodeSigningService $signer;

    private OmrPageGeometryService $geometry;

    public function __construct(?QrCodeSigningService $signer = null, ?OmrPageGeometryService $geometry = null)
    {
        $this->signer = $signer ?? new QrCodeSigningService;
        $this->geometry = $geometry ?? new OmrPageGeometryService;
    }

    /**
     * Generate an answer sheet PDF for a given exam.
     *
     * @param  Exam  $exam  The exam (with questions loaded)
     * @param  Collection|ExamCopy  $copies  One or more exam copies (with shuffled maps)
     * @param  string  $templateSlug  'essential' or 'detailed'
     * @param  string  $scanMode  'preloaded', 'qr_embedded', or 'hybrid'
     * @return array{pdf: \Barryvdh\DomPDF\PDF, templates: Collection}
     */
    public function generate(
        Exam $exam,
        $copies,
        OmrTemplate $template,
        string $scanMode = 'hybrid'
    ): array {
        if ($copies instanceof ExamCopy) {
            $copies = collect([$copies]);
        }
        $copies = collect($copies)->values();
        if ($copies->isEmpty()) {
            throw new \InvalidArgumentException('Informe ao menos uma cópia para gerar o cartão-resposta.');
        }

        // Geometria vem do TEMPLATE (versão atual). Fallback: AnswerSheetType "essential".
        [$layout] = $this->resolveTemplateLayout($exam, $template, $copies->first());

        $exam->loadMissing(['questions.discipline', 'organization']);

        $allCopiesData = [];
        foreach ($copies as $copy) {
            $allCopiesData[] = [
                'copy' => $copy,
                'template' => $template,
                'pagesData' => $this->buildCardPages($exam, $copy, $template, $scanMode),
            ];
        }

        // Cartão único: blade absoluta (posiciona por `geometry`).
        $pdf = Pdf::loadView('pdf.answer-sheet-essential', [
            'exam' => $exam,
            'sheetType' => null,
            'layout' => $layout,
            'allCopiesData' => $allCopiesData,
        ]);

        return [
            'pdf' => $pdf,
            'template' => $template,
        ];
    }

    /**
     * Constrói os dados do cartão-resposta de UMA cópia (geometria + QR seguro por página),
     * reutilizável tanto na geração isolada (`generate`) quanto no lote (`pdf_advanced`).
     * Garante que QUALQUER cartão impresso use a mesma geometria/QR legível pelo motor.
     *
     * @return array[] pagesData: [{page, totalPages, qStart, qEnd, questions, qrBase64, qrPayload, geometry}]
     */
    public function buildCardPages(Exam $exam, ExamCopy $copy, OmrTemplate $template, string $scanMode = 'hybrid', ?array $layoutOverride = null): array
    {
        // $layoutOverride permite ajustar o frame ao espaço disponível (ex.: lote com
        // @page margin). Como `g` é relativo ao frame, a leitura continua correta.
        $exam->loadMissing(['questions.discipline', 'organization']);
        [$resolvedLayout, $tplVersion] = $this->resolveTemplateLayout($exam, $template, $copy);
        // O lote avançado só pode reposicionar o frame. Geometria estrutural
        // (linhas, colunas, bolhas e limites) permanece presa à versão da prova.
        $placementOverride = $layoutOverride
            ? array_intersect_key($layoutOverride, array_flip([
                'frame_left_mm',
                'frame_top_mm',
                'frame_width_mm',
            ]))
            : [];
        $layout = array_replace($resolvedLayout, $placementOverride);

        $rowsPerColumn = max(1, (int) ($layout['rows_per_column'] ?? 20));
        $maxColumns = max(1, (int) ($layout['columns'] ?? $template->max_columns ?? 2));
        $maxQuestions = (int) ($layout['max_questions'] ?? $template->max_questions ?? ($rowsPerColumn * $maxColumns));
        $maxOptions = max(2, (int) ($layout['max_options'] ?? $template->max_options ?? 5));
        $organizationId = $exam->organization_id;

        $questionMap = collect($copy->questions_map)->map(fn ($id): int => (int) $id)->values();
        $examQuestionIds = $exam->questions->pluck('id')->map(fn ($id): int => (int) $id)->values();

        if ($copy->exists && (int) $copy->exam_id !== (int) $exam->id) {
            throw new \InvalidArgumentException('A cópia informada pertence a outra avaliação.');
        }
        if (
            $questionMap->count() !== $questionMap->unique()->count()
            || $questionMap->count() !== $examQuestionIds->count()
            || $questionMap->diff($examQuestionIds)->isNotEmpty()
            || $examQuestionIds->diff($questionMap)->isNotEmpty()
        ) {
            throw new \RuntimeException('O mapa da cópia não corresponde integralmente às questões da avaliação.');
        }

        $orderedQuestions = $questionMap
            ->map(fn ($qId) => $exam->questions->firstWhere('id', $qId))
            ->values();

        $numQ = $orderedQuestions->count();
        if ($numQ === 0) {
            throw new \RuntimeException('Não é possível gerar um cartão-resposta sem questões.');
        }
        if ($maxQuestions > 0 && $numQ > $maxQuestions) {
            throw new \RuntimeException("A prova tem {$numQ} questões, acima do limite do template '{$template->name}' ({$maxQuestions}).");
        }

        // ADAPTA o nº de colunas ao nº real de questões (até o limite do template).
        $this->validateQuestionsAndMaps($orderedQuestions, $copy, $maxOptions);

        $effectiveColumns = max(1, min($maxColumns, (int) ceil($numQ / $rowsPerColumn)));
        $pageLayout = array_merge($layout, [
            'columns' => $effectiveColumns,
            'rows_per_column' => $rowsPerColumn,
        ]);

        $questionsPerPage = $rowsPerColumn * $effectiveColumns;
        $chunks = $orderedQuestions->chunk($questionsPerPage);
        $totalPages = $chunks->count();

        $pagesData = [];
        foreach ($chunks as $pageIndex => $chunk) {
            $page = $pageIndex + 1;
            $qStart = ($pageIndex * $questionsPerPage) + 1;
            $qEnd = $qStart + $chunk->count() - 1;

            // Geometria do frame (marcadores + bolhas) — FONTE DE VERDADE ÚNICA:
            // impressa (posicionamento absoluto na blade) e embarcada no QR ('g').
            $questionDescriptors = $chunk->values()->map(fn ($question) => [
                'type' => $question->type,
                'option_count' => $question->type === 'true_false'
                    ? 2
                    : count($question->content['options'] ?? []),
            ]);
            $geometry = $this->geometry->build(
                $pageLayout,
                $questionDescriptors,
                $qStart,
                (int) $template->id,
                $tplVersion
            );
            $contract = $geometry['contract'];

            $qrPayload = [
                'e' => $exam->id,
                'c' => $copy->id,
                'h' => $copy->validation_hash,
                'p' => $page,
                'pt' => $totalPages,
                'qs' => $contract['qs'],
                'qe' => $contract['qe'],
                // v5 mantém o contrato v4, mas codifica a assinatura em base64url. A
                // economia no QR permite módulos maiores na impressão, muito mais
                // confiáveis para leitura pela câmera do celular.
                'v' => 5,
                'rpp' => $contract['rpp'],
                // A coluna é derivável de qe/qs/rpp e o slug é redundante com
                // tpl_id. Omiti-los diminui a densidade do QR sem perder a geometria.
                'tpl_id' => $contract['tpl_id'],
                'tpl_v' => $contract['tpl_v'],
                'g' => $contract['g'],
                // Contagem de opções por questão (V/F=2, múltipla=n, dissertativa=0). O motor
                // lê APENAS o nº real de bolhas — ex.: V/F = só A e B — evitando ler ruído em
                // C/D/E e marcar a questão como inválida. 1 dígito por questão, na ordem da folha.
                'oc' => $contract['oc'],
            ];

            // Gabarito (índices visuais) — será CIFRADO em buildPayload (nunca texto puro).
            if (in_array($scanMode, ['qr_embedded', 'hybrid'], true)) {
                $gabarito = [];
                $pontos = [];
                foreach ($chunk->values() as $q) {
                    $optMap = $copy->options_map[$q->id] ?? null;
                    $correctOriginal = $q->content['correct_option'] ?? null;
                    if ($optMap !== null && $correctOriginal !== null) {
                        $shuffledIdx = array_search(
                            (int) $correctOriginal,
                            array_map('intval', array_values($optMap)),
                            true
                        );
                        $gabarito[] = $shuffledIdx !== false ? $shuffledIdx : -1;
                    } else {
                        $gabarito[] = -1; // dissertativa/inválida
                    }
                    $pontos[] = $q->pivot->points ?? 1;
                }
                $qrPayload['gab'] = $gabarito;
                if ($scanMode === 'qr_embedded') {
                    $qrPayload['pts'] = $pontos;
                }
            }

            // Cifra o gabarito (AES) + assina (HMAC) com a chave da organização.
            $qrPayload = $this->signer->buildPayload($qrPayload, $scanMode, $organizationId);

            // Não use QR minúsculo nem margem zero: impressoras e câmeras perdem os
            // módulos da borda. O SVG é escalado na blade para 26 mm e inclui quiet zone.
            $qrSvg = QrCode::format('svg')
                ->errorCorrection('M')
                ->size(300)
                ->margin(2)
                ->generate(json_encode($qrPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            $pagesData[] = [
                'page' => $page,
                'totalPages' => $totalPages,
                'qStart' => $qStart,
                'qEnd' => $qEnd,
                'questions' => $chunk->values(),
                'qrBase64' => base64_encode($qrSvg),
                'qrPayload' => $qrPayload,
                'geometry' => $geometry,
            ];
        }

        return $pagesData;
    }

    /**
     * Validates a template against the live exam without persisting a binding or copy.
     * The same canonical geometry service used by PDF generation performs the check.
     */
    public function assertCompatible(
        Exam $exam,
        OmrTemplate $template,
        ?array $layoutOverride = null,
        ?int $templateVersion = null,
    ): void {
        $exam->loadMissing('questions');
        $templateVersion ??= (int) ($template->current_version ?: 1);
        $resolvedLayout = $template->layoutForVersion($templateVersion);
        if ($resolvedLayout === []) {
            throw new \RuntimeException('O template OMR não possui uma geometria válida.');
        }
        $placementOverride = $layoutOverride
            ? array_intersect_key($layoutOverride, array_flip(['frame_left_mm', 'frame_top_mm', 'frame_width_mm']))
            : [];
        $layout = array_replace($resolvedLayout, $placementOverride);
        $questions = $exam->questions->values();
        $maxQuestions = (int) ($layout['max_questions'] ?? $template->max_questions ?? 0);
        $rows = max(1, (int) ($layout['rows_per_column'] ?? 20));
        $maxColumns = max(1, (int) ($layout['columns'] ?? $template->max_columns ?? 2));
        $maxOptions = min(9, max(2, (int) ($layout['max_options'] ?? $template->max_options ?? 5)));

        if ($questions->isEmpty()) {
            throw new \RuntimeException('Não é possível gerar um cartão-resposta sem questões.');
        }
        if ($maxQuestions > 0 && $questions->count() > $maxQuestions) {
            throw new \RuntimeException("O modelo aceita até {$maxQuestions} questões.");
        }

        $descriptors = $questions->map(function ($question) use ($maxOptions): array {
            if (! in_array($question->type, ['multiple_choice', 'true_false', 'essay'], true)) {
                throw new \RuntimeException("Tipo de questão não suportado pelo cartão OMR: {$question->type}.");
            }
            $optionCount = $question->type === 'true_false'
                ? 2
                : ($question->type === 'essay' ? 0 : count($question->content['options'] ?? []));
            if ($question->type !== 'essay' && ($optionCount < 2 || $optionCount > $maxOptions)) {
                throw new \RuntimeException("O modelo aceita no máximo {$maxOptions} alternativas por questão.");
            }

            if ($question->type !== 'essay') {
                $correctOption = $question->content['correct_option'] ?? null;
                if (
                    ! is_numeric($correctOption)
                    || (int) $correctOption < 0
                    || (int) $correctOption >= $optionCount
                ) {
                    throw new \RuntimeException("A questão objetiva {$question->id} não possui gabarito válido.");
                }
            }

            return ['type' => $question->type, 'option_count' => $optionCount];
        });

        $effectiveColumns = max(1, min($maxColumns, (int) ceil($questions->count() / $rows)));
        $pageLayout = array_merge($layout, ['columns' => $effectiveColumns, 'rows_per_column' => $rows]);
        foreach ($descriptors->chunk($rows * $effectiveColumns) as $pageIndex => $pageQuestions) {
            $this->geometry->build(
                $pageLayout,
                $pageQuestions->values(),
                ($pageIndex * $rows * $effectiveColumns) + 1,
                (int) $template->id,
                $templateVersion,
            );
        }
    }

    /**
     * @return array{0: array, 1: int}
     */
    private function resolveTemplateLayout(Exam $exam, OmrTemplate $template, ?ExamCopy $copy = null): array
    {
        $copySnapshot = $copy?->template_snapshot;
        if (is_array($copySnapshot) && ! empty($copySnapshot['layout_config'])) {
            return [
                $copySnapshot['layout_config'],
                (int) ($copySnapshot['version'] ?? $copy->card_template_version ?? 1),
            ];
        }

        $version = (int) ($template->current_version ?: 1);
        if ((int) ($copy?->card_template_id) === (int) $template->id && (int) ($copy?->card_template_version) > 0) {
            $version = (int) $copy->card_template_version;
        }
        if (
            (int) $exam->card_template_id === (int) $template->id
            && (int) $exam->card_template_version > 0
        ) {
            $version = (int) $exam->card_template_version;
        }

        $layout = $template->layoutForVersion($version);
        if (empty($layout)) {
            $layout = AnswerSheetType::withoutGlobalScopes()
                ->where('slug', 'essential')
                ->first()?->layout_config ?? [];
        }
        if (empty($layout)) {
            throw new \RuntimeException('O template OMR não possui uma geometria válida.');
        }

        return [$layout, $version];
    }

    /**
     * Cada questão objetiva precisa de gabarito e de um mapa que seja uma
     * permutação exata das alternativas existentes.
     */
    private function validateQuestionsAndMaps($questions, ExamCopy $copy, int $maxOptions): void
    {
        if (! is_array($copy->options_map)) {
            throw new \RuntimeException('A cópia não possui mapa de alternativas.');
        }

        foreach ($questions as $question) {
            $map = $copy->options_map[$question->id] ?? null;

            if ($question->type === 'essay') {
                if ($map !== null) {
                    throw new \RuntimeException("A questão discursiva {$question->id} não pode possuir bolhas.");
                }

                continue;
            }

            if (! in_array($question->type, ['multiple_choice', 'true_false'], true)) {
                throw new \RuntimeException("Tipo de questão não suportado pelo cartão OMR: {$question->type}.");
            }

            $optionCount = $question->type === 'true_false'
                ? 2
                : count($question->content['options'] ?? []);
            if ($optionCount < 2 || $optionCount > $maxOptions || $optionCount > 9) {
                throw new \RuntimeException(
                    "A questão {$question->id} possui {$optionCount} alternativas; o template aceita de 2 a {$maxOptions}."
                );
            }

            $correctOption = $question->content['correct_option'] ?? null;
            if (
                ! is_numeric($correctOption)
                || (int) $correctOption < 0
                || (int) $correctOption >= $optionCount
            ) {
                throw new \RuntimeException("A questão objetiva {$question->id} não possui gabarito válido.");
            }

            if (! is_array($map)) {
                throw new \RuntimeException("A questão objetiva {$question->id} não possui mapa de alternativas.");
            }
            $normalizedMap = array_map('intval', array_values($map));
            $expectedMap = range(0, $optionCount - 1);
            sort($normalizedMap);
            if ($normalizedMap !== $expectedMap) {
                throw new \RuntimeException("O mapa de alternativas da questão {$question->id} é inválido.");
            }
        }
    }
}
