<?php

namespace App\Services;

/**
 * Builds the immutable, page-specific OMR geometry used by printing and readers.
 *
 * Coordinates in `g` are fractions of the fiducial frame multiplied by 10,000.
 * This is the signed contract embedded in QR v4/v5; consumers must not infer a
 * current template layout when this contract is present.
 */
class OmrPageGeometryService
{
    public const CONTRACT_VERSION = 1;

    /**
     * @param  iterable<int, array{type: string, option_count: int}>  $questions
     * @return array{
     *   contract_version: int,
     *   contract: array{g: int[], rpp: int, oc: string, qs: int, qe: int, tpl_id: int, tpl_v: int},
     *   geometry_hash: string,
     *   paper: array{size: string, width_mm: int, height_mm: int},
     *   columns: int,
     *   index_basis: string,
     *   g: int[],
     *   frame: array{x: float, y: float, w: float, h: float, fid: float},
     *   bubbleMm: float,
     *   cells: array<int, array{num: int, numX: float, numY: float, essay: bool, bubbles: array}>
     * }
     */
    public function build(
        array $layout,
        iterable $questions,
        int $qStart,
        int $templateId,
        int $templateVersion
    ): array {
        $normalizedQuestions = [];
        foreach ($questions as $question) {
            $type = (string) ($question['type'] ?? '');
            $optionCount = (int) ($question['option_count'] ?? 0);

            if (! in_array($type, ['multiple_choice', 'true_false', 'essay'], true)) {
                throw new \RuntimeException("Tipo de questão não suportado pelo contrato OMR: {$type}.");
            }

            if ($type === 'true_false') {
                $optionCount = 2;
            } elseif ($type === 'essay') {
                $optionCount = 0;
            }

            if ($type !== 'essay' && ($optionCount < 2 || $optionCount > 9)) {
                throw new \RuntimeException('Questões objetivas devem possuir entre 2 e 9 alternativas no contrato OMR.');
            }

            $normalizedQuestions[] = ['type' => $type, 'option_count' => $optionCount];
        }
        $questions = $normalizedQuestions;

        if ($questions === []) {
            throw new \RuntimeException('O contrato OMR exige ao menos uma questão por página.');
        }
        if ($qStart < 1 || $templateId < 1 || $templateVersion < 1) {
            throw new \RuntimeException('Identificadores e versão do contrato OMR devem ser positivos.');
        }

        $columns = max((int) ($layout['columns'] ?? 2), 1);
        $rows = max((int) ($layout['rows_per_column'] ?? 20), 1);
        if (count($questions) > $columns * $rows) {
            throw new \RuntimeException('A página possui mais questões do que a geometria OMR comporta.');
        }

        $bubble = (float) ($layout['bubble_diameter_mm'] ?? 5.5);
        $fiducial = (float) ($layout['frame_fiducial_mm'] ?? 8.0);
        $frameX = (float) ($layout['frame_left_mm'] ?? 12.0);
        $frameY = (float) ($layout['frame_top_mm'] ?? 56.0);
        $frameWidth = (float) ($layout['frame_width_mm'] ?? 186.0);
        $rowSpacing = (float) ($layout['row_spacing_mm'] ?? 9.0);
        $startX = (float) ($layout['cell_indent_mm'] ?? 14.0);
        $startY = (float) ($layout['grid_pad_top_mm'] ?? 8.0);
        $optionSpacing = $bubble + (float) ($layout['option_gap_mm'] ?? 2.0);
        $columnSpacing = $frameWidth / $columns;

        if ($frameWidth <= 0 || $bubble <= 0 || $fiducial <= 0) {
            throw new \RuntimeException('Dimensões do contrato OMR devem ser positivas.');
        }
        if ($rowSpacing < $bubble + 1.0 || $optionSpacing < $bubble + 0.5) {
            throw new \RuntimeException('O espaçamento do template é insuficiente para separar as bolhas com segurança.');
        }

        $rowsUsed = max(5, min(count($questions), $rows));
        $frameHeight = $startY + ($rowsUsed * $rowSpacing) + 4.0;
        $g = [
            (int) round(($startX / $frameWidth) * 10000),
            (int) round(($startY / $frameHeight) * 10000),
            (int) round(($columnSpacing / $frameWidth) * 10000),
            (int) round(($rowSpacing / $frameHeight) * 10000),
            (int) round(($bubble / $frameWidth) * 10000),
            (int) round(($optionSpacing / $frameWidth) * 10000),
        ];

        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];
        $cells = [];
        foreach ($questions as $index => $question) {
            $column = intdiv($index, $rows);
            $row = $index % $rows;
            $cellTop = $frameY + $startY + ($row * $rowSpacing);
            $bubbleX = $frameX + ($column * $columnSpacing) + $startX;
            $bubbles = [];

            for ($option = 0; $option < $question['option_count']; $option++) {
                $bubbles[] = [
                    'label' => $letters[$option],
                    'x' => round($bubbleX + ($option * $optionSpacing), 3),
                    'y' => round($cellTop, 3),
                ];
            }

            $lastBubble = end($bubbles);
            $columnRight = $frameX + (($column + 1) * $columnSpacing);
            if ($lastBubble && (float) $lastBubble['x'] + $bubble > $columnRight - 1.0) {
                throw new \RuntimeException(
                    'As alternativas não cabem na coluna do template; aumente a largura ou reduza colunas/bolhas.'
                );
            }

            $cells[] = [
                'num' => $qStart + $index,
                'numX' => round($frameX + ($column * $columnSpacing) + 1.0, 3),
                'numY' => round($cellTop, 3),
                'essay' => $question['type'] === 'essay',
                'bubbles' => $bubbles,
            ];
        }

