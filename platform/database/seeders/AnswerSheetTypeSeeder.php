<?php

namespace Database\Seeders;

use App\Models\AnswerSheetType;
use Illuminate\Database\Seeder;

class AnswerSheetTypeSeeder extends Seeder
{
    public function run(): void
    {
        AnswerSheetType::updateOrCreate(
            ['slug' => 'essential'],
            [
                'name' => 'Gabarito Essential',
                'description' => 'Modelo simplificado, mais confiável e robusto. Bolhas maiores (5.5mm), fiduciais maiores (6mm), 2 colunas com até 40 questões.',
                'is_system' => true,
                'is_default' => true,
                'layout_config' => [
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
                'grading_config' => [
                    'blank_handling' => 'zero_points',
                    'multiple_marks_handling' => 'zero_points',
                    'partial_credit' => false,
                    'confidence_threshold_auto_accept' => 0.85,
                    'confidence_threshold_review' => 0.65,
                    'confidence_threshold_rescan' => 0.50,
                ],
                'version' => 1,
                'is_active' => true,
            ]
        );

        AnswerSheetType::updateOrCreate(
            ['slug' => 'detailed'],
            [
                'name' => 'Gabarito Detalhado',
                'description' => 'Modelo completo com 4 colunas, até 60 questões, cabeçalho com nome, turma e disciplina. Compatível com o layout original.',
                'is_system' => true,
                'is_default' => false,
                'layout_config' => [
                    'paper_size' => 'A4',
                    'orientation' => 'portrait',
                    'margins_mm' => 10,
                    'max_questions' => 60,
                    'columns' => 4,
                    'rows_per_column' => 15,
                    'max_options' => 5,
                    'supports_vf' => true,
                    'supports_essay_space' => false,
                    'header_includes' => ['student_name', 'student_id', 'class', 'discipline'],
                    'fiducial_count' => 4,
                    'fiducial_size_mm' => 4.76,
                    'qr_position' => 'top_left',
                    'bubble_diameter_mm' => 4.2,
                    'area_width_mm' => 184.5,
                    'area_height_mm' => 153.4,
                    'col_bubble_start_mm' => [18.9, 62.4, 106.0, 149.5],
                    'col_bubble_end_mm' => [43.5, 87.0, 130.6, 174.1],
                    'grid_top_offset_mm' => 15.8,
                    'discipline_header_height_mm' => 6.8,
                ],
                'grading_config' => [
                    'blank_handling' => 'zero_points',
                    'multiple_marks_handling' => 'zero_points',
                    'partial_credit' => false,
                    'confidence_threshold_auto_accept' => 0.80,
                    'confidence_threshold_review' => 0.60,
                    'confidence_threshold_rescan' => 0.45,
                ],
                'version' => 1,
                'is_active' => true,
            ]
        );
    }
}
