<?php

namespace App\Support;

final class OmrSystemTemplateCatalog
{
    /** @return array<int, array<string, mixed>> */
    public static function definitions(): array
    {
        return [
            self::legacyStandard(),
            self::legacyDetailed(),
            self::template('sistema-leitura-ampliada-20', 'Leitura ampliada 20', 'Bolhas grandes para turmas menores', 20, 1, 20, 5, 6.5, 9.0, 12, 56, 186, 10, 16, 9, 2.5),
            self::template('sistema-conforto-30', 'Conforto 30', 'Espaçamento amplo em duas colunas', 30, 2, 15, 5, 6.0, 9.0, 12, 56, 186, 10, 15, 9, 2.0),
            self::template('sistema-equilibrado-36', 'Equilibrado 36', 'Boa densidade com bolhas médias', 36, 2, 18, 5, 5.8, 8.5, 12, 56, 186, 9.5, 14.5, 8.5, 1.8),
            self::template('sistema-tricoluna-45', 'Três colunas 45', 'Distribuição regular para avaliações médias', 45, 3, 15, 5, 4.8, 8.0, 10, 56, 190, 9, 10, 8, 1.5),
            self::template('sistema-extendido-50', 'Estendido 50', 'Duas colunas longas e leitura linear', 50, 2, 25, 5, 4.8, 8.0, 12, 56, 186, 8, 13, 7, 1.5),
            self::template('sistema-vf-ampliado-60', 'V/F ampliado 60', 'Duas alternativas com bolhas maiores', 60, 3, 20, 2, 6.0, 9.0, 10, 56, 190, 9, 12, 8, 2.0),
            self::template('sistema-alta-capacidade-72', 'Alta capacidade 72', 'Quatro colunas para avaliações extensas', 72, 4, 18, 5, 4.2, 8.0, 10, 56, 190, 8, 8, 7, 1.2),
            self::template('sistema-alta-capacidade-80', 'Alta capacidade 80', 'Máximo inicial em uma página A4', 80, 4, 20, 5, 4.2, 8.0, 10, 56, 190, 7.5, 8, 7, 1.2),
        ];
    }

    /** @return array<string, mixed> */
    private static function legacyStandard(): array
    {
        return [
            'slug' => 'sistema-padrao',
            'name' => 'Cartão Padrão (Sistema)',
            'purpose' => 'Uso geral e marcação confortável',
            'default' => true,
            // Contrato histórico: instalações existentes e novas mantêm a mesma versão 1.
            'layout' => [
                'paper_size' => 'A4',
                'orientation' => 'portrait',
                'margins_mm' => 12,
                'max_questions' => 40,
                'columns' => 2,
                'rows_per_column' => 20,
                'max_options' => 5,
                'supports_vf' => true,
                'supports_essay_space' => false,
                'header_includes' => ['student_id'],
                'fiducial_count' => 4,
                'fiducial_size_mm' => 6.0,
                'qr_position' => 'top_center',
                'bubble_diameter_mm' => 5.5,
                'area_width_mm' => 186.0,
                'area_height_mm' => 240.0,
                'col_bubble_start_mm' => [25.0, 110.0],
                'col_bubble_end_mm' => [85.0, 170.0],
                'grid_top_offset_mm' => 20.0,
                'discipline_header_height_mm' => 0,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function legacyDetailed(): array
    {
        return [
            'slug' => 'sistema-detalhado',
            'name' => 'Cartão Detalhado (Sistema)',
            'purpose' => 'Maior capacidade com quatro colunas',
            'default' => false,
            // Contrato histórico: não reinterprete o cartão detalhado já impresso.
            'layout' => [
                'paper_size' => 'A4',
                'orientation' => 'portrait',
                'margins_mm' => 10,
                'max_questions' => 60,
                'columns' => 4,
                'rows_per_column' => 15,
                'max_options' => 5,
                'bubble_diameter_mm' => 4.2,
                'frame_fiducial_mm' => 8.0,
                'frame_left_mm' => 10.0,
                'frame_top_mm' => 50.0,
                'frame_width_mm' => 192.0,
                'row_spacing_mm' => 8.0,
                'cell_indent_mm' => 9.0,
                'grid_pad_top_mm' => 6.0,
                'option_gap_mm' => 1.5,
                'qr_position' => 'top_right',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function template(
        string $slug,
        string $name,
        string $purpose,
        int $maxQuestions,
        int $columns,
        int $rows,
        int $maxOptions,
        float $bubble,
        float $fiducial,
        float $frameLeft,
        float $frameTop,
        float $frameWidth,
        float $rowSpacing,
        float $indent,
        float $paddingTop,
        float $optionGap,
        bool $default = false,
        string $qrPosition = 'top_right',
    ): array {
        return [
            'slug' => $slug,
            'name' => $name,
            'purpose' => $purpose,
            'default' => $default,
            'layout' => [
                'paper_size' => 'A4',
                'orientation' => 'portrait',
                'margins_mm' => 10,
                'max_questions' => $maxQuestions,
                'columns' => $columns,
                'rows_per_column' => $rows,
                'max_options' => $maxOptions,
                'supports_vf' => true,
                'bubble_diameter_mm' => $bubble,
                'frame_fiducial_mm' => $fiducial,
                'frame_left_mm' => $frameLeft,
                'frame_top_mm' => $frameTop,
                'frame_width_mm' => $frameWidth,
                'row_spacing_mm' => $rowSpacing,
                'cell_indent_mm' => $indent,
                'grid_pad_top_mm' => $paddingTop,
                'option_gap_mm' => $optionGap,
                'qr_position' => $qrPosition,
            ],
        ];
    }
}
