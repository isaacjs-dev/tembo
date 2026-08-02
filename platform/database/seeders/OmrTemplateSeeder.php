<?php

namespace Database\Seeders;

use App\Models\AnswerSheetType;
use App\Models\OmrTemplate;
use App\Models\OmrTemplateVersion;
use Illuminate\Database\Seeder;

/**
 * Cria o TEMPLATE PADRÃO do sistema (cartão único, geometria "essential" que já funciona).
 * É a base de todos os exames sem template explícito e o ponto de partida para templates
 * personalizados. Idempotente (updateOrCreate por slug).
 */
class OmrTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // Reusa a geometria do AnswerSheetType "essential" (fonte única); fallback inline.
        $essential = AnswerSheetType::withoutGlobalScopes()->where('slug', 'essential')->first();
        $layout = $essential?->layout_config ?? [
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'margins_mm' => 12,
            'max_questions' => 40,
            'columns' => 2,
            'rows_per_column' => 20,
            'max_options' => 5,
            'supports_vf' => true,
            'fiducial_count' => 4,
            'fiducial_size_mm' => 6.0,
            'qr_position' => 'top_center',
            'bubble_diameter_mm' => 5.5,
            'area_width_mm' => 186.0,
            'area_height_mm' => 240.0,
            'col_bubble_start_mm' => [25.0, 110.0],
            'grid_top_offset_mm' => 20.0,
        ];

        $template = OmrTemplate::updateOrCreate(
            ['slug' => 'sistema-padrao'],
            [
                'name' => 'Cartão Padrão (Sistema)',
                'organization_id' => null,
                'created_by' => null,
                'owner_type' => null,
                'owner_id' => null,
                'visibility_scope' => 'system',
                'is_system' => true,
                'is_default' => true,
                'is_active' => true,
                // Geometria efetiva (consumida por buildPageGeometry)
                'layout_config' => $layout,
                'header_config' => [
                    'title' => 'CARTÃO RESPOSTA',
                    'show_institution' => true,
                    'show_qr' => true,
                ],
                'logo_path' => null,
                // Limites do template (a prova pode usar menos)
                'max_questions' => (int) ($layout['max_questions'] ?? 40),
                'max_columns' => (int) ($layout['columns'] ?? 2),
                'columns' => (int) ($layout['columns'] ?? 2),
                'rows_per_column' => (int) ($layout['rows_per_column'] ?? 20),
                'max_options' => (int) ($layout['max_options'] ?? 5),
                'total_questions' => (int) ($layout['max_questions'] ?? 40),
                'total_pages' => 1,
                'current_version' => 1,
                // Campos legados NOT NULL do modelo antigo de ROI (não usados na leitura por g)
                'width' => 2480,
                'height' => 3508,
                'paper_size' => $layout['paper_size'] ?? 'A4',
                'orientation' => $layout['orientation'] ?? 'portrait',
                'corner_points_json' => ['TL' => [0, 0], 'TR' => [0, 0], 'BR' => [0, 0], 'BL' => [0, 0]],
                'thresholds_json' => ['mark' => 0.45, 'blank' => 0.15, 'uncertain_low' => 0.25, 'uncertain_high' => 0.40],
            ]
        );

        // Snapshot da versão 1
        OmrTemplateVersion::updateOrCreate(
            ['omr_template_id' => $template->id, 'version' => 1],
            [
                'layout_config' => $layout,
                'header_config' => $template->header_config,
                'logo_path' => $template->logo_path,
            ]
        );

        $this->seedDetailed();
    }

    /**
     * Template do sistema para provas grandes: 4 colunas, até 60 questões (bolhas menores).
     * Usa a mesma blade/geometria absoluta — buildPageGeometry adapta o nº de colunas.
     */
    private function seedDetailed(): void
    {
        $layout = [
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
        ];

        $template = OmrTemplate::updateOrCreate(
            ['slug' => 'sistema-detalhado'],
            [
                'name' => 'Cartão Detalhado (Sistema)',
                'organization_id' => null,
                'created_by' => null,
                'owner_type' => null,
                'owner_id' => null,
                'visibility_scope' => 'system',
                'is_system' => true,
                'is_default' => false,
                'is_active' => true,
                'layout_config' => $layout,
                'header_config' => ['title' => 'CARTÃO RESPOSTA', 'show_institution' => true, 'show_qr' => true],
                'logo_path' => null,
                'max_questions' => 60,
                'max_columns' => 4,
                'columns' => 4,
                'rows_per_column' => 15,
                'max_options' => 5,
                'total_questions' => 60,
                'total_pages' => 1,
                'current_version' => 1,
                'width' => 2480,
                'height' => 3508,
                'paper_size' => 'A4',
                'orientation' => 'portrait',
                'corner_points_json' => ['TL' => [0, 0], 'TR' => [0, 0], 'BR' => [0, 0], 'BL' => [0, 0]],
                'thresholds_json' => ['mark' => 0.45, 'blank' => 0.15, 'uncertain_low' => 0.25, 'uncertain_high' => 0.40],
            ]
        );

        OmrTemplateVersion::updateOrCreate(
            ['omr_template_id' => $template->id, 'version' => 1],
            ['layout_config' => $layout, 'header_config' => $template->header_config, 'logo_path' => null]
        );
    }
}
