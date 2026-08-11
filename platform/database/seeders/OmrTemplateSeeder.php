<?php

namespace Database\Seeders;

use App\Models\OmrTemplate;
use App\Models\OmrTemplateVersion;
use App\Support\OmrSystemTemplateCatalog;
use Illuminate\Database\Seeder;

class OmrTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (OmrSystemTemplateCatalog::definitions() as $definition) {
            $layout = $definition['layout'];
            $header = [
                'title' => 'CARTÃO RESPOSTA',
                'show_institution' => true,
                'show_qr' => true,
            ];
            $template = OmrTemplate::firstOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'organization_id' => null,
                    'created_by' => null,
                    'owner_type' => null,
                    'owner_id' => null,
                    'visibility_scope' => 'system',
                    'is_system' => true,
                    'is_default' => $definition['default'],
                    'is_active' => true,
                    'layout_config' => $layout,
                    'header_config' => $header,
                    'logo_path' => null,
                    'max_questions' => $layout['max_questions'],
                    'max_columns' => $layout['columns'],
                    'columns' => $layout['columns'],
                    'rows_per_column' => $layout['rows_per_column'],
                    'max_options' => $layout['max_options'],
                    'total_questions' => $layout['max_questions'],
                    'total_pages' => 1,
                    'current_version' => 1,
                    'width' => 2480,
                    'height' => 3508,
                    'paper_size' => 'A4',
                    'orientation' => 'portrait',
                    'corner_points_json' => ['TL' => [0, 0], 'TR' => [0, 0], 'BR' => [0, 0], 'BL' => [0, 0]],
                    'thresholds_json' => ['mark' => 0.45, 'blank' => 0.15, 'uncertain_low' => 0.25, 'uncertain_high' => 0.40],
                ],
            );

            OmrTemplateVersion::firstOrCreate(
                ['omr_template_id' => $template->id, 'version' => 1],
                $this->versionPayload($template, $layout, $header),
            );
        }
    }

    /** @return array<string, mixed> */
    private function versionPayload(OmrTemplate $template, array $layout, array $header): array
    {
        $definition = [
            'schema_version' => 2,
            'paper' => [
                'width' => (int) $template->width,
                'height' => (int) $template->height,
                'paper_size' => 'A4',
                'orientation' => 'portrait',
            ],
            'layout_config' => $layout,
            'header_config' => $header,
            'logo_path' => null,
            'corner_points' => $template->corner_points_json ?? [],
            'thresholds' => $template->thresholds_json ?? [],
            'calibration' => [],
            'qr_region' => [],
            'questions' => [],
            'legacy_questions_source' => null,
        ];

        return [
            'schema_version' => 2,
            'layout_config' => $layout,
            'header_config' => $header,
            'logo_path' => null,
            'definition' => $definition,
            'content_hash' => hash('sha256', json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'created_by' => null,
        ];
    }
}