        $geometry = [
            'contract_version' => self::CONTRACT_VERSION,
            'paper' => ['size' => 'A4', 'width_mm' => 210, 'height_mm' => 297],
            'columns' => $columns,
            'index_basis' => 'page-local-zero-based',
            'g' => $g,
            'frame' => [
                'x' => $frameX,
                'y' => $frameY,
                'w' => $frameWidth,
                'h' => round($frameHeight, 3),
                'fid' => $fiducial,
            ],
            'bubbleMm' => $bubble,
            'cells' => $cells,
        ];
        $this->validate($geometry);

        $geometry['contract'] = [
            'g' => $g,
            'rpp' => $rows,
            'oc' => implode('', array_column($questions, 'option_count')),
            'qs' => $qStart,
            'qe' => $qStart + count($questions) - 1,
            'tpl_id' => $templateId,
            'tpl_v' => $templateVersion,
        ];
        $geometry['geometry_hash'] = hash('sha256', json_encode([
            'contract_version' => self::CONTRACT_VERSION,
            'paper' => $geometry['paper'],
            'columns' => $columns,
            'index_basis' => $geometry['index_basis'],
            'contract' => $geometry['contract'],
            'frame' => $geometry['frame'],
            'bubble_mm' => $geometry['bubbleMm'],
            'cells' => $geometry['cells'],
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        return $geometry;
    }

    /** @param array{frame: array, bubbleMm: float, cells: array} $geometry */
    private function validate(array $geometry): void
    {
        $frame = $geometry['frame'];
        $halfFiducial = ((float) $frame['fid']) / 2;
        $left = (float) $frame['x'] - $halfFiducial;
        $right = (float) $frame['x'] + (float) $frame['w'] + $halfFiducial;
        $top = (float) $frame['y'] - $halfFiducial;
        $bottomWithFooter = (float) $frame['y'] + (float) $frame['h'] + 10;

        if ($left < 0 || $right > 210 || $top < 45 || $bottomWithFooter > 297) {
            throw new \RuntimeException('A geometria do template ultrapassa a área segura da página A4 ou invade o cabeçalho.');
        }
        if ((float) $frame['fid'] < 7.0 || (float) $frame['fid'] > 12.0) {
            throw new \RuntimeException('Os marcadores do template OMR devem ter entre 7 e 12 mm para leitura confiável.');
        }

        $bubbleDiameter = (float) $geometry['bubbleMm'];
        $minimumClearance = 1.5;
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

                $nearLeft = (float) $bubble['x'] - ((float) $frame['x'] + $halfFiducial);
                $nearTop = (float) $bubble['y'] - ((float) $frame['y'] + $halfFiducial);
                if ($nearLeft < $minimumClearance || $nearTop < $minimumClearance) {
                    throw new \RuntimeException('A grade deve manter ao menos 3 mm livres ao redor dos marcadores OMR.');
                }
            }
        }
    }
}
