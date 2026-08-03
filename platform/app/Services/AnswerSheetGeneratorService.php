<?php

namespace App\Services;

use App\Models\AnswerSheetType;
use App\Models\Exam;
use App\Models\ExamCopy;
use App\Models\OmrTemplate;
use App\Models\OmrTemplateQuestion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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

    /** A4 dimensions at 300 DPI */
    private const A4_WIDTH_PX = 2480;

    private const A4_HEIGHT_PX = 3508;

    /** mm-to-px conversion factor at 300 DPI */
    private const MM_TO_PX = 11.811; // 300 / 25.4

    public function __construct(?QrCodeSigningService $signer = null)
    {
        $this->signer = $signer ?? new QrCodeSigningService;
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
        [$layout] = $this->resolveTemplateLayout($exam, $template);

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
        [$resolvedLayout, $tplVersion] = $this->resolveTemplateLayout($exam, $template);
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
            $geometry = $this->buildPageGeometry($pageLayout, $chunk->values(), $qStart);
            $this->validatePageGeometry($geometry);

            $qrPayload = [
                'e' => $exam->id,
                'c' => $copy->id,
                'h' => $copy->validation_hash,
                'p' => $page,
                'pt' => $totalPages,
                'qs' => $qStart,
                'qe' => $qEnd,
                // v5 mantém o contrato v4, mas codifica a assinatura em base64url. A
                // economia no QR permite módulos maiores na impressão, muito mais
                // confiáveis para leitura pela câmera do celular.
                'v' => 5,
                'rpp' => $rowsPerColumn,
                // A coluna é derivável de qe/qs/rpp e o slug é redundante com
                // tpl_id. Omiti-los diminui a densidade do QR sem perder a geometria.
                'tpl_id' => $template->id,
                'tpl_v' => $tplVersion,
                'g' => $geometry['g'],
                // Contagem de opções por questão (V/F=2, múltipla=n, dissertativa=0). O motor
                // lê APENAS o nº real de bolhas — ex.: V/F = só A e B — evitando ler ruído em
                // C/D/E e marcar a questão como inválida. 1 dígito por questão, na ordem da folha.
                'oc' => $chunk->values()->map(function ($q) {
                    if ($q->type === 'true_false') {
                        return 2;
                    }
                    if ($q->type === 'multiple_choice') {
                        return min(9, count($q->content['options'] ?? []));
                    }

                    return 0; // dissertativa / sem bolhas
                })->implode(''),
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
     * Constrói a geometria de UMA página: o "frame" (retângulo cujos cantos são os
     * CENTROS dos 4 marcadores fiduciais), as posições absolutas (mm) de cada bolha
     * e o array `g` (frações do frame ×10000) embarcado no QR.
     *
     * FONTE DE VERDADE ÚNICA: a blade imprime as bolhas exatamente nas posições
     * calculadas aqui, e o motor (engine.ts → readBubbles) amostra usando o mesmo `g`
     * aplicado ao frame transformado. Assim impressão e leitura coincidem por construção.
     *
     * Modelo de grade do motor (precisa bater):
     *   col = floor((n-1) / rpp);  row = (n-1) % rpp
     *   bX  = startX + col*colSpacing + opt*optionSpacing   (frações da largura do frame)
     *   bY  = startY + row*rowSpacing                       (frações da altura do frame)
     *
     * @param  Collection  $questions  Questões desta página (em ordem).
     * @param  int  $qStart  Número global da 1ª questão da página.
     * @return array{g: int[], frame: array, bubbleMm: float, cells: array}
     */
    /**
     * @return array{0: array, 1: int}
     */
    private function resolveTemplateLayout(Exam $exam, OmrTemplate $template): array
    {
        $version = (int) ($template->current_version ?: 1);
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

    private function validatePageGeometry(array $geometry): void
    {
        $frame = $geometry['frame'];
        $halfFiducial = ((float) $frame['fid']) / 2;
        $left = (float) $frame['x'] - $halfFiducial;
        $right = (float) $frame['x'] + (float) $frame['w'] + $halfFiducial;
        $top = (float) $frame['y'] - $halfFiducial;
        $bottomWithFooter = (float) $frame['y'] + (float) $frame['h'] + 10;

        if ($left < 0 || $right > 210 || $top < 45 || $bottomWithFooter > 297) {
            throw new \RuntimeException(
                'A geometria do template ultrapassa a área segura da página A4 ou invade o cabeçalho.'
            );
        }

        // Quatro fiduciais sólidos precisam ser grandes o bastante para a foto, mas
        // sem invadir a grade. Este intervalo foi escolhido para A4 fotografado por
        // celular (aprox. 35–110 px por marcador em capturas usuais).
        if ((float) $frame['fid'] < 7.0 || (float) $frame['fid'] > 12.0) {
            throw new \RuntimeException('Os marcadores do template OMR devem ter entre 7 e 12 mm para leitura confiável.');
        }

        $bubbleDiameter = (float) $geometry['bubbleMm'];
        foreach ($geometry['cells'] as $cell) {
            foreach ($cell['bubbles'] as $bubble) {
                if (
                    (float) $bubble['x'] < (float) $frame['x']
                    || (float) $bubble['x'] + $bubbleDiameter > (float) $frame['x'] + (float) $frame['w']
                    || (float) $bubble['y'] < (float) $frame['y']
                    || (float) $bubble['y'] + $bubbleDiameter > (float) $frame['y'] + (float) $frame['h']
                ) {
                    throw new \RuntimeException('A grade de bolhas ultrapassa o frame do template OMR.');
                }
            }
        }

        // Impede que uma bolha encoste no quadrado preto. Regiões pretas adjacentes
        // fazem o detector enxergar um componente maior e invalidam a homografia.
        // Os templates legados já posicionam a primeira linha a 2 mm da borda do
        // marcador. Mantemos 1,5 mm como mínimo compatível, sem invalidar cartões
        // históricos que continuam sendo lidos pelo mesmo motor.
        $minimumClearance = 1.5;
        foreach ($geometry['cells'] as $cell) {
            foreach ($cell['bubbles'] as $bubble) {
                $nearLeft = (float) $bubble['x'] - ((float) $frame['x'] + $halfFiducial);
                $nearTop = (float) $bubble['y'] - ((float) $frame['y'] + $halfFiducial);
                if ($nearLeft < $minimumClearance || $nearTop < $minimumClearance) {
                    throw new \RuntimeException('A grade deve manter ao menos 3 mm livres ao redor dos marcadores OMR.');
                }
            }
        }
    }

    /**
     * Calcula a geometria impressa e os coeficientes relativos consumidos pela engine.
     *
     * @return array{g: int[], frame: array, bubbleMm: float, cells: array}
     */
    private function buildPageGeometry(array $layout, $questions, int $qStart): array
    {
        $columns = max((int) ($layout['columns'] ?? 2), 1);
        $rows = max((int) ($layout['rows_per_column'] ?? 20), 1);
        $bubble = (float) ($layout['bubble_diameter_mm'] ?? 5.5);
        // Fiduciais maiores que os marcadores do QR (~6mm) p/ a detecção pegar os 4 certos.
        $fid = (float) ($layout['frame_fiducial_mm'] ?? 8.0);

        // --- Frame da grade (mm, na página A4 210×297). Cabeçalho/QR ficam ACIMA. ---
        $frameX = (float) ($layout['frame_left_mm'] ?? 12.0);
        $frameY = (float) ($layout['frame_top_mm'] ?? 56.0);
        $frameW = (float) ($layout['frame_width_mm'] ?? 186.0);
        $rowSpacing = (float) ($layout['row_spacing_mm'] ?? 9.0);

        $startXIn = (float) ($layout['cell_indent_mm'] ?? 14.0); // recuo p/ o número da questão
        $startYIn = (float) ($layout['grid_pad_top_mm'] ?? 8.0);
        $optionSpacing = $bubble + (float) ($layout['option_gap_mm'] ?? 2.0);
        $colSpacing = $frameW / $columns;
        if ($rowSpacing < $bubble + 1.0 || $optionSpacing < $bubble + 0.5) {
            throw new \RuntimeException('O espaçamento do template é insuficiente para separar as bolhas com segurança.');
        }

        // Altura do frame: cabe todas as linhas + padding inferior.
        // Frame COMPACTO: dimensiona pelo nº REAL de linhas usadas (a coluna 0 enche até
        // `rows`), não pelo máximo do template. Menos papel, marcadores mais próximos, foto
        // mais fácil. O `g` é relativo ao frame, então a leitura continua idêntica.
        $rowsUsed = max(5, min($questions->count(), $rows)); // mínimo p/ não achatar o frame
        $frameH = $startYIn + ($rowsUsed * $rowSpacing) + 4.0;

        // --- `g`: frações do FRAME ×10000 (mesma base do engine) ---
        $g = [
            (int) round(($startXIn / $frameW) * 10000),
            (int) round(($startYIn / $frameH) * 10000),
            (int) round(($colSpacing / $frameW) * 10000),
            (int) round(($rowSpacing / $frameH) * 10000),
            (int) round(($bubble / $frameW) * 10000),
            (int) round(($optionSpacing / $frameW) * 10000),
        ];

        // --- Posições absolutas (mm) de cada questão/bolha, no MESMO modelo do engine ---
        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
        $cells = [];
        foreach ($questions->values() as $qi => $q) {
            $col = intdiv($qi, $rows);
            $row = $qi % $rows;
            if ($col >= $columns) {
                break; // segurança: não exceder colunas
            }

            $cellTop = $frameY + $startYIn + ($row * $rowSpacing);
            $bubbleX0 = $frameX + ($col * $colSpacing) + $startXIn;

            $isObjective = in_array($q->type, ['multiple_choice', 'true_false'], true);
            $optCount = $q->type === 'true_false'
                ? 2
                : count($q->content['options'] ?? []);

            $bubbles = [];
            if ($isObjective) {
                for ($i = 0; $i < $optCount; $i++) {
                    $bubbles[] = [
                        'label' => $letters[$i] ?? '',
                        'x' => round($bubbleX0 + ($i * $optionSpacing), 3),
                        'y' => round($cellTop, 3),
                    ];
                }
                $lastBubble = end($bubbles);
                $columnRight = $frameX + (($col + 1) * $colSpacing);
                if ($lastBubble && (float) $lastBubble['x'] + $bubble > $columnRight - 1.0) {
                    throw new \RuntimeException(
                        'As alternativas não cabem na coluna do template; aumente a largura ou reduza colunas/bolhas.'
                    );
                }
            }

            $cells[] = [
                'num' => $qStart + $qi,
                'numX' => round($frameX + ($col * $colSpacing) + 1.0, 3),
                'numY' => round($cellTop, 3),
                'essay' => ! $isObjective,
                'bubbles' => $bubbles,
            ];
        }

        return [
            'g' => $g,
            'frame' => [
                'x' => $frameX,
                'y' => $frameY,
                'w' => $frameW,
                'h' => round($frameH, 3),
                'fid' => $fid,
            ],
            'bubbleMm' => $bubble,
            'cells' => $cells,
        ];
    }

    /**
     * Create an OmrTemplate record with pre-calculated ROI coordinates.
     */
    private function createTemplate(
        Exam $exam,
        ExamCopy $copy,
        AnswerSheetType $sheetType,
        Collection $orderedQuestions,
        array $pagesData
    ): OmrTemplate {
        $layout = $sheetType->layout_config;
        $columns = $layout['columns'] ?? 2;
        $rowsPerCol = $layout['rows_per_column'] ?? 20;
        $bubbleMm = $layout['bubble_diameter_mm'] ?? 5.5;
        $fiducialMm = $layout['fiducial_size_mm'] ?? 6.0;

        $template = OmrTemplate::create([
            'name' => "Auto: {$sheetType->name} - {$exam->title} v{$copy->copy_number}",
            'slug' => Str::slug("auto-{$sheetType->slug}-exam-{$exam->id}-v{$copy->copy_number}"),
            'organization_id' => $exam->organization_id,
            'created_by' => $exam->author_id,
            'width' => self::A4_WIDTH_PX,
            'height' => self::A4_HEIGHT_PX,
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'corner_points_json' => $this->computeCornerPoints($layout),
            'thresholds_json' => $sheetType->grading_config ?? [
                'confidence_threshold_auto_accept' => 0.85,
                'confidence_threshold_review' => 0.65,
                'confidence_threshold_rescan' => 0.50,
            ],
            'calibration_json' => null,
            'qr_region_json' => $this->computeQrRegion($layout),
            'total_questions' => $orderedQuestions->count(),
            'total_pages' => count($pagesData),
            'columns' => $columns,
            'rows_per_column' => $rowsPerCol,
            'max_options' => $layout['max_options'] ?? 5,
            'is_active' => true,
        ]);

        // Create ROIs for each question
        $this->createTemplateQuestions($template, $orderedQuestions, $copy, $layout);

        return $template;
    }

    /**
     * Compute corner points for fiducial markers in pixel coordinates.
     */
    private function computeCornerPoints(array $layout): array
    {
        $margins = $layout['margins_mm'] ?? 12;
        $fiducialMm = $layout['fiducial_size_mm'] ?? 6.0;
        $fiducialPx = (int) round($fiducialMm * self::MM_TO_PX);
        $marginPx = (int) round($margins * self::MM_TO_PX);

        // Fiducial centers at the 4 corners of the bubble area
        $halfFid = (int) round($fiducialPx / 2);

        return [
            'tl' => ['x' => $marginPx + $halfFid, 'y' => $marginPx + $halfFid],
            'tr' => ['x' => self::A4_WIDTH_PX - $marginPx - $halfFid, 'y' => $marginPx + $halfFid],
            'br' => ['x' => self::A4_WIDTH_PX - $marginPx - $halfFid, 'y' => self::A4_HEIGHT_PX - $marginPx - $halfFid],
            'bl' => ['x' => $marginPx + $halfFid, 'y' => self::A4_HEIGHT_PX - $marginPx - $halfFid],
            'fiducial_size_px' => $fiducialPx,
        ];
    }

    /**
     * Compute QR code region bounds for the reader.
     */
    private function computeQrRegion(array $layout): array
    {
        $margins = $layout['margins_mm'] ?? 12;
        $qrPos = $layout['qr_position'] ?? 'top_center';

        $qrSizeMm = 25; // ~25mm QR
        $qrSizePx = (int) round($qrSizeMm * self::MM_TO_PX);
        $marginPx = (int) round($margins * self::MM_TO_PX);

        $y = $marginPx + 10;

        if ($qrPos === 'top_left') {
            $x = $marginPx + 10;
        } else {
            // top_center
            $x = (int) round((self::A4_WIDTH_PX - $qrSizePx) / 2);
        }

        return [
            'x' => $x,
            'y' => $y,
            'width' => $qrSizePx,
            'height' => $qrSizePx,
        ];
    }

    /**
     * Generate OmrTemplateQuestion records with ROI coordinates.
     */
    private function createTemplateQuestions(
        OmrTemplate $template,
        Collection $orderedQuestions,
        ExamCopy $copy,
        array $layout
    ): void {
        $columns = $layout['columns'] ?? 2;
        $rowsPerCol = $layout['rows_per_column'] ?? 20;
        $bubbleMm = $layout['bubble_diameter_mm'] ?? 5.5;
        $colStarts = $layout['col_bubble_start_mm'] ?? [25.0, 110.0];

        // Convert to px
        $bubblePx = (int) round($bubbleMm * self::MM_TO_PX);
        $optionGapPx = (int) round(($bubbleMm + 1.5) * self::MM_TO_PX); // bubble + gap

        $gridTopMm = ($layout['grid_top_offset_mm'] ?? 20) + 35; // add header height
        $gridTopPx = (int) round($gridTopMm * self::MM_TO_PX);

        $areaH = $layout['area_height_mm'] ?? 240.0;
        $rowSpacingPx = (int) round(($areaH / max($rowsPerCol, 1)) * self::MM_TO_PX);

        $letters = ['A', 'B', 'C', 'D', 'E'];

        foreach ($orderedQuestions as $qIndex => $question) {
            $col = intdiv($qIndex, $rowsPerCol);
            $row = $qIndex % $rowsPerCol;

            if ($col >= $columns || $col >= count($colStarts)) {
                break; // Safety: don't exceed available columns
            }

            $baseX = (int) round($colStarts[$col] * self::MM_TO_PX);
            $baseY = $gridTopPx + ($row * $rowSpacingPx);

            $optMap = $copy->options_map[$question->id] ?? null;
            $optCount = $question->type === 'true_false' ? 2 : count($question->content['options'] ?? []);

            $optionLabels = [];
            $rois = [];

            for ($i = 0; $i < $optCount; $i++) {
                $optionLabels[] = $letters[$i] ?? "O{$i}";
                $rois[] = [
                    'x' => $baseX + ($i * $optionGapPx),
                    'y' => $baseY,
                    'w' => $bubblePx,
                    'h' => $bubblePx,
                ];
            }

            OmrTemplateQuestion::create([
                'omr_template_id' => $template->id,
                'question_number' => $qIndex + 1,
                'option_labels_json' => $optionLabels,
                'rois_json' => $rois,
                'weight' => $question->pivot->points ?? 1,
            ]);
        }
    }
}
